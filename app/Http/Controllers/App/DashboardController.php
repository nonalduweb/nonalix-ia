<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\ConversationStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageDirection;
use App\Models\AiUsageLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\Billing\QuotaService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord client.
 *
 * Statistiques calculées à la volée : les volumes de la Phase 1 le permettent
 * largement. La pré-agrégation viendra quand la latence l'imposera, pas avant
 * (voir ROADMAP.md, dette technique assumée).
 */
class DashboardController
{
    public function __construct(
        private readonly QuotaService $quotas,
        private readonly TenantContext $context,
    ) {}

    public function __invoke(Request $request): Response
    {
        $tenant = $this->context->currentOrFail();
        $since  = now()->subDays(30);

        $conversations = Conversation::query()->where('created_at', '>=', $since)->count();
        $handovers     = Conversation::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('handover_at')
            ->count();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'conversations_30d'  => $conversations,
                'messages_in_30d'    => Message::query()->inbound()->where('created_at', '>=', $since)->count(),
                'messages_out_30d'   => Message::query()->outbound()->where('created_at', '>=', $since)->count(),
                'contacts_total'     => Contact::query()->count(),
                'leads_qualified_30d' => Lead::query()
                    ->where('status', LeadStatus::Qualified->value)
                    ->where('created_at', '>=', $since)
                    ->count(),
                // Indicateur clé : au-delà de ~30 %, l'agent ne tient pas son
                // rôle et sa configuration doit être revue.
                'handover_rate' => $conversations > 0
                    ? round($handovers / $conversations * 100, 1)
                    : 0.0,
                'ai_cost_30d_micros' => (int) AiUsageLog::query()
                    ->where('created_at', '>=', $since)
                    ->sum('cost_micros'),
            ],

            'inbox' => [
                'open'     => Conversation::query()->where('status', ConversationStatus::Open->value)->count(),
                'awaiting' => Conversation::query()->awaitingHuman()->count(),
                'unassigned' => Conversation::query()
                    ->whereNull('assigned_user_id')
                    ->where('status', '!=', ConversationStatus::Closed->value)
                    ->count(),
            ],

            'quotas' => collect(config('nonalix.quotas.metrics'))
                ->mapWithKeys(fn (string $metric) => [$metric => [
                    'used'  => $this->quotas->current($tenant, $metric),
                    'limit' => $tenant->quotaFor($metric),
                ]])->all(),

            // Un agent non configuré ou un numéro déconnecté sont les deux
            // causes les plus fréquentes de « ça ne marche pas ».
            'setup' => [
                'whatsapp_connected' => WhatsAppAccount::query()->first()?->canSend() ?? false,
                'agent_active'       => $tenant->activeAgent() !== null,
                'has_knowledge'      => \App\Models\Document::query()->searchable()->exists(),
            ],

            'recentMessages' => Message::query()
                ->where('direction', MessageDirection::Inbound->value)
                ->with('conversation.contact:id,name,profile_name,wa_id')
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
