<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\ConversationStatus;
use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Agent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Boîte de réception. */
    public function index(Request $request): Response
    {
        $this->authorizeAction($request, 'viewAny', Conversation::class);

        $conversations = Conversation::query()
            ->with(['contact:id,name,profile_name,wa_id', 'assignedUser:id,name'])
            ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('mine'), fn ($q) => $q->assignedTo($request->user()->id))
            ->when($request->boolean('awaiting'), fn ($q) => $q->awaitingHuman())
            ->when($request->string('q')->toString() !== '', function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->whereHas('contact', fn ($c) => $c->search($term));
            })
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
            'filters'       => $request->only(['status', 'mine', 'awaiting', 'q']),
            'counts'        => [
                'open'     => Conversation::query()->where('status', ConversationStatus::Open->value)->count(),
                'awaiting' => Conversation::query()->awaitingHuman()->count(),
            ],
            'conversation'  => null,
            'messages'      => [],
            'notes'         => [],
            'lead'          => null,
            'operators'     => User::query()->ofTenant($request->user()->tenant_id)->select('id', 'name')->get(),
            'agents'        => Agent::query()->select('id', 'name')->get(),
            'windowOpen'    => false,
            'windowExpires' => null,
            'templates'     => [],
        ]);
    }

    /** Fil d'une conversation. */
    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorizeAction($request, 'view', $conversation);

        // Ouvrir la conversation vaut lecture : le compteur est remis à zéro
        // ici et non côté client, pour rester cohérent entre onglets.
        if ($conversation->unread_count > 0) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }

        $conversations = Conversation::query()
            ->with(['contact:id,name,profile_name,wa_id', 'assignedUser:id,name'])
            ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('mine'), fn ($q) => $q->assignedTo($request->user()->id))
            ->when($request->boolean('awaiting'), fn ($q) => $q->awaitingHuman())
            ->when($request->string('q')->toString() !== '', function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->whereHas('contact', fn ($c) => $c->search($term));
            })
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
            'filters'       => $request->only(['status', 'mine', 'awaiting', 'q']),
            'counts'        => [
                'open'     => Conversation::query()->where('status', ConversationStatus::Open->value)->count(),
                'awaiting' => Conversation::query()->awaitingHuman()->count(),
            ],
            'conversation'  => $conversation->load(['contact', 'assignedUser:id,name', 'whatsappAccount:id,display_phone_number']),
            'messages'      => $conversation->messages()
                ->with('sender:id,name')
                ->orderBy('created_at')
                ->limit(200)
                ->get(),
            'notes'         => $conversation->notes()->with('author:id,name')->latest()->get(),
            'lead'          => $conversation->tenant
                ? \App\Models\Lead::query()->where('contact_id', $conversation->contact_id)->open()->first()
                : null,
            'operators'     => User::query()->ofTenant($conversation->tenant_id)->select('id', 'name')->get(),
            'agents'        => Agent::query()->select('id', 'name')->get(),
            'windowOpen'    => $conversation->isWithinServiceWindow(),
            'windowExpires' => $conversation->window_expires_at?->toIso8601String(),
            'templates'     => $conversation->whatsappAccount
                ? $conversation->whatsappAccount->templates()->approved()->get()
                : [],
        ]);
    }

    /** Changement de statut (fermeture, mise en veille, réouverture). */
    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeAction($request, 'close', $conversation);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,pending,snoozed,closed'],
        ]);

        $status = ConversationStatus::from($validated['status']);

        $conversation->forceFill([
            'status'    => $status,
            'closed_at' => $status === ConversationStatus::Closed ? now() : null,
            'closed_by' => $status === ConversationStatus::Closed ? $request->user()->id : null,
        ])->save();

        $this->audit->log('conversation.status_changed', $conversation, ['after' => ['status' => $status->value]]);
        ConversationUpdated::dispatch($conversation);

        return back()->with('success', 'Statut mis à jour.');
    }

    /** Attribution à un opérateur. */
    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeAction($request, 'assign', $conversation);

        $validated = $request->validate([
            'user_id' => ['nullable', 'uuid'],
        ]);

        // L'opérateur cible doit appartenir au même tenant : sans ce contrôle,
        // un identifiant forgé attribuerait la conversation à un inconnu.
        if ($validated['user_id'] !== null) {
            $belongs = User::query()
                ->ofTenant($conversation->tenant_id)
                ->whereKey($validated['user_id'])
                ->exists();

            abort_unless($belongs, 422, 'Cet utilisateur n\'appartient pas à votre entreprise.');
        }

        $conversation->forceFill(['assigned_user_id' => $validated['user_id']])->save();

        $this->audit->log('conversation.assigned', $conversation, [
            'after' => ['assigned_user_id' => $validated['user_id']],
        ]);
        ConversationUpdated::dispatch($conversation);

        return back()->with('success', 'Conversation attribuée.');
    }

    /** Reprise humaine : coupe l'IA et place la conversation en attente. */
    public function handover(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeAction($request, 'toggleAi', $conversation);

        $conversation->forceFill([
            'ai_enabled'       => false,
            'handover_at'      => now(),
            'handover_reason'  => 'reprise_operateur',
            'status'           => ConversationStatus::Open,
            'assigned_user_id' => $conversation->assigned_user_id ?? $request->user()->id,
        ])->save();

        $this->audit->log('conversation.handover_manual', $conversation);
        ConversationUpdated::dispatch($conversation);

        return back()->with('success', 'Vous avez repris la conversation.');
    }

    /** Réactivation de l'agent IA. */
    public function resumeAi(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeAction($request, 'toggleAi', $conversation);

        $conversation->forceFill([
            'ai_enabled'      => true,
            'handover_at'     => null,
            'handover_reason' => null,
            'status'          => ConversationStatus::Open,
        ])->save();

        $this->audit->log('conversation.ai_resumed', $conversation);
        ConversationUpdated::dispatch($conversation);

        return back()->with('success', 'L\'agent IA a repris la main.');
    }

    /** Attribution à un agent IA. */
    public function assignAgent(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorizeAction($request, 'update', $conversation);

        $validated = $request->validate([
            'agent_id' => ['nullable', 'uuid'],
        ]);

        if ($validated['agent_id'] !== null) {
            $belongs = Agent::query()
                ->where('tenant_id', $conversation->tenant_id)
                ->whereKey($validated['agent_id'])
                ->exists();

            abort_unless($belongs, 422, 'Cet agent n\'appartient pas à votre entreprise.');
        }

        $conversation->forceFill(['agent_id' => $validated['agent_id']])->save();

        $this->audit->log('conversation.agent_assigned', $conversation, [
            'after' => ['agent_id' => $validated['agent_id']],
        ]);
        ConversationUpdated::dispatch($conversation);

        return back()->with('success', 'Agent IA mis à jour.');
    }

    private function authorizeAction(Request $request, string $ability, mixed $target): void
    {
        abort_unless($request->user()->can($ability, $target), 403);
    }
}
