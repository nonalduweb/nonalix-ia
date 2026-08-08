<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Exceptions\WhatsAppException;
use App\Models\WhatsAppAccount;
use App\Support\Redaction;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Seul point de sortie HTTP vers l'API Meta Cloud.
 *
 * Centraliser les appels garantit que le throttling, les retries, la
 * journalisation et la rédaction des secrets s'appliquent partout de la même
 * façon — un appel Guzzle isolé ailleurs dans le code contournerait tout cela.
 */
class CloudApiClient
{
    public function __construct(private readonly WhatsAppAccount $account) {}

    public static function for(WhatsAppAccount $account): self
    {
        return new self($account);
    }

    /**
     * Envoie un message texte.
     *
     * @return string  le wamid attribué par Meta
     *
     * @throws WhatsAppException
     */
    public function sendText(string $to, string $body, ?string $replyToWamid = null): string
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'body' => mb_substr($body, 0, 4096),
                // Désactivé : une prévisualisation de lien inattendue peut
                // exposer une URL interne mentionnée dans une réponse.
                'preview_url' => false,
            ],
        ];

        if ($replyToWamid !== null) {
            $payload['context'] = ['message_id' => $replyToWamid];
        }

        return $this->extractWamid($this->post('messages', $payload));
    }

    /**
     * Envoie un template approuvé — seul type de message autorisé hors de la
     * fenêtre de service de 24 h.
     *
     * @param  array<int, string>  $bodyParameters
     *
     * @throws WhatsAppException
     */
    public function sendTemplate(string $to, string $templateName, string $language, array $bodyParameters = []): string
    {
        $components = [];

        if ($bodyParameters !== []) {
            $components[] = [
                'type'       => 'body',
                'parameters' => array_map(
                    static fn (string $value) => ['type' => 'text', 'text' => $value],
                    array_values($bodyParameters),
                ),
            ];
        }

        return $this->extractWamid($this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => array_filter([
                'name'       => $templateName,
                'language'   => ['code' => $language],
                'components' => $components === [] ? null : $components,
            ]),
        ]));
    }

    /** Marque un message entrant comme lu (double coche bleue côté contact). */
    public function markAsRead(string $wamid): void
    {
        $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $wamid,
        ]);
    }

    /**
     * Vérifie les identifiants et récupère les informations du numéro.
     *
     * Utilisé par le bouton « Tester la connexion » : c'est le seul moyen de
     * dire au client que son jeton est valide avant qu'un vrai message n'échoue.
     *
     * @return array<string, mixed>
     *
     * @throws WhatsAppException
     */
    public function fetchPhoneNumberDetails(): array
    {
        $response = $this->request()->get($this->url($this->account->phone_number_id), [
            'fields' => 'verified_name,display_phone_number,quality_rating,throughput,code_verification_status',
        ]);

        return $this->handle($response);
    }

    /**
     * Liste les templates du compte WhatsApp Business.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws WhatsAppException
     */
    public function fetchTemplates(int $limit = 100): array
    {
        if ($this->account->waba_id === null) {
            throw new WhatsAppException('Aucun WABA ID n\'est renseigné pour ce compte.');
        }

        $response = $this->request()->get($this->url($this->account->waba_id.'/message_templates'), [
            'limit'  => $limit,
            'fields' => 'id,name,language,category,status,components,rejected_reason',
        ]);

        return $this->handle($response)['data'] ?? [];
    }

    /** Récupère l'URL temporaire d'un média entrant. */
    public function fetchMediaUrl(string $mediaId): ?string
    {
        $response = $this->request()->get($this->url($mediaId));

        return $this->handle($response)['url'] ?? null;
    }

    /**
     * Télécharge les octets d'un média entrant.
     *
     * L'URL rendue par Meta est temporaire ET exige le même jeton porteur que
     * le reste de l'API : la récupérer sans en-tête d'authentification échoue.
     *
     * @throws WhatsAppException
     */
    public function downloadMedia(string $mediaId): ?string
    {
        $url = $this->fetchMediaUrl($mediaId);

        if ($url === null) {
            return null;
        }

        try {
            $response = $this->request()->timeout(60)->get($url);
        } catch (Throwable $e) {
            throw new WhatsAppException(
                'Téléchargement du média impossible : '
                    .Redaction::fromText($e->getMessage(), $this->secrets()),
                retryable: true,
                previous: $e,
            );
        }

        return $response->successful() ? $response->body() : null;
    }

    /**
     * Téléverse un média et rend son identifiant Meta.
     *
     * Étape obligatoire avant tout envoi de note vocale : on n'envoie pas des
     * octets à un contact, on envoie une référence.
     *
     * @throws WhatsAppException
     */
    public function uploadMedia(string $bytes, string $filename, string $mimeType): string
    {
        $url = $this->url($this->account->phone_number_id.'/media');

        try {
            $response = $this->request()
                ->timeout(60)
                ->attach('file', $bytes, $filename, ['Content-Type' => $mimeType])
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'type'              => $mimeType,
                ]);
        } catch (Throwable $e) {
            throw new WhatsAppException(
                'Téléversement du média impossible : '
                    .Redaction::fromText($e->getMessage(), $this->secrets()),
                retryable: true,
                previous: $e,
            );
        }

        $id = $this->handle($response)['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new WhatsAppException('WhatsApp n\'a pas rendu d\'identifiant de média.', retryable: true);
        }

        return $id;
    }

    /**
     * Envoie une note vocale.
     *
     * `voice: true` est ce qui distingue une VRAIE note vocale d'une pièce
     * jointe audio. Sans lui, le contact reçoit un fichier à télécharger au
     * lieu d'un message qu'il écoute d'un geste — ce n'est pas la même chose.
     *
     * @return string  le wamid attribué par Meta
     *
     * @throws WhatsAppException
     */
    public function sendVoiceNote(string $to, string $mediaId, ?string $replyToWamid = null): string
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'audio',
            'audio'             => ['id' => $mediaId, 'voice' => true],
        ];

        if ($replyToWamid !== null) {
            $payload['context'] = ['message_id' => $replyToWamid];
        }

        $data = $this->post('messages', $payload);

        return $data['messages'][0]['id'] ?? '';
    }

    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws WhatsAppException
     */
    private function post(string $endpoint, array $payload): array
    {
        $url = $this->url($this->account->phone_number_id.'/'.$endpoint);

        try {
            $response = $this->request()->post($url, $payload);
        } catch (Throwable $e) {
            // Une exception de transport peut reprendre l'en-tête
            // Authorization, donc le jeton lui-même.
            throw new WhatsAppException(
                'Appel à l\'API WhatsApp impossible : '
                    .Redaction::fromText($e->getMessage(), $this->secrets()),
                retryable: true,
                previous: $e,
            );
        }

        $result = $this->handle($response, $payload);

        Log::channel('whatsapp')->info('Message WhatsApp envoyé.', [
            'tenant_id'       => $this->account->tenant_id,
            'phone_number_id' => $this->account->phone_number_id,
            'to'              => Redaction::phone($payload['to'] ?? null),
            'type'            => $payload['type'] ?? null,
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @return array<string, mixed>
     *
     * @throws WhatsAppException
     */
    private function handle(Response $response, array $requestPayload = []): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        // Meta reprend le jeton dans ses messages (« Malformed access token
        // EAAG… »). Le masquer ICI, avant même que l'exception n'existe :
        // sinon chaque consommateur en aval — incidents, journaux de webhook,
        // last_error — devrait y penser, et l'un d'eux oubliera.
        $body = Redaction::fromArray($response->json() ?? [], $this->secrets());

        $exception = WhatsAppException::fromResponse($body, $response->status());

        Log::channel('whatsapp')->error('Erreur de l\'API WhatsApp.', array_merge($exception->context(), [
            'tenant_id' => $this->account->tenant_id,
            'to'        => Redaction::phone($requestPayload['to'] ?? null),
            'message'   => $exception->getMessage(),
        ]));

        throw $exception;
    }

    /**
     * Valeurs à faire disparaître de tout message sortant.
     *
     * @return array<int, string|null>
     */
    private function secrets(): array
    {
        return [
            $this->account->access_token,
            $this->account->app_secret,
            $this->account->webhook_verify_token,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) $this->account->access_token)
            ->timeout((int) config('whatsapp.http.timeout', 15))
            ->connectTimeout((int) config('whatsapp.http.connect_timeout', 5))
            ->acceptJson()
            ->asJson();
    }

    private function url(string $path): string
    {
        return sprintf(
            '%s/%s/%s',
            rtrim((string) config('whatsapp.base_url'), '/'),
            config('whatsapp.api_version'),
            ltrim($path, '/'),
        );
    }

    /** @param array<string, mixed> $body */
    private function extractWamid(array $body): string
    {
        $wamid = $body['messages'][0]['id'] ?? null;

        if (! is_string($wamid)) {
            throw new WhatsAppException(
                'Réponse Meta sans identifiant de message : impossible de suivre la livraison.',
                payload: $body,
            );
        }

        return $wamid;
    }
}
