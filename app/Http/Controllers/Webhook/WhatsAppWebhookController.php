<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Enums\IncidentLevel;
use App\Enums\WebhookEventStatus;
use App\Jobs\WhatsApp\ProcessWebhookEventJob;
use App\Models\Incident;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WebhookParser;
use App\Services\WhatsApp\WebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Point d'entrée des webhooks Meta.
 *
 * Contrainte structurante : Meta attend un 200 en quelques centaines de
 * millisecondes. Au-delà, il rejoue la requête et finit par dégrader la note
 * de qualité du numéro du client. Ce contrôleur ne fait donc que trois choses :
 * vérifier la signature, journaliser l'événement, mettre en file. Tout le
 * traitement métier se passe dans un job.
 */
class WhatsAppWebhookController
{
    public function __construct(
        private readonly WebhookSignatureVerifier $verifier,
        private readonly WebhookParser $parser,
    ) {}

    /**
     * Handshake de vérification, appelé par Meta à la déclaration de l'URL.
     */
    public function verify(Request $request, string $tenant): Response
    {
        $account = $this->resolveAccount($tenant);

        if ($account === null) {
            return response('Not found', 404);
        }

        $challenge = $this->verifier->verifyChallenge($request, $account->webhook_verify_token);

        if ($challenge === null) {
            Log::channel('whatsapp')->warning('Handshake de webhook refusé.', [
                'tenant_id' => $tenant,
                'ip'        => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        // Meta attend le challenge en texte brut, sans guillemets JSON.
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Réception des événements : messages entrants et statuts de livraison.
     */
    public function handle(Request $request, string $tenant): JsonResponse
    {
        $account = $this->resolveAccount($tenant);

        // Compte inconnu : 200 tout de même. Un 404 pousserait Meta à rejouer
        // indéfiniment un webhook que nous ne saurons jamais traiter.
        if ($account === null) {
            return response()->json(['status' => 'ignored'], 200);
        }

        if (! $this->verifier->verify($request, $account->app_secret)) {
            $this->reportInvalidSignature($request, $tenant);

            // Ici, en revanche, on refuse explicitement : une signature
            // invalide n'est jamais un webhook Meta légitime.
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();

        try {
            $this->recordAndDispatch($account->tenant_id, $payload);
        } catch (Throwable $e) {
            // Le traitement échoue : on l'enregistre mais on acquitte quand
            // même. Un 500 déclencherait un rejeu qui échouerait pareil.
            Log::channel('whatsapp')->error('Échec de la prise en charge d\'un webhook.', [
                'tenant_id' => $account->tenant_id,
                'error'     => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * Crée un WebhookEvent par élément du payload et met en file.
     *
     * Un même webhook peut transporter plusieurs messages et plusieurs statuts.
     * Chacun reçoit sa propre clé d'idempotence : un rejeu partiel de Meta ne
     * retraite que ce qui ne l'a pas encore été.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordAndDispatch(string $tenantId, array $payload): void
    {
        $units = [];

        foreach ($this->parser->extractMessages($payload) as $message) {
            $units[] = ['type' => 'message', 'reference' => $message->wamid, 'status' => null];
        }

        foreach ($this->parser->extractStatuses($payload) as $status) {
            $units[] = ['type' => 'status', 'reference' => $status->wamid, 'status' => $status->status->value];
        }

        // Payload reconnu mais sans contenu exploitable (mises à jour de
        // template, changements de qualité…) : tracé une fois, non traité.
        if ($units === []) {
            $units[] = [
                'type'      => 'other',
                'reference' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                'status'    => null,
            ];
        }

        foreach ($units as $unit) {
            $key = WebhookEvent::makeIdempotencyKey(
                $tenantId, $unit['type'], $unit['reference'], $unit['status'],
            );

            // firstOrCreate sur un index unique : deux livraisons simultanées
            // du même événement ne produisent qu'une seule ligne.
            $event = WebhookEvent::query()->firstOrCreate(
                ['idempotency_key' => $key],
                [
                    'tenant_id'       => $tenantId,
                    'provider'        => 'whatsapp',
                    'event_type'      => $unit['type'],
                    'signature_valid' => true,
                    'payload'         => $payload,
                    'status'          => WebhookEventStatus::Received,
                    'received_at'     => now(),
                ],
            );

            // Déjà traité lors d'une livraison précédente : rien à refaire.
            if (! $event->wasRecentlyCreated && $event->status->isTerminal()) {
                continue;
            }

            ProcessWebhookEventJob::dispatch($event->id, $tenantId)->onQueue('webhooks');
        }
    }

    /**
     * Le compte est résolu par le tenant de l'URL.
     *
     * Sans cloisonnement : à ce stade aucune identité n'est établie, c'est
     * précisément la signature vérifiée juste après qui prouvera la légitimité
     * de l'appel.
     */
    private function resolveAccount(string $tenantId): ?WhatsAppAccount
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenantId)) {
            return null;
        }

        $exists = Tenant::query()->whereKey($tenantId)->exists();

        if (! $exists) {
            return null;
        }

        return WhatsAppAccount::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('app_secret')
            ->first();
    }

    private function reportInvalidSignature(Request $request, string $tenantId): void
    {
        Log::channel('whatsapp')->warning('Signature de webhook invalide.', [
            'tenant_id'  => $tenantId,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Incident::record(
            tenantId: $tenantId,
            level: IncidentLevel::Critical,
            source: 'webhook',
            code: 'invalid_signature',
            title: 'Signature de webhook WhatsApp invalide',
            context: ['ip' => $request->ip()],
        );
    }
}
