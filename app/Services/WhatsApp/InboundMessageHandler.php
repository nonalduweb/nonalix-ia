<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Data\WhatsApp\InboundMessage;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\OptInStatus;
use App\Enums\SenderType;
use App\Events\MessageCreated;
use App\Jobs\AI\GenerateAgentReplyJob;
use App\Jobs\Voice\TranscribeInboundAudioJob;
use App\Jobs\WhatsApp\SendWhatsAppMessageJob;
use App\Models\ConsentLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use App\Services\Billing\QuotaService;
use Illuminate\Support\Facades\DB;

/**
 * Traite un message entrant : contact, conversation, persistance, réponse.
 *
 * Toutes les écritures sont idempotentes. Un rejeu de webhook ne doit jamais
 * produire un message en double dans la conversation d'un client.
 */
class InboundMessageHandler
{
    public function __construct(private readonly QuotaService $quotas) {}

    public function handle(Tenant $tenant, WhatsAppAccount $account, InboundMessage $inbound): void
    {
        // Court-circuit : ce wamid a déjà été enregistré lors d'une livraison
        // précédente. On sort avant toute écriture.
        if (Message::query()->where('wamid', $inbound->wamid)->exists()) {
            return;
        }

        $contact      = $this->upsertContact($inbound);
        $conversation = $this->openConversation($contact, $account, $inbound);

        $message = DB::transaction(function () use ($conversation, $inbound) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'wamid'           => $inbound->wamid,
                'direction'       => MessageDirection::Inbound,
                'sender_type'     => SenderType::Contact,
                'type'            => $inbound->type,
                'body'            => $inbound->body,
                'media'           => $inbound->media,
                'context_wamid'   => $inbound->contextWamid,
                // Un message reçu est par définition distribué.
                'status'          => MessageStatus::Delivered,
                'delivered_at'    => $inbound->timestamp,
            ]);

            $conversation->refreshServiceWindow($inbound->timestamp);
            $conversation->forceFill([
                'last_message_at' => $inbound->timestamp,
                'unread_count'    => $conversation->unread_count + 1,
            ])->save();

