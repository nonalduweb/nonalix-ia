<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Conversation;
use App\Models\ConversationNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationNoteController
{
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('view', $conversation), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        ConversationNote::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $request->user()->id,
            'body'            => $validated['body'],
        ]);

        // Aucun événement de diffusion : une note interne n'a pas à circuler
        // sur les mêmes canaux que les messages, qui alimentent aussi les
        // compteurs et les notifications.
        return back()->with('success', 'Note ajoutée.');
    }
}
