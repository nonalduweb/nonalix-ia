<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\SetupChecklist;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Données partagées avec toutes les pages.
     *
     * Rien de sensible ici : ce tableau est sérialisé dans le HTML et visible
     * de l'utilisateur. Les permissions sont partagées pour piloter l'affichage,
     * mais l'autorisation réelle reste côté serveur, dans les policies — masquer
     * un bouton n'a jamais protégé une route.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // Closures OBLIGATOIRES ici : ce middleware appartient au groupe
            // `web` et s'exécute donc AVANT le middleware de route `tenant`.
            // Évaluer les rôles immédiatement les résoudrait dans le périmètre
            // plateforme au lieu de celui de l'entreprise, et l'interface
            // paraîtrait dépourvue de toute permission. Inertia résout ces
            // closures au moment de construire la réponse, une fois toute la
            // pile de middleware traversée.
            'auth' => fn () => [
                'user' => ($user = $request->user()) === null ? null : [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'email'          => $user->email,
                    'avatar_url'     => $user->avatar_url,
                    'is_super_admin' => $user->isSuperAdmin(),
                    'roles'          => $user->getRoleNames(),
                    'permissions'    => $user->getAllPermissions()->pluck('name'),
                ],
            ],

            'tenant' => fn () => ($tenant = app(TenantContext::class)->current()) === null ? null : [
                'id'     => $tenant->id,
                'name'   => $tenant->name,
                'status' => $tenant->status->value,
                'plan'   => $tenant->plan?->name,
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'whatsapp_connected' => $tenant->whatsappAccounts()->where('status', \App\Enums\WhatsAppAccountStatus::Connected->value)->exists(),

                // La boîte de réception n'est plus propre à WhatsApp depuis
                // l'ouverture du widget web. La conditionner au seul numéro
                // connecté privait de tout accès l'entreprise qui n'utilise
                // que le widget — alors qu'elle y recevait bien ses messages.
                'has_conversations' => $tenant->conversations()->exists(),
            ],

            // Bannière d'impersonation : l'opérateur doit toujours savoir
            // qu'il agit au nom de quelqu'un d'autre.
            'impersonating' => $request->session()->get('impersonation.tenant_name'),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                // `status` porte les messages d'étape (code envoyé, lien
                // renvoyé) : ils informent sans signaler l'aboutissement
                // d'une action, et n'ont donc pas le même rendu que `success`.
                'status'  => fn () => $request->session()->get('status'),
                // Affichés une seule fois, jamais relus depuis la base.
                'recoveryCodes' => fn () => $request->session()->get('recoveryCodes'),
            ],

            'domains' => [
                'app'   => config('nonalix.domains.app'),
                'admin' => config('nonalix.domains.admin'),
            ],

            // Avancement de la configuration, pour signaler dans les onglets ce
            // qui reste à faire.
            //
            // Calculé UNIQUEMENT sur les écrans de réglages : ces vérifications
            // font cinq requêtes, et les imposer au tableau de bord ou à la
            // messagerie les alourdirait sans rien apporter.
            'setup' => fn () => $request->is('settings*') && app(TenantContext::class)->has()
                ? app(SetupChecklist::class)->forCurrentTenant()
                : null,
        ]);
    }
}
