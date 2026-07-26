<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\OptInStatus;
use App\Models\ConsentLog;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('viewAny', Contact::class), 403);

        return response()->json(
            Contact::query()
                ->when($request->string('q')->toString() !== '', fn ($q) => $q->search($request->string('q')->toString()))
                ->orderByDesc('last_message_at')
                ->paginate(min((int) $request->integer('per_page', 50), 100))
        );
    }

    public function show(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($request->user()->can('view', $contact), 403);

        return response()->json(['data' => $contact]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('create', Contact::class), 403);

        $validated = $request->validate([
            // E.164 sans le « + » : format exigé par l'API Meta.
            'wa_id'      => ['required', 'string', 'regex:/^[1-9][0-9]{7,14}$/'],
            'name'       => ['nullable', 'string', 'max:160'],
            'email'      => ['nullable', 'email', 'max:190'],
            'attributes' => ['nullable', 'array'],
            'opt_in'     => ['boolean'],
        ]);

        $contact = Contact::query()->firstOrNew(['wa_id' => $validated['wa_id']]);

        $contact->fill([
            'phone_number' => '+'.$validated['wa_id'],
            'name'         => $validated['name'] ?? $contact->name,
            'email'        => $validated['email'] ?? $contact->email,
            'attributes'   => $validated['attributes'] ?? $contact->attributes,
        ]);

        // Un opt-in importé doit être traçable : l'API ne peut pas déclarer
        // un consentement sans qu'il soit consigné.
        if (($validated['opt_in'] ?? false) && $contact->opt_in_status !== OptInStatus::OptedIn) {
            $contact->opt_in_status = OptInStatus::OptedIn;
            $contact->opt_in_at     = now();
            $contact->opt_in_source = 'api';
        }

        $wasNew = ! $contact->exists;
        $contact->save();

        if ($contact->opt_in_source === 'api' && $contact->wasChanged('opt_in_status')) {
            ConsentLog::create([
                'contact_id' => $contact->id,
                'action'     => 'opt_in',
                'channel'    => 'whatsapp',
                'source'     => 'api',
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return response()->json(['data' => $contact], $wasNew ? 201 : 200);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($request->user()->can('update', $contact), 403);

        $contact->fill($request->validate([
            'name'       => ['nullable', 'string', 'max:160'],
            'email'      => ['nullable', 'email', 'max:190'],
            'attributes' => ['nullable', 'array'],
        ]))->save();

        return response()->json(['data' => $contact]);
    }

    public function optOut(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($request->user()->can('optOut', $contact), 403);

        $contact->forceFill([
            'opt_in_status' => OptInStatus::OptedOut,
            'opt_out_at'    => now(),
            'opt_in_source' => 'api',
        ])->save();

        ConsentLog::create([
            'contact_id' => $contact->id,
            'action'     => 'opt_out',
            'channel'    => 'whatsapp',
            'source'     => 'api',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return response()->json(['data' => $contact]);
    }
}
