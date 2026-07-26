<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Échec d'un fournisseur IA.
 *
 * `retryable` distingue ce qui mérite une nouvelle tentative (429, 5xx, réseau)
 * de ce qui ne s'arrangera pas tout seul (clé invalide, modèle inexistant,
 * requête malformée) : réessayer 3 fois avec une clé révoquée ne fait que
 * retarder l'affichage de l'erreur au client.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?string $model = null,
        public readonly ?int $statusCode = null,
        public readonly bool $retryable = false,
        public readonly ?string $providerCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public static function retryable(
        string $message,
        string $provider,
        ?string $model = null,
        ?int $statusCode = null,
        ?string $providerCode = null,
        ?Throwable $previous = null,
    ): self {
        return new self($message, $provider, $model, $statusCode, true, $providerCode, $previous);
    }

    public static function permanent(
        string $message,
        string $provider,
        ?string $model = null,
        ?int $statusCode = null,
        ?string $providerCode = null,
        ?Throwable $previous = null,
    ): self {
        return new self($message, $provider, $model, $statusCode, false, $providerCode, $previous);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'provider'      => $this->provider,
            'model'         => $this->model,
            'status_code'   => $this->statusCode,
            'provider_code' => $this->providerCode,
            'retryable'     => $this->retryable,
        ];
    }
}
