<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|------------------------------------------------------------------------------
| Canaux de diffusion temps réel (Reverb)
|------------------------------------------------------------------------------
| Règle absolue : l'identifiant de tenant présent dans le nom du canal n'est
| JAMAIS une preuve d'appartenance. Il est comparé au tenant de l'utilisateur
| authentifié, et l'autorisation exige en plus la permission métier adéquate.
*/

Broadcast::channel('tenant.{tenantId}.conversations', function (User $user, string $tenantId) {
    return $user->tenant_id === $tenantId
        && $user->can('viewAny', Conversation::class);
});

Broadcast::channel('tenant.{tenantId}.conversation.{conversationId}',
    function (User $user, string $tenantId, string $conversationId) {
        if ($user->tenant_id !== $tenantId) {
            return false;
        }

        // Le scope global filtre déjà par tenant : un identifiant appartenant
        // à une autre entreprise ressort simplement introuvable.
        $conversation = Conversation::find($conversationId);

        return $conversation !== null && $user->can('view', $conversation);
    });

Broadcast::channel('tenant.{tenantId}.presence', function (User $user, string $tenantId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }

    return [
        'id'     => $user->id,
        'name'   => $user->name,
        'avatar' => $user->avatar_url,
    ];
});

// Notifications personnelles (attribution d'une conversation, mention…).
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});
