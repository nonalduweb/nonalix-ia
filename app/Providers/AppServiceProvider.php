<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton : le contexte de tenant doit être unique pour toute la
        // durée de la requête ou du job.
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
        $this->configureUrls();
    }

    /**
     * Réglages Eloquent stricts.
     *
     * Hors production uniquement : en développement et en test, une relation
     * non chargée ou un attribut inexistant doit exploser immédiatement. En
     * production, on préfère une page dégradée à une erreur 500 pour un
     * `$model->typo` oublié.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Empêche `Model::unguard()` de laisser passer une assignation de
        // masse involontaire, y compris en production.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }

    private function configureRateLimiting(): void
    {
        // API : par jeton, avec repli sur l'IP pour les requêtes non
        // authentifiées (erreurs 401 comprises).
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(120)->by($key);
        });

        // Webhooks : plafond large, par tenant. Meta peut livrer par rafales ;
        // le limiteur ne sert qu'à contenir un abus, pas à réguler Meta.
        RateLimiter::for('webhooks', function (Request $request) {
            $tenant = $request->route('tenant');

            return Limit::perMinute(600)->by(is_string($tenant) ? $tenant : $request->ip());
        });

        // Connexion : la clé combine e-mail et IP. Ne cibler que l'IP
        // permettrait de bloquer un utilisateur légitime derrière un NAT
        // partagé ; ne cibler que l'e-mail laisserait passer le bourrage
        // d'identifiants distribué.
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)
            ->by($request->user()?->id ?: $request->ip()));

        // Import de documents : coûteux en CPU et en appels d'embeddings.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(60)
            ->by($request->user()?->tenant_id ?: $request->ip()));

        // Vérification d'un code d'accès : c'est la seule porte d'entrée vers
        // la création d'un compte, donc la cible naturelle d'une recherche par
        // essais. Un code fait 12 caractères sur un alphabet de 32 ; ce plafond
        // rend l'exploration inatteignable en pratique.
        RateLimiter::for('access-code', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perHour(60)->by($request->ip()),
        ]);

        // Inscription : plus stricte encore, chaque tentative aboutie créant
        // une entreprise et consommant un code.
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perHour(10)->by($request->ip()),
        ]);

        // Réinitialisation : la clé combine adresse et IP, comme la connexion.
        // Sans le volet adresse, un attaquant pourrait inonder la boîte d'un
        // tiers depuis plusieurs IP.
        RateLimiter::for('password-reset', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(3)->by($email.'|'.$request->ip()),
                Limit::perHour(10)->by($request->ip()),
            ];
        });

        // Renvoi et validation du lien de confirmation d'adresse.
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(6)
            ->by($request->user()?->id ?: $request->ip()));

        // Demande d'accès : formulaire public, sans authentification. Le
        // plafond est bas — un prospect ne dépose qu'une demande, et une
        // rafale ne peut être qu'un robot ou une nuisance.
        RateLimiter::for('access-request', fn (Request $request) => [
            Limit::perMinute(2)->by($request->ip()),
            Limit::perDay(10)->by($request->ip()),
        ]);
    }

    private function configureUrls(): void
    {
        // Derrière un proxy TLS, Laravel doit générer des URL en https, sans
        // quoi les liens de réinitialisation de mot de passe partent en http.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
