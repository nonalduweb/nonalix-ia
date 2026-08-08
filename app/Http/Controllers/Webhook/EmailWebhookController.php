<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\OptInStatus;
use App\Enums\SenderType;
use App\Events\ConversationUpdated;
use App\Events\MessageCreated;
use App\Jobs\AI\GenerateAgentReplyJob;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Réception du courrier entrant, redirigé par l'entreprise.
 *
 * L'entreprise ne déclare pas son adresse d'entrée : la plateforme la lui
 * frappe (`in-<jeton>@…`) et c'est elle, et elle seule, qui désigne le
 * destinataire. Résoudre l'entreprise en comparant à un champ saisi
 * librement permettait à qui voulait de recevoir le courrier d'un concurrent.
 *
 * Le point d'entrée est protégé par un secret porté dans l'URL, vérifié par le
 * middleware `email-webhook`. Sans lui, n'importe qui pouvait faire naître des
 * conversations et déclencher des générations facturées au client.
 */
class EmailWebhookController
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $sender    = (string) ($request->input('sender') ?: $request->input('from') ?: '');
        $recipient = (string) ($request->input('recipient') ?: $request->input('to') ?: '');
        $subject   = (string) ($request->input('subject') ?: 'Sans sujet');
        $body      = (string) ($request->input('body') ?: $request->input('body-plain') ?: '');

        if ($sender === '' || $recipient === '') {
            return response()->json(['error' => 'Champs sender ou recipient manquants.'], 422);
        }

        $senderEmail = $this->extractEmail($sender);
        $tenant      = $this->resolveTenant($recipient);

        if ($tenant === null) {
            // 200 volontaire : un fournisseur d'entrée qui reçoit une erreur
            // réessaie en boucle. L'adresse est inconnue, il n'y a rien à
            // réessayer.
            Log::channel('ai')->info('Courrier entrant sans entreprise correspondante.', [
                'recipient' => $recipient,
            ]);

            return response()->json(['status' => 'ignored']);
        }

        // Notre propre expéditeur qui revient : une redirection mal réglée
        // peut renvoyer nos réponses vers nous. Sans ce garde, chaque réponse
        // en déclencherait une nouvelle, indéfiniment.
        if ($this->isOwnAddress($senderEmail)) {
            return response()->json(['status' => 'ignored']);
        }

        // Message sonde : il prouve que la redirection fonctionne, et ne doit
        // surtout pas devenir une conversation.
        if ($this->completesProbe($tenant, $subject.' '.$body)) {
            return response()->json(['status' => 'verified']);
        }

        return $this->context->runAs($tenant, function () use ($tenant, $senderEmail, $subject, $body) {
            $contact = Contact::firstOrCreate(
                ['wa_id' => 'email_'.$senderEmail],
                [
                    'name'          => 'Client E-mail',
                    'profile_name'  => $senderEmail,
                    'email'         => $senderEmail,
                    'opt_in_status' => OptInStatus::OptedIn,
                ],
            );

            $conversation = Conversation::query()
                ->where('contact_id', $contact->id)
                ->where('channel', 'email')
                ->whereNull('closed_at')
                ->first();

            if ($conversation === null) {
                // Aucun compte WhatsApp : une conversation e-mail n'en a pas
                // besoin, la colonne est nullable depuis la migration 000028.
                // Ne JAMAIS emprunter celui d'un autre tenant pour satisfaire
                // la clé étrangère — c'est une fuite de données.
                $conversation = Conversation::create([
                    'contact_id'          => $contact->id,
                    'whatsapp_account_id' => null,
                    'channel'             => 'email',
                    'status'              => ConversationStatus::Open,
                    'ai_enabled'          => true,
                ]);
            }

            // Le sujet fait partie de la demande : l'agent doit le lire.
            $messageBody = 'Sujet : '.$subject."\n\n".$body;

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction'       => MessageDirection::Inbound,
                'sender_type'     => SenderType::Contact,
                'type'            => MessageType::Text,
                'body'            => $messageBody,
                'status'          => MessageStatus::Delivered,
            ]);

            $conversation->forceFill([
                'last_message_at' => now(),
                'last_inbound_at' => now(),
            ])->save();

            MessageCreated::dispatch($message);
            ConversationUpdated::dispatch($conversation);

            GenerateAgentReplyJob::dispatch($tenant->id, $conversation->id, $messageBody);

            return response()->json(['status' => 'queued']);
        });
    }

    /**
     * Entreprise désignée par l'adresse d'entrée que nous avons frappée.
     *
     * Le destinataire peut contenir plusieurs adresses, ou l'adresse d'origine
     * du client à côté de la nôtre : on cherche le motif, où qu'il soit.
     */
    private function resolveTenant(string $recipient): ?Tenant
    {
        if (preg_match('/in-([a-z0-9]{24})@/i', $recipient, $matches) !== 1) {
            return null;
        }

        return Tenant::query()
            ->where('inbound_email_token', mb_strtolower($matches[1]))
            ->first();
    }

    /** L'adresse est-elle l'une des nôtres ? */
    private function isOwnAddress(string $email): bool
    {
        $domain = (string) config('nonalix.email.inbound_domain');

        return $domain !== '' && str_ends_with($email, '@'.$domain);
    }

    /**
     * Le message porte-t-il le code sonde attendu ?
     *
     * C'est la seule preuve acceptée que la redirection fonctionne : elle
     * vient du courrier lui-même, pas d'une déclaration du client.
     */
    private function completesProbe(Tenant $tenant, string $haystack): bool
    {
        $code = $tenant->email_probe_code;

        if ($code === null || ! str_contains($haystack, $code)) {
            return false;
        }

        $tenant->forceFill([
            'email_forward_verified_at' => now(),
            'email_probe_code'          => null,
        ])->save();

        return true;
    }

    private function extractEmail(string $address): string
    {
        if (preg_match('/<([^>]+)>/', $address, $matches) === 1) {
            return mb_strtolower(trim($matches[1]));
        }

        return mb_strtolower(trim($address));
    }
}
