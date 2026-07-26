<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ConversationStatus;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ConversationController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('viewAny', Conversation::class), 403);

        $conversations = Conversation::query()
            ->with('contact:id,name,profile_name,wa_id')
            ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->date('since'), fn ($q, $since) => $q->where('last_message_at', '>=', $since))
            ->orderByDesc('last_message_at')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        abort_unless($request->user()->can('view', $conversation), 403);

        return new ConversationResource($conversation->load('contact', 'assignedUser:id,name'));
    }

    public function update(Request $request, Conversation $conversation): ConversationResource
    {
        abort_unless($request->user()->can('update', $conversation), 403);

        $validated = $request->validate([
            'status'     => ['nullable', Rule::enum(ConversationStatus::class)],
            'ai_enabled' => ['nullable', 'boolean'],
        ]);

        // Couper l'IA depuis l'API vaut reprise humaine : sans cela, la
        // conversation resterait « ouverte » sans que personne ne s'en occupe.
        if (array_key_exists('ai_enabled', $validated) && $validated['ai_enabled'] === false) {
            $conversation->forceFill([
                'handover_at'     => now(),
                'handover_reason' => 'api',
            ]);
        }

        $conversation->fill(array_filter($validated, static fn ($v) => $v !== null))->save();

        return new ConversationResource($conversation);
    }
}
