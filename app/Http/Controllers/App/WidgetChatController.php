<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\OptInStatus;
use App\Enums\SenderType;
use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Jobs\AI\GenerateAgentReplyJob;
use App\Jobs\Voice\TranscribeWidgetAudioJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WidgetChatController
{
    public function __construct(
        private readonly TenantContext $context
    ) {}

    /** Renvoie la configuration de l'agent et l'historique des messages. */
    public function config(Request $request, string $tenantId): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
        } catch (Throwable) {
            return response()->json(['error' => 'Entreprise introuvable.'], 404);
        }

        return $this->context->runAs($tenant, function () use ($tenant, $request) {
            $agent = $tenant->activeAgent();
            $sessionId = $request->query('session_id');
            $messages = [];

            if ($sessionId) {
                $contact = Contact::where('wa_id', 'web_' . $sessionId)->first();
                if ($contact) {
                    // Même critère que `chat()` : la conversation web en cours
                    // est celle qui n'est pas close. Sans ce filtre, l'historique
                    // affiché pouvait être celui d'une conversation clôturée.
                    $conversation = Conversation::where('contact_id', $contact->id)
                        ->where('channel', 'web')
                        ->whereNull('closed_at')
                        ->first();

                    if ($conversation) {
                        $messages = $conversation->messages()
                            ->orderBy('created_at')
                            ->limit(50)
                            ->get()
                            ->map(fn ($m) => [
                                'id'         => $m->id,
                                'body'       => $m->body,
                                'direction'  => $m->direction->value,
                                'created_at' => $m->created_at->toIso8601String(),
                                // Un message vocal se lit ET s'écoute : le
                                // texte reste là pour qui préfère lire.
                                'audio_url'  => $this->audioUrl($tenant, $m, $sessionId),
                                'duration'   => $m->media['duration_seconds'] ?? null,
                            ]);
                    }
                }
            }

            return response()->json([
                // Le micro ne s'affiche que si l'entreprise a activé la voix.
                'voice_enabled'    => $agent?->voiceEnabled() ?? false,
                'agent_name'       => $agent?->name ?? 'Assistant',
                'persona'          => $agent?->persona ?? 'Assistant Virtuel',
                'greeting_message' => $agent?->greeting_message ?? 'Bonjour ! Comment puis-je vous aider ?',
                'theme_color'      => $agent?->settings['theme_color'] ?? '#005c4b',
                'messages'         => $messages,
            ]);
        });
    }

    /** Reçoit un message du visiteur web et déclenche l'agent IA. */
    public function chat(Request $request, string $tenantId): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
        } catch (Throwable) {
            return response()->json(['error' => 'Entreprise introuvable.'], 404);
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:120'],
            'body'       => ['required', 'string', 'max:5000'],
        ]);

        return $this->context->runAs($tenant, function () use ($tenant, $validated) {
            $sessionId = $validated['session_id'];
            $body = $validated['body'];

            // 1. Trouver ou créer le contact virtuel pour le web chat
            $contact = Contact::firstOrCreate(
                ['wa_id' => 'web_' . $sessionId],
                [
                    'name'          => 'Visiteur Web',
                    'profile_name'  => 'Navigateur Web',
                    'opt_in_status' => OptInStatus::OptedIn,
                ]
            );

            // 2. Trouver ou créer la conversation web active
            $conversation = Conversation::where('contact_id', $contact->id)
                ->where('channel', 'web')
                ->whereNull('closed_at')
                ->first();

            if (! $conversation) {
                // Aucun compte WhatsApp : une conversation web n'en a pas
                // besoin, la colonne est nullable depuis la migration 000028.
                // Ne JAMAIS emprunter le compte d'un autre tenant pour
                // satisfaire la clé étrangère — c'est une fuite de données.
                $conversation = Conversation::create([
                    'contact_id'          => $contact->id,
                    'whatsapp_account_id' => null,
                    'channel'             => 'web',
                    'status'              => ConversationStatus::Open,
                    'ai_enabled'          => true,
                ]);
            }

            // 3. Enregistrer le message entrant
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction'       => MessageDirection::Inbound,
                'sender_type'     => SenderType::Contact,
                'type'            => MessageType::Text,
                'body'            => $body,
                'status'          => MessageStatus::Delivered,
            ]);

            $conversation->forceFill([
                'last_message_at' => now(),
                'last_inbound_at' => now(),
            ])->save();

            // Diffuser aux opérateurs pour la mise à jour temps réel de l'inbox
            MessageCreated::dispatch($message);
            ConversationUpdated::dispatch($conversation);

            // 4. Déclencher le job de réponse de l'agent IA
            GenerateAgentReplyJob::dispatch($tenant->id, $conversation->id, $body);

            // 5. Dire au visiteur s'il faut attendre une réponse.
            //
            // Sans agent actif — entreprise qui vient de s'inscrire, agent
            // jamais active, conversation reprise par un humain — le job se
            // termine sans rien produire. Le visiteur restait alors devant un
            // chat muet, indéfiniment, sans savoir si son message était parti.
            // Le message est bien enregistré et visible dans la boîte de
            // réception : ce que l'on annonce ici est exact.
            $agent = $conversation->agent ?? $tenant->activeAgent();

            return response()->json([
                'status'     => 'queued',
                'auto_reply' => $conversation->shouldAiRespond()
                    && $agent !== null
                    && $agent->is_active,
            ]);
        });
    }

    /**
     * Reçoit un message VOCAL du visiteur.
     *
     * Même conversation que le texte, délibérément : c'est ce qui donne à
     * l'agent la mémoire de l'échange entier et l'accès à ses outils. Un
     * visiteur qui écrit puis parle poursuit la même discussion.
     */
    public function voice(Request $request, string $tenantId): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
        } catch (Throwable) {
            return response()->json(['error' => 'Entreprise introuvable.'], 404);
        }

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:120'],
            'audio'      => [
                'required', 'file',
                'max:'.(int) (config('elevenlabs.limits.max_audio_bytes') / 1024),
                'mimetypes:'.implode(',', (array) config('elevenlabs.limits.allowed_mime')),
            ],
        ]);

        return $this->context->runAs($tenant, function () use ($tenant, $validated, $request) {
            $agent = $tenant->activeAgent();

            if ($agent === null || ! $agent->voiceEnabled()) {
                return response()->json(['error' => 'Les messages vocaux ne sont pas activés.'], 422);
            }

            $conversation = $this->conversationFor($validated['session_id']);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction'       => MessageDirection::Inbound,
                'sender_type'     => SenderType::Contact,
                'type'            => MessageType::Audio,
                'body'            => null,
                'status'          => MessageStatus::Delivered,
            ]);

            // Rangé AVANT toute transcription : si le fournisseur tombe,
            // l'enregistrement du visiteur n'est pas perdu pour autant.
            $file = $request->file('audio');
            $path = $tenant->id.'/voice/'.$message->id.'.'.($file->guessExtension() ?: 'webm');

            Storage::disk('media')->put($path, $file->get());

            $message->forceFill(['media' => [
                'storage_path' => $path,
                'mime_type'    => $file->getMimeType(),
            ]])->save();

            $conversation->forceFill([
                'last_message_at' => now(),
                'last_inbound_at' => now(),
            ])->save();

            MessageCreated::dispatch($message);
            ConversationUpdated::dispatch($conversation);

            TranscribeWidgetAudioJob::dispatch($tenant->id, $message->id)->onQueue('ai');

            return response()->json(['status' => 'queued', 'message_id' => $message->id]);
        });
    }

    /**
     * Sert l'audio d'un message au visiteur qui en est l'auteur.
     *
     * Aucune authentification ici, mais aucun accès libre non plus : le
     * message doit appartenir à la conversation de CETTE session. Sans cette
     * vérification, connaître un identifiant suffirait à écouter la
     * conversation d'un autre.
     */
    public function audio(Request $request, string $tenantId, string $messageId): mixed
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
        } catch (Throwable) {
            abort(404);
        }

        $sessionId = (string) $request->query('session_id');

        abort_if($sessionId === '', 404);

        return $this->context->runAs($tenant, function () use ($sessionId, $messageId) {
            $conversation = $this->existingConversation($sessionId);

            abort_if($conversation === null, 404);

            $message = Message::query()
                ->where('conversation_id', $conversation->id)
                ->whereKey($messageId)
                ->first();

            $path = $message?->media['storage_path'] ?? null;

            abort_if(! is_string($path) || ! Storage::disk('media')->exists($path), 404);

            return Storage::disk('media')->response($path, null, [
                'Content-Type'  => $message->media['mime_type'] ?? 'audio/mpeg',
                'Cache-Control' => 'private, no-store',
            ]);
        });
    }

    /** Lien d'écoute, seulement pour un message qui porte un audio. */
    private function audioUrl(Tenant $tenant, Message $message, string $sessionId): ?string
    {
        if (($message->media['storage_path'] ?? null) === null) {
            return null;
        }

        return '/widget/audio/'.$tenant->id.'/'.$message->id.'?session_id='.urlencode($sessionId);
    }

    /** Conversation web en cours pour cette session, créée au besoin. */
    private function conversationFor(string $sessionId): Conversation
    {
        $contact = Contact::firstOrCreate(
            ['wa_id' => 'web_'.$sessionId],
            [
                'name'          => 'Visiteur Web',
                'profile_name'  => 'Navigateur Web',
                'opt_in_status' => OptInStatus::OptedIn,
            ],
        );

        return $this->existingConversation($sessionId) ?? Conversation::create([
            'contact_id'          => $contact->id,
            'whatsapp_account_id' => null,
            'channel'             => 'web',
            'status'              => ConversationStatus::Open,
            'ai_enabled'          => true,
        ]);
    }

    private function existingConversation(string $sessionId): ?Conversation
    {
        $contact = Contact::query()->where('wa_id', 'web_'.$sessionId)->first();

        if ($contact === null) {
            return null;
        }

        return Conversation::query()
            ->where('contact_id', $contact->id)
            ->where('channel', 'web')
            ->whereNull('closed_at')
            ->first();
    }
}
