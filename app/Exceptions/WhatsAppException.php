<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Échec d'un appel à l'API Meta Cloud.
 *
 * Les codes d'erreur Meta sont conservés bruts : ils sont la seule information
 * exploitable pour arbitrer un litige de livraison, et les traduire ferait
 * perdre de l'information.
 */
class WhatsAppException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $metaCode = null,
        public readonly ?int $metaSubcode = null,
        public readonly ?string $metaType = null,
        public readonly ?int $statusCode = null,
        public readonly bool $retryable = false,
        /** @var array<string, mixed> */
        public readonly array $payload = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    /**
     * Construit l'exception depuis une réponse d'erreur Meta.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(array $body, int $status): self
    {
        $error = $body['error'] ?? [];
        $code  = isset($error['code']) ? (int) $error['code'] : null;

        return new self(
            message:     $error['message'] ?? 'Erreur inconnue de l\'API WhatsApp.',
            metaCode:    $code,
            metaSubcode: isset($error['error_subcode']) ? (int) $error['error_subcode'] : null,
            metaType:    $error['type'] ?? null,
            statusCode:  $status,
            retryable:   self::isRetryable($code, $status),
            payload:     $error,
        );
    }

    /**
     * Codes Meta qu'il est pertinent de réessayer.
     *
     *   4   / 613 : limite de débit atteinte
     *   80007     : quota de l'application dépassé
     *   131_016   : service temporairement indisponible
     *   131_026   : message non délivrable dans l'immédiat
     *   368       : compte temporairement restreint
     */
    private static function isRetryable(?int $code, int $status): bool
    {
        if ($status >= 500) {
            return true;
        }

        return in_array($code, [4, 368, 613, 80_007, 131_016, 131_026], true);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'meta_code'    => $this->metaCode,
            'meta_subcode' => $this->metaSubcode,
            'meta_type'    => $this->metaType,
            'status_code'  => $this->statusCode,
            'retryable'    => $this->retryable,
        ];
    }
}
