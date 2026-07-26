<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Data\WhatsApp\DeliveryStatus;
use App\Data\WhatsApp\InboundMessage;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use Carbon\CarbonImmutable;

/**
 * Traduit un payload Meta en objets métier.
 *
 * Le format Meta est profondément imbriqué (entry → changes → value → messages)
 * et un seul webhook peut transporter plusieurs messages, plusieurs statuts et
 * plusieurs numéros. Tout accès est défensif : un champ manquant produit une
 * valeur nulle, jamais une erreur — un webhook malformé ne doit pas provoquer
 * de retry infini côté Meta.
 */
class WebhookParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, InboundMessage>
     */
    public function extractMessages(array $payload): array
    {
        $messages = [];

        foreach ($this->values($payload) as $value) {
            $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

            if (! is_string($phoneNumberId)) {
                continue;
            }

            // Les noms de profil arrivent dans un tableau séparé, indexé par
            // wa_id : on le retourne pour pouvoir enrichir chaque message.
            $profiles = [];

            foreach ($value['contacts'] ?? [] as $contact) {
                if (isset($contact['wa_id'])) {
                    $profiles[$contact['wa_id']] = $contact['profile']['name'] ?? null;
                }
            }

            foreach ($value['messages'] ?? [] as $raw) {
                $message = $this->parseMessage($raw, $phoneNumberId, $profiles);

                if ($message !== null) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, DeliveryStatus>
     */
    public function extractStatuses(array $payload): array
    {
        $statuses = [];

        foreach ($this->values($payload) as $value) {
            foreach ($value['statuses'] ?? [] as $raw) {
                $wamid  = $raw['id'] ?? null;
                $status = MessageStatus::tryFrom((string) ($raw['status'] ?? ''));

                if (! is_string($wamid) || $status === null) {
                    continue;
                }

                $statuses[] = new DeliveryStatus(
                    wamid: $wamid,
                    status: $status,
                    timestamp: $this->timestamp($raw['timestamp'] ?? null),
                    recipientId: $raw['recipient_id'] ?? null,
                    error: $raw['errors'][0] ?? null,
                );
            }
        }

        return $statuses;
    }

    /** Identifiants de numéro présents dans le payload, pour résoudre le tenant. */
    public function extractPhoneNumberIds(array $payload): array
    {
        $ids = [];

        foreach ($this->values($payload) as $value) {
            $id = $value['metadata']['phone_number_id'] ?? null;

            if (is_string($id)) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, ?string>  $profiles
     */
    private function parseMessage(array $raw, string $phoneNumberId, array $profiles): ?InboundMessage
    {
        $wamid = $raw['id'] ?? null;
        $from  = $raw['from'] ?? null;

        if (! is_string($wamid) || ! is_string($from)) {
            return null;
        }

        $type = MessageType::fromMeta((string) ($raw['type'] ?? 'unsupported'));

        return new InboundMessage(
            wamid: $wamid,
            from: $from,
            phoneNumberId: $phoneNumberId,
            type: $type,
            body: $this->extractBody($raw, $type),
            timestamp: $this->timestamp($raw['timestamp'] ?? null),
            profileName: $profiles[$from] ?? null,
            media: $this->extractMedia($raw, $type),
            contextWamid: $raw['context']['id'] ?? null,
        );
    }

    /**
     * Texte du message, selon son type.
     *
     * Un clic sur un bouton ou une réponse de liste vaut réponse utilisateur :
     * on en extrait le libellé, sans quoi l'agent recevrait un message vide.
     */
    private function extractBody(array $raw, MessageType $type): ?string
    {
        return match ($type) {
            MessageType::Text     => $raw['text']['body'] ?? null,
            MessageType::Button   => $raw['button']['text'] ?? $raw['button']['payload'] ?? null,
            MessageType::Interactive => $raw['interactive']['button_reply']['title']
                ?? $raw['interactive']['list_reply']['title']
                ?? null,
            MessageType::Reaction => $raw['reaction']['emoji'] ?? null,
            MessageType::Location => $this->formatLocation($raw['location'] ?? []),
            // Les médias portent parfois une légende, qui est le vrai message.
            MessageType::Image, MessageType::Video, MessageType::Document
                => $raw[$type->value]['caption'] ?? null,
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function extractMedia(array $raw, MessageType $type): ?array
    {
        if (! $type->isMedia()) {
            return null;
        }

        $media = $raw[$type->value] ?? null;

        if (! is_array($media)) {
            return null;
        }

        return array_filter([
            'id'        => $media['id'] ?? null,
            'mime_type' => $media['mime_type'] ?? null,
            'sha256'    => $media['sha256'] ?? null,
            'filename'  => $media['filename'] ?? null,
            'caption'   => $media['caption'] ?? null,
        ], static fn ($v) => $v !== null);
    }

    private function formatLocation(array $location): ?string
    {
        if (! isset($location['latitude'], $location['longitude'])) {
            return null;
        }

        $name = $location['name'] ?? $location['address'] ?? null;

        return sprintf(
            '[Position partagée%s : %s, %s]',
            $name !== null ? " — {$name}" : '',
            $location['latitude'],
            $location['longitude'],
        );
    }

    /**
     * Sous-objets `value` du payload.
     *
     * @return array<int, array<string, mixed>>
     */
    private function values(array $payload): array
    {
        $values = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (is_array($change['value'] ?? null)) {
                    $values[] = $change['value'];
                }
            }
        }

        return $values;
    }

    /** Meta transmet des horodatages Unix en chaîne de caractères. */
    private function timestamp(mixed $value): CarbonImmutable
    {
        return is_numeric($value)
            ? CarbonImmutable::createFromTimestampUTC((int) $value)
            : CarbonImmutable::now();
    }
}
