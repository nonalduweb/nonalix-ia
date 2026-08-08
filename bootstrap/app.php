<?php

use App\Http\Middleware\EnforceTenantQuota;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTwoFactorIsConfirmed;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InjectRequestContext;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        using: function () {
            $domains = config('nonalix.domains');

            // Point de santé, enregistré ici et non via le paramètre `health`
            // de withRouting() : dès qu'une closure `using` est fournie,
            // Laravel n'enregistre plus les routes par défaut, health comprise.
            // Volontairement sans contrainte de domaine — sondes des
            // équilibreurs de charge et HEALTHCHECK Docker.
            Route::get('/up', function () {
                Event::dispatch(new DiagnosingHealth);

                return response('OK', 200, ['Content-Type' => 'text/plain']);
            })->name('health');

            // ---- Site commercial : nonalixia.com ---------------------------
            Route::domain($domains['marketing'])
                ->middleware('web')
                ->group(base_path('routes/marketing.php'));

            // ---- Widget de chat public : app.nonalixia.com/widget/* --------
            // Déclaré AVANT l'espace client : ces routes sont anonymes et
            // inter-origines, elles ne doivent porter ni session ni CSRF.
            Route::domain($domains['app'])
                ->middleware('widget')
                ->group(base_path('routes/widget.php'));

            // ---- Espace client : app.nonalixia.com -------------------------
            Route::domain($domains['app'])
                ->middleware('web')
                ->group(base_path('routes/app.php'));

            // ---- Administration NONALIX : admin.nonalixia.com --------------
            Route::domain($domains['admin'])
                ->middleware('web')
                ->group(base_path('routes/admin.php'));

            // ---- Webhooks entrants : api.nonalixia.com ---------------------
            // Déclarés AVANT l'API : ils ne portent ni session ni Sanctum,
            // uniquement une vérification de signature.
            Route::domain($domains['api'])
                ->middleware('webhooks')
                ->group(base_path('routes/webhooks.php'));

            // ---- API publique : api.nonalixia.com --------------------------
            Route::domain($domains['api'])
                ->middleware('api')
                ->prefix('v1')
                ->as('api.v1.')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: []);

        $middleware->web(append: [
            InjectRequestContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(prepend: [
            InjectRequestContext::class,
        ]);

        // Pile dédiée aux webhooks : ni session, ni CSRF, ni Sanctum.
        // L'authenticité est prouvée par la signature HMAC de l'émetteur.
        $middleware->group('webhooks', [
            InjectRequestContext::class,
            'throttle:webhooks',
        ]);

        // Pile dédiée au widget de chat public. Sans session : le widget
        // n'envoie aucun cookie, et laisser StartSession ici ferait naître une
        // session jetable à chaque sondage du navigateur (toutes les 3 s).
        // Le throttle n'est pas cosmétique : ces routes créent des lignes en
        // base et déclenchent des générations facturées.
        $middleware->group('widget', [
            InjectRequestContext::class,
            'throttle:widget',
        ]);

        // ORDRE CRITIQUE : SubstituteBindings appartient au groupe `web` et
        // s'exécuterait donc AVANT le middleware de route `tenant`. Le route
        // model binding interrogerait alors des modèles cloisonnés sans tenant
        // en contexte, ce qui fait lever le TenantScope — toute route liée à
        // une ressource de tenant tomberait en erreur.
        //
        // La liste de priorité de Laravel trie l'intégralité de la pile d'une
        // route, groupes compris : y insérer ResolveTenant avant
        // SubstituteBindings rétablit le bon ordre.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: ResolveTenant::class,
        );

        $middleware->alias([
            'email-webhook' => \App\Http\Middleware\VerifyEmailWebhookSecret::class,
            'tenant'      => ResolveTenant::class,
            'super-admin' => EnsureSuperAdmin::class,
            '2fa'         => EnsureTwoFactorIsConfirmed::class,
            'quota'       => EnforceTenantQuota::class,
            'role'        => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'  => Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // Les webhooks Meta n'ont pas de jeton CSRF (exclusion redondante avec
        // le groupe dédié, conservée en défense en profondeur).
        //
        // Le widget de chat s'exécute sur le site du client, sur une origine
        // tierce : il n'a ni session ni jeton CSRF à présenter. Son API est
        // volontairement anonyme et ne fait qu'ouvrir une conversation pour un
        // identifiant de session opaque — aucune donnée existante n'est
        // accessible sans connaître cet identifiant.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'widget/*',
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Les identifiants et jetons ne doivent jamais atterrir dans un rapport.
        $exceptions->dontFlash([
            'current_password', 'password', 'password_confirmation',
            'access_token', 'app_secret', 'api_key', 'two_factor_code',
        ]);

        $exceptions->render(function (App\Exceptions\TenantMismatchException $e) {
            report($e);

            return response()->json(['message' => 'Ressource introuvable.'], 404);
        });

        $exceptions->render(function (App\Exceptions\QuotaExceededException $e, $request) {
            return $request->expectsJson()
                ? response()->json([
                    'message' => $e->getMessage(),
                    'metric'  => $e->metric,
                    'limit'   => $e->limit,
                ], 429)
                : back()->withErrors(['quota' => $e->getMessage()]);
        });
    })
    ->create();
