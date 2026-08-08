<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Contracts\AI\AgentTool;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\SenderType;
use App\Events\MessageCreated;
use App\Exceptions\QuotaExceededException;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Jobs\WhatsApp\SendWhatsAppMessageJob;
use App\Models\Agent;
use App\Models\BusinessHour;
use App\Models\BusinessProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AgentRunner;
use App\Services\AI\EmailClassifier;
use App\Services\Billing\QuotaService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Génère et envoie la réponse de l'agent IA.
 *
 * Verrouillé par conversation : deux messages arrivés coup sur coup ne doivent
 * pas produire deux réponses concurrentes qui s'ignorent mutuellement.
 */
class GenerateAgentReplyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RunsInTenantContext;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $tenantId,
        public readonly string $conversationId,
        public readonly string $incomingText,
    ) {}

    public function handle(AgentRunner $runner, QuotaService $quotas, EmailClassifier $classifier): void
    {
        $this->withTenant($this->tenantId, function ($tenant) use ($runner, $quotas, $classifier) {
            // Le verrou couvre toute la génération : une réponse peut prendre
            // plusieurs secondes, pendant lesquelles un second message peut
            // arriver. Sans verrou, le contact recevrait deux réponses
            // construites sur le même historique incomplet.
            $lock = Cache::lock('conversation:'.$this->conversationId, (int) config('ai.agent.lock_seconds', 60));

            if (! $lock->get()) {
                // Une génération est déjà en cours : on réessaie plus tard,
                // l'historique aura alors intégré les deux messages.
                $this->release(10);

                return;
            }

            try {
                $conversation = Conversation::query()->find($this->conversationId);

                // `shouldAiRespond()` et non le seul `ai_enabled` : il couvre
                // aussi la reprise par un humain et les conversations closes,
                // sur lesquelles l'agent n'a plus rien à dire.
                if ($conversation === null || ! $conversation->shouldAiRespond()) {
                    return;
                }

                $agent = $conversation->agent ?? $tenant->activeAgent();

                // Un agent désactivé ne parle pas. C'est aussi ce que le widget
                // promet au visiteur via `auto_reply` : les deux doivent dire
                // la même chose.
                if ($agent === null || ! $agent->is_active) {
                    return;
                }

                // Hors horaires si l'entreprise l'a demandé : on ne répond pas
                // plutôt que d'envoyer une réponse automatique à 3 h du matin.
                if ($agent->active_hours_only && ! $this->isWithinBusinessHours()) {
                    return;
                }

                // Mot-clé de transfert détecté avant tout appel au LLM : le
                // contact ne doit pas attendre une génération pour être
                // transféré, ni risquer que le modèle passe outre.
                if ($agent->detectsHandoverRequest($this->incomingText)) {
                    $this->handOver($conversation, 'demande_explicite');

                    return;
                }

                try {
                    $quotas->assertWithinQuota($tenant, 'ai_requests');
                } catch (QuotaExceededException $e) {
                    // Quota épuisé : on bascule sur un humain plutôt que de
                    // laisser le contact sans interlocuteur.
                    Log::channel('ai')->warning('Quota IA atteint, transfert humain.', $e->context());
                    $this->handOver($conversation, 'quota_ia_atteint');

                    return;
                }

                // Le courrier est plus engageant qu'un message de chat : selon
                // le réglage de l'entreprise, la réponse peut n'être qu'un
                // brouillon soumis à validation.
                $status = $conversation->channel === 'email'
                    ? $this->resolveEmailStatus($conversation, $agent, $classifier, $quotas, $tenant)
                    : MessageStatus::Queued;

                $result = $runner->run(
                    conversation: $conversation,
                    agent: $agent,
                    incomingText: $this->incomingText,
                    tools: $this->resolveTools($agent),
                );

                // L'agent a transféré via un outil : il a déjà mis à jour la
                // conversation, mais peut aussi avoir un message d'annonce.
                if ($result['content'] === null) {
                    return;
                }

                $this->sendReply($tenant->id, $conversation, $result, $status);
            } catch (Throwable $e) {
                Log::channel('ai')->error('Échec de génération de la réponse IA.', [
                    'tenant_id'       => $this->tenantId,
                    'conversation_id' => $this->conversationId,
                    'error'           => $e->getMessage(),
                ]);

                // Dernière tentative seulement : le contact reçoit le message
                // de repli et la conversation part vers un humain. L'envoyer à
                // chaque tentative lui en enverrait trois d'affilée.
                if ($this->attempts() >= $this->tries) {
                    $this->sendFallback($tenant);

                    return;
                }

                throw $e;
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * Statut de la réponse à un courrier : envoyée, ou retenue en brouillon.
     *
     * En mode assisté, rien ne part sans un humain. En mode automatique, la
     * classification décide : un litige, une réclamation financière ou une
     * intention hors des catégories autorisées repassent la main.
     */
    private function resolveEmailStatus(
        Conversation $conversation,
        Agent $agent,
        EmailClassifier $classifier,
        QuotaService $quotas,
        $tenant,
    ): MessageStatus {
        if (($agent->settings['email_mode'] ?? 'assisted') !== 'automatic') {
            return MessageStatus::Draft;
        }

        [$subject, $body] = $this->splitSubject($this->incomingText);

        $classification = $classifier->classify($agent, $subject, $body);

        // La classification est un appel au modèle comme un autre : elle doit
        // peser sur le quota, faute de quoi le canal e-mail consommerait le
        // double sans que rien ne le montre.
        $quotas->increment($tenant->id, 'ai_requests');

        if ($classification['is_sensitive']) {
            $this->handOver($conversation, 'email_sensible');

            return MessageStatus::Draft;
        }

        $allowed = $agent->settings['email_auto_categories'] ?? ['faq', 'horaires', 'autre'];

        if (! in_array($classification['intent'], $allowed, true)) {
            $this->handOver($conversation, 'email_intent_restreint');

            return MessageStatus::Draft;
        }

        return MessageStatus::Queued;
    }

    /**
     * Sépare le sujet du corps, tels que le webhook les a assemblés.
     *
     * @return array{0: string, 1: string}
     */
    private function splitSubject(string $text): array
    {
        if (preg_match('/^Sujet\s*:\s*(.+)\n\n([\s\S]+)$/u', $text, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return ['Demande client', $text];
    }

    /**
     * Outils autorisés pour cet agent.
     *
     * La liste blanche vient de la base : un agent ne peut pas invoquer un
     * outil que l'entreprise n'a pas activé.
     *
     * @return array<string, AgentTool>
     */
    private function resolveTools(Agent $agent): array
    {
        /** @var array<string, AgentTool> $registry */
        $registry = app('nonalix.agent.tools');

        return array_filter(
            $registry,
            static fn (string $name) => $agent->allowsTool($name),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @param array{content: ?string, metadata: array<string, mixed>} $result */
    private function sendReply(
        string $tenantId,
        Conversation $conversation,
        array $result,
        MessageStatus $status = MessageStatus::Queued,
    ): void {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => MessageDirection::Outbound,
            'sender_type'     => SenderType::Ai,
            'type'            => MessageType::Text,
            'body'            => $result['content'],
            'status'          => $status,
            'ai_meta'         => $result['metadata'],
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        MessageCreated::dispatch($message);

        // Un brouillon attend l'opérateur : il s'affiche dans la boîte de
        // réception, mais rien ne part.
        if ($status === MessageStatus::Draft) {
            return;
        }

        $this->dispatchToChannel($tenantId, $conversation, $message);
    }

    private function handOver(Conversation $conversation, string $reason): void
    {
        $conversation->forceFill([
            'ai_enabled'      => false,
            'handover_at'     => now(),
            'handover_reason' => $reason,
            'status'          => ConversationStatus::Pending,
        ])->save();
    }

    private function sendFallback($tenant): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        $agent        = $conversation?->agent ?? $tenant->activeAgent();

        if ($conversation === null || $agent === null) {
            return;
        }

        $this->handOver($conversation, 'echec_ia');

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => MessageDirection::Outbound,
            'sender_type'     => SenderType::System,
            'type'            => MessageType::Text,
            'body'            => $agent->effectiveFallbackMessage(),
            'status'          => MessageStatus::Queued,
        ]);

        $this->dispatchToChannel($tenant->id, $conversation, $message);
    }

    /** Remet le message au transporteur du canal concerné. */
    private function dispatchToChannel(string $tenantId, Conversation $conversation, Message $message): void
    {
        match ($conversation->channel) {
            // Le widget lit la conversation par sondage : rien à transporter.
            'web'   => $message->update(['status' => MessageStatus::Delivered]),
            'email' => SendEmailMessageJob::dispatch($tenantId, $message->id)->onQueue('email'),
            default => SendWhatsAppMessageJob::dispatch($tenantId, $message->id)->onQueue('whatsapp'),
        };
    }

    private function isWithinBusinessHours(): bool
    {
        $hours = BusinessHour::query()->get();

        if ($hours->isEmpty()) {
            return true;
        }

        $timezone = BusinessProfile::query()->value('timezone') ?? 'Europe/Paris';

        return BusinessHour::isOpenAt($hours, CarbonImmutable::now($timezone));
    }
}
