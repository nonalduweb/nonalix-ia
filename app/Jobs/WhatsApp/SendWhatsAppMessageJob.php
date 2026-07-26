<?php

declare(strict_types=1);

namespace App\Jobs\WhatsApp;

use App\Enums\IncidentLevel;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\MessageStatusUpdated;
use App\Exceptions\ServiceWindowClosedException;
use App\Exceptions\WhatsAppException;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\Incident;
use App\Models\Message;
use App\Services\Billing\QuotaService;
use App\Services\WhatsApp\CloudApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi d'un message vers l'API Meta.
 *
 * Seule voie de sortie : aucun envoi n'est fait de façon synchrone dans une
 * requête HTTP. Un appel Meta prend entre 200 ms et plusieurs secondes, et
 * faire attendre un opérateur derrière un formulaire pour cela serait à la
 * fois lent et fragile.
 */
class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RunsInTenantContext;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public array $backoff = [5, 15, 60, 300];

    public function __construct(
        public readonly string $tenantId,
        public readonly string $messageId,
        /** @var array<int, string> */
        public readonly array $templateParameters = [],
    ) {}

    public function handle(QuotaService $quotas): void
    {
        $this->withTenant($this->tenantId, function ($tenant) use ($quotas) {
            $message = Message::query()->with('conversation.contact', 'conversation.whatsappAccount', 'template')
                ->find($this->messageId);

            if ($message === null || $message->status !== MessageStatus::Queued) {
                return;
            }

            $conversation = $message->conversation;
            $account      = $conversation->whatsappAccount;
            $contact      = $conversation->contact;

            // -- Contrôles préalables -----------------------------------------
            if (! $account->canSend()) {
                $this->fail($message, 'account_not_connected', 'Le compte WhatsApp n\'est pas connecté.');

                return;
            }

            if (! $contact->isReachable()) {
                $this->fail($message, 'contact_unreachable', 'Le contact est désinscrit ou bloqué.');

                return;
            }

            $isTemplate = $message->type === MessageType::Template;

            // Hors fenêtre de 24 h, Meta n'accepte qu'un template approuvé.
            // On bloque en amont : un rejet côté Meta dégrade la note de
            // qualité du numéro du client, ce qui est bien plus coûteux.
            if (! $isTemplate && ! $conversation->isWithinServiceWindow()) {
                $this->fail($message, 'service_window_closed', 'Fenêtre de 24 h fermée.');

                throw new ServiceWindowClosedException($conversation->id, $conversation->window_expires_at);
            }

            // Verrou anti-double-envoi : si le job est rejoué après un timeout
            // réseau alors que Meta a bien reçu la requête, on n'envoie pas deux fois.
            $lock = Cache::lock('wa-send:'.$message->id, 120);

            if (! $lock->get()) {
                return;
            }

            try {
                $client = CloudApiClient::for($account);

                $wamid = $isTemplate && $message->template !== null
                    ? $client->sendTemplate(
                        to: $contact->wa_id,
                        templateName: $message->template->name,
                        language: $message->template->language,
                        bodyParameters: $this->templateParameters,
                    )
                    : $client->sendText(
                        to: $contact->wa_id,
                        body: (string) $message->body,
                        replyToWamid: $message->context_wamid,
                    );

                $message->forceFill([
                    'wamid'   => $wamid,
                    'status'  => MessageStatus::Sent,
                    'sent_at' => now(),
                    'error'   => null,
                ])->save();

                $quotas->increment($tenant, 'messages_sent');

                MessageStatusUpdated::dispatch($message);
            } catch (WhatsAppException $e) {
                $this->handleMetaError($message, $e);
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * Une erreur transitoire est relancée pour bénéficier du backoff ; une
     * erreur définitive marque le message en échec, sans nouvelle tentative.
     */
    private function handleMetaError(Message $message, WhatsAppException $e): void
    {
        Incident::record(
            tenantId: $message->tenant_id,
            level: $e->retryable ? IncidentLevel::Warning : IncidentLevel::Error,
            source: 'whatsapp',
            code: 'send_failure.'.($e->metaCode ?? 'unknown'),
            title: 'Échec d\'envoi WhatsApp : '.mb_substr($e->getMessage(), 0, 150),
            context: $e->context(),
        );

        if ($e->retryable && $this->attempts() < $this->tries) {
            throw $e;
        }

        $this->fail($message, (string) ($e->metaCode ?? 'unknown'), $e->getMessage(), $e->context());
    }

    /** @param array<string, mixed> $context */
    private function fail(Message $message, string $code, string $reason, array $context = []): void
    {
        $message->forceFill([
            'status'    => MessageStatus::Failed,
            'failed_at' => now(),
            'error'     => array_merge(['code' => $code, 'message' => $reason], $context),
        ])->save();

        MessageStatusUpdated::dispatch($message);
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('whatsapp')->error('Envoi WhatsApp définitivement en échec.', [
            'tenant_id'  => $this->tenantId,
            'message_id' => $this->messageId,
            'error'      => $exception->getMessage(),
        ]);
    }
}