            return $message;
        });

        $this->quotas->increment($tenant, 'messages_received');

        MessageCreated::dispatch($message);

        // Le consentement prime sur tout : un contact qui écrit STOP ne doit
        // pas voir son message passer à l'agent IA.
        if ($this->handleConsentKeywords($tenant, $contact, $conversation, $inbound)) {
            return;
        }

        if ($contact->opt_in_status === OptInStatus::OptedOut) {
            return;
        }

        if (! $conversation->shouldAiRespond()) {
            return;
        }

        if ($inbound->hasText()) {
            GenerateAgentReplyJob::dispatch(
                tenantId: $tenant->id,
                conversationId: $conversation->id,
                incomingText: (string) $inbound->textContent(),
            )->onQueue('ai');

            return;
        }

        // Note vocale : elle passe d'abord par la transcription, qui rendra
        // la main au même moteur. Le chemin texte ci-dessus est inchangé —
        // c'est une extension du pipeline, pas une réécriture.
        if ($inbound->type === MessageType::Audio) {
            TranscribeInboundAudioJob::dispatch(
                tenantId: $tenant->id,
                messageId: $message->id,
            )->onQueue('ai');
        }
    }

    /**
     * Crée ou met à jour le contact.
     *
     * Le nom de profil WhatsApp est rafraîchi à chaque message (il peut
     * changer), mais on n'écrase jamais un nom saisi par un opérateur.
     */
    private function upsertContact(InboundMessage $inbound): Contact
    {
        $contact = Contact::query()->firstOrNew(['wa_id' => $inbound->from]);

        $contact->fill([
            'phone_number'    => '+'.$inbound->from,
            'profile_name'    => $inbound->profileName ?? $contact->profile_name,
            'last_message_at' => $inbound->timestamp,
        ]);

        $contact->save();

        return $contact;
    }

    /**
     * Récupère la conversation ouverte du contact, ou en crée une.
     *
     * Un index unique partiel garantit qu'il n'y en a jamais deux ouvertes
     * simultanément pour le même contact.
     */
    private function openConversation(
        Contact $contact,
        WhatsAppAccount $account,
        InboundMessage $inbound,
    ): Conversation {
        $conversation = Conversation::query()
            ->where('contact_id', $contact->id)
            ->whereNull('closed_at')
            ->first();

        if ($conversation !== null) {
            return $conversation;
        }

        $hours = (int) config('whatsapp.service_window_hours', 24);

        return Conversation::create([
            'contact_id'          => $contact->id,
            'whatsapp_account_id' => $account->id,
            'channel'             => 'whatsapp',
            'status'              => ConversationStatus::Open,
            'ai_enabled'          => true,
            'last_message_at'     => $inbound->timestamp,
            'last_inbound_at'     => $inbound->timestamp,
            'window_expires_at'   => $inbound->timestamp->addHours($hours),
        ]);
    }

    /**
     * Traite STOP / START.
     *
     * La désinscription est immédiate, inconditionnelle et confirmée. Elle est
     * gérée AVANT l'IA : faire transiter « STOP » par un LLM, c'est accepter
     * qu'il puisse décider de ne pas en tenir compte.
     *
     * @return bool  true si un mot-clé a été traité (l'IA ne doit pas répondre)
     */
    private function handleConsentKeywords(
        Tenant $tenant,
        Contact $contact,
        Conversation $conversation,
        InboundMessage $inbound,
    ): bool {
        $text = mb_strtolower(trim((string) $inbound->textContent()));

        if ($text === '') {
            return false;
        }

        $optOut = array_map('mb_strtolower', config('whatsapp.consent.opt_out_keywords', []));
        $optIn  = array_map('mb_strtolower', config('whatsapp.consent.opt_in_keywords', []));

        // Correspondance exacte : un message contenant « stop » au milieu
        // d'une phrase n'est pas une demande de désinscription.
        if (in_array($text, $optOut, true)) {
            $this->applyConsent($contact, $conversation, OptInStatus::OptedOut, $inbound);
            $this->replySystem($tenant, $conversation, (string) config('whatsapp.consent.opt_out_reply'));

            return true;
        }

        if (in_array($text, $optIn, true)) {
            $this->applyConsent($contact, $conversation, OptInStatus::OptedIn, $inbound);
            $this->replySystem($tenant, $conversation, (string) config('whatsapp.consent.opt_in_reply'));

            return true;
        }

        return false;
    }

    private function applyConsent(
        Contact $contact,
        Conversation $conversation,
        OptInStatus $status,
        InboundMessage $inbound,
    ): void {
        $isOptOut = $status === OptInStatus::OptedOut;

        $contact->forceFill([
            'opt_in_status' => $status,
            'opt_in_source' => 'keyword',
            $isOptOut ? 'opt_out_at' : 'opt_in_at' => now(),
        ])->save();

        ConsentLog::create([
            'contact_id'  => $contact->id,
            'action'      => $isOptOut ? 'opt_out' : 'opt_in',
            'channel'     => 'whatsapp',
            'source'      => 'keyword',
            'raw_message' => $inbound->textContent(),
            'created_at'  => now(),
        ]);

        // Une désinscription ferme la conversation : continuer à afficher un
        // fil actif inviterait un opérateur à y répondre.
        if ($isOptOut) {
            $conversation->forceFill([
                'ai_enabled' => false,
                'status'     => ConversationStatus::Closed,
                'closed_at'  => now(),
            ])->save();
        }
    }

    /** Envoie un message système (confirmation d'opt-in/opt-out). */
    private function replySystem(Tenant $tenant, Conversation $conversation, string $body): void
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => MessageDirection::Outbound,
            'sender_type'     => SenderType::System,
            'type'            => \App\Enums\MessageType::Text,
            'body'            => $body,
            'status'          => MessageStatus::Queued,
        ]);

        SendWhatsAppMessageJob::dispatch($tenant->id, $message->id)->onQueue('whatsapp');
    }
}
