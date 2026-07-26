<?php

declare(strict_types=1);

namespace App\Services\AI\Concerns;

use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client HTTP commun aux fournisseurs IA : timeouts, retries, classification
 * des erreurs.
 *
 * La politique de retry est volontairement centralisée : chaque fournisseur
 * a ses codes propres, mais la règle « réessayer le transitoire, échouer vite
 * sur le permanent » doit être identique partout.
 */
trait SendsHttpRequests
{
    protected function http(): PendingRequest
    {
        return Http::timeout((int) config('ai.http.timeout', 60))
            ->connectTimeout((int) config('ai.http.connect_timeout', 10))
            ->acceptJson()
            ->asJson();
    }

    /**
     * Exécute la requête avec backoff exponentiel et jitter.
     *
     * Le jitter n'est pas cosmétique : sans lui, tous les workers dont les
     * appels ont échoué en même temps réessaient en même temps et reproduisent
     * exactement la surcharge qui a causé le 429.
     *
     * @param  callable(): Response  $send
     *
     * @throws AiProviderException
     */
    protected function sendWithRetries(callable $send, string $provider, ?string $model = null): Response
    {
        $maxAttempts = max(1, (int) config('ai.http.max_retries', 3));
        $baseMs      = (int) config('ai.http.retry_base_ms', 500);

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $send();

                if ($response->successful()) {
                    return $response;
                }

                $exception = $this->exceptionFromResponse($response, $provider, $model);

                if (! $exception->retryable || $attempt === $maxAttempts) {
                    throw $exception;
                }

                $lastException = $exception;
            } catch (AiProviderException $e) {
                throw $e;
            } catch (Throwable $e) {
                // Panne réseau, DNS, timeout : transitoire par nature.
                $lastException = AiProviderException::retryable(
                    $e->getMessage(), $provider, $model, previous: $e,
                );

                if ($attempt === $maxAttempts) {
                    throw $lastException;
                }
            }

            usleep($this->backoffMicroseconds($baseMs, $attempt));
        }

        throw $lastException ?? AiProviderException::permanent(
            'Échec de l\'appel au fournisseur.', $provider, $model,
        );
    }

    private function backoffMicroseconds(int $baseMs, int $attempt): int
    {
        $delayMs = $baseMs * (2 ** ($attempt - 1));
        $jitter  = random_int(0, (int) ($delayMs * 0.3));

        return ($delayMs + $jitter) * 1000;
    }

    /**
     * Traduit une réponse en erreur, en distinguant transitoire et définitif.
     *
     * 429 et 5xx : transitoires. 401/403 (clé invalide), 400 (requête
     * malformée), 404 (modèle inconnu) : définitifs — réessayer ne ferait que
     * retarder l'affichage de l'erreur.
     */
    protected function exceptionFromResponse(Response $response, string $provider, ?string $model): AiProviderException
    {
        $status = $response->status();
        $body   = $response->json() ?? [];

        $message = $body['error']['message']
            ?? $body['error']['status']
            ?? $body['message']
            ?? $response->body();

        $code = $body['error']['type'] ?? $body['error']['code'] ?? null;

        $retryable = $status === 429 || $status >= 500 || $status === 408;

        return $retryable
            ? AiProviderException::retryable((string) $message, $provider, $model, $status, is_string($code) ? $code : null)
            : AiProviderException::permanent((string) $message, $provider, $model, $status, is_string($code) ? $code : null);
    }
}
