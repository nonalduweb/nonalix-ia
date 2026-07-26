<?php

declare(strict_types=1);

namespace App\Jobs\WhatsApp;

use App\Enums\MessageStatus;
use App\Enums\WebhookEventStatus;
use App\Events\MessageStatusUpdated;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\InboundMessageHandler;
use App\Services\WhatsApp\WebhookParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Traitement asynchrone d'un événement Meta.
 *
 * Rejouable sans effet de bord : les messages sont dédupliqués par `wamid`,
 * les statuts ne progressent que dans un sens. Relancer ce job dix fois
 * produit exactement le même état final qu'une seule exécution.
 */
class ProcessWebhookEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RunsInTenantContext;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    /** Backoff progressif : une panne passagère se résorbe rarement en 1 s. */
    public array $backoff = [5, 15, 60, 300];

    public function __construct(
        public readonly string $webhookEventId,
        public readonly string $tenantId,
    ) {}

    /** Évite qu'un même événement soit traité par deux workers en parallèle. */
    public function uniqueId(): string
    {
        return 'webhook-event:'.$this->webhookEventId;
    }

    public function handle(WebhookParser $parser, InboundMessageHandler $handler): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null || $event->status->isTerminal()) {
            return;
        }

        $this->withTenant($this->tenantId, function ($tenant) use ($event, $parser, $handler) {
            $payload = $event->payload ?? [];

            foreach ($parser->extractMessages($payload) as $inbound) {
                $account = WhatsAppAccount::query()
                    ->where('phone_number_id', $inbound->phoneNumberId)
                    ->first();

                // Le numéro ne correspond à aucun compte de CE tenant : le
                // payload a été livré sur la mauvaise URL. On ignore plutôt
                // que d'écrire dans le mauvais espace client.
                if ($account === null) {
                    Log::channel('whatsapp')->warning('Numéro inconnu pour ce tenant.', [
                        'tenant_id'       => $tenant->id,
                        'phone_number_id' => $inbound->phoneNumberId,
                    ]);

                    continue;
                }

                $handler->handle($tenant, $account, $inbound);
            }

            foreach ($parser->extractStatuses($payload) as $status) {
                $this->applyStatus($status->wamid, $status->status, $status->error);
            }

            $event->markProcessed();
        });
    }

    /**
     * Applique un statut de livraison.
     *
     * Les webhooks de statut n'arrivent pas nécessairement dans l'ordre :
     * `Message::applyStatus()` refuse toute régression, un `delivered` tardif
     * n'écrase donc pas un `read` déjà enregistré.
     */
    private function applyStatus(string $wamid, MessageStatus $status, ?array $error): void
    {
        $message = Message::query()->where('wamid', $wamid)->first();

        if ($message === null) {
            return;
        }

        if ($message->applyStatus($status, $error)) {
            $message->save();

            MessageStatusUpdated::dispatch($message);
        }
    }

    public function failed(Throwable $exception): void
    {
        WebhookEvent::query()
            ->whereKey($this->webhookEventId)
            ->update([
                'status' => WebhookEventStatus::Failed->value,
                'error'  => mb_substr($exception->getMessage(), 0, 2000),
            ]);

        Log::channel('whatsapp')->error('Traitement de webhook définitivement en échec.', [
            'webhook_event_id' => $this->webhookEventId,
            'tenant_id'        => $this->tenantId,
            'error'            => $exception->getMessage(),
        ]);
    }
}
