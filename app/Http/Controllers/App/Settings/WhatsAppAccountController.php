<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Enums\WhatsAppAccountStatus;
use App\Exceptions\WhatsAppException;
use App\Models\WhatsAppAccount;
use App\Services\Audit\AuditLogger;
use App\Support\Redaction;
use App\Services\Tenancy\TenantContext;
use App\Services\WhatsApp\CloudApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connexion manuelle d'un numéro WhatsApp Business (Phase 1).
 *
 * Le client colle les identifiants de sa propre application Meta. L'Embedded
 * Signup, qui automatiserait cette étape, exige le statut Tech Provider chez
 * Meta et arrive en Phase 4.
 */
class WhatsAppAccountController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $context,
    ) {}

    public function edit(Request $request): Response
    {
        $account = WhatsAppAccount::query()->first();

        abort_unless(
            $account === null || $request->user()->can('view', $account),
            403,
        );

        return Inertia::render('Settings/WhatsApp', [
            // Le jeton d'accès et l'app secret ne sont JAMAIS renvoyés au
            // navigateur, même masqués : on n'expose que l'information
            // « une valeur est enregistrée ».
            //
            // Le webhook_verify_token fait exception : il est indispensable au
            // client pour déclarer l'URL de callback dans la console Meta, et
            // il ne donne aucun pouvoir par lui-même — il ne sert qu'au
            // handshake de vérification, et Meta le connaît déjà.
            'account' => $account === null ? null : [
                'id'                   => $account->id,
                'waba_id'              => $account->waba_id,
                'phone_number_id'      => $account->phone_number_id,
                'display_phone_number' => $account->display_phone_number,
                'verified_name'        => $account->verified_name,
                'quality_rating'       => $account->quality_rating,
                'status'               => $account->status->value,
                'status_label'         => $account->status->label(),
                'connected_at'         => $account->connected_at?->toIso8601String(),
                'last_error'           => $account->last_error,
                'has_access_token'     => $account->access_token !== null,
                'has_app_secret'       => $account->app_secret !== null,
                'webhook_url'          => $account->webhookUrl(),
                'webhook_verify_token' => $account->webhook_verify_token,
            ],
            'webhookUrl' => sprintf(
                'https://%s/webhooks/whatsapp/%s',
                config('nonalix.domains.api'),
                $this->context->id(),
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = WhatsAppAccount::query()->first() ?? new WhatsAppAccount;

        abort_unless(
            $account->exists
                ? $request->user()->can('update', $account)
                : $request->user()->hasAnyRole(['owner', 'admin']),
            403,
        );

        $validated = $request->validate([
            'waba_id'         => ['required', 'string', 'max:40'],
            'phone_number_id' => ['required', 'string', 'max:40'],
            // Les secrets sont facultatifs à la mise à jour : un champ laissé
            // vide conserve la valeur existante, ce qui évite d'obliger le
            // client à recoller son jeton à chaque modification.
            'access_token'    => [$account->exists ? 'nullable' : 'required', 'string', 'max:1000'],
            'app_secret'      => [$account->exists ? 'nullable' : 'required', 'string', 'max:255'],
        ]);

        // Unicité globale du phone_number_id : sans ce contrôle, deux clients
        // pourraient revendiquer le même numéro et les webhooks deviendraient
        // ambigus. Le message reste volontairement vague — il ne doit pas
        // révéler qu'un autre client utilise ce numéro.
        $taken = WhatsAppAccount::withoutTenantScope()
            ->where('phone_number_id', $validated['phone_number_id'])
            ->when($account->exists, fn ($q) => $q->whereKeyNot($account->id))
            ->exists();

        if ($taken) {
            return back()->withErrors([
                'phone_number_id' => 'Ce numéro est déjà connecté à la plateforme.',
            ]);
        }

        $account->fill([
            'waba_id'         => $validated['waba_id'],
            'phone_number_id' => $validated['phone_number_id'],
            'status'          => WhatsAppAccountStatus::Pending,
        ]);

        foreach (['access_token', 'app_secret'] as $secret) {
            if (! empty($validated[$secret])) {
                $account->{$secret} = $validated[$secret];
            }
        }

        // Généré par la plateforme et affiché une fois : le client le colle
        // dans la console Meta pour valider l'URL de callback.
        $account->webhook_verify_token ??= Str::random(48);

        $account->save();

        // `changes` passe par la rédaction : les jetons ne doivent pas
        // atterrir dans le journal d'audit.
        $this->audit->logUpdate('whatsapp.account_updated', $account);

        return back()->with('success', 'Identifiants enregistrés. Testez la connexion pour les valider.');
    }

    /** Appel réel à Meta pour valider les identifiants. */
    public function test(Request $request): RedirectResponse
    {
        $account = WhatsAppAccount::query()->firstOrFail();

        abort_unless($request->user()->can('test', $account), 403);

        try {
            $details = CloudApiClient::for($account)->fetchPhoneNumberDetails();

            $account->forceFill([
                'verified_name'        => $details['verified_name'] ?? null,
                'display_phone_number' => $details['display_phone_number'] ?? null,
                'quality_rating'       => $details['quality_rating'] ?? null,
                'status'               => WhatsAppAccountStatus::Connected,
                'connected_at'         => $account->connected_at ?? now(),
                'last_verified_at'     => now(),
                'last_error'           => null,
            ])->save();

            $this->audit->log('whatsapp.connection_verified', $account);

            return back()->with('success', sprintf(
                'Connexion validée : %s (%s).',
                $account->verified_name ?? 'numéro vérifié',
                $account->display_phone_number ?? '',
            ));
        } catch (WhatsAppException $e) {
            // Meta reprend le jeton dans ses messages d'erreur (« Malformed
            // access token EAAG… »). `last_error` n'étant pas chiffré, le
            // stocker tel quel remettrait en clair, en base et à l'écran, un
            // secret que la colonne voisine protège soigneusement.
            $message = Redaction::fromText($e->getMessage(), [
                $account->access_token,
                $account->app_secret,
                $account->webhook_verify_token,
            ]);

            $account->forceFill([
                'status'     => WhatsAppAccountStatus::Error,
                'last_error' => mb_substr($message, 0, 1000),
            ])->save();

            return back()->withErrors([
                'connection' => 'Échec de la connexion à Meta : '.$message,
            ]);
        }
    }

    /** Synchronisation des modèles de messages approuvés. */
    public function syncTemplates(Request $request): RedirectResponse
    {
        $account = WhatsAppAccount::query()->firstOrFail();

        abort_unless($request->user()->can('update', $account), 403);

        try {
            $templates = CloudApiClient::for($account)->fetchTemplates();
        } catch (WhatsAppException $e) {
            return back()->withErrors(['templates' => $e->getMessage()]);
        }

        foreach ($templates as $template) {
            \App\Models\MessageTemplate::query()->updateOrCreate(
                [
                    'name'     => $template['name'],
                    'language' => $template['language'],
                ],
                [
                    'whatsapp_account_id' => $account->id,
                    'meta_template_id'    => $template['id'] ?? null,
                    'category'            => mb_strtolower((string) ($template['category'] ?? 'utility')),
                    'status'              => mb_strtolower((string) ($template['status'] ?? 'pending')),
                    'components'          => $template['components'] ?? [],
                    'rejected_reason'     => $template['rejected_reason'] ?? null,
                    'synced_at'           => now(),
                ],
            );
        }

        return back()->with('success', count($templates).' modèle(s) synchronisé(s).');
    }
}
