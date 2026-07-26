<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enrichit tous les logs de la requête avec un contexte commun.
 *
 * Sans `request_id` et `tenant_id` dans chaque ligne, diagnostiquer un
 * incident dans une plateforme partagée revient à chercher à l'aveugle. Le
 * `request_id` vient de Nginx quand il est présent, ce qui permet de relier
 * les logs du proxy à ceux de l'application.
 */
class InjectRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid7();

        Log::shareContext([
            'request_id' => $requestId,
            'ip'         => $request->ip(),
            'method'     => $request->method(),
            'path'       => $request->path(),
            'user_id'    => $request->user()?->id,
            'tenant_id'  => $request->user()?->tenant_id,
        ]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
