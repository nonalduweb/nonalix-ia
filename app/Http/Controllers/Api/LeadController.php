<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('viewAny', Lead::class), 403);

        return response()->json(
            Lead::query()
                ->with('contact:id,name,profile_name,wa_id')
                ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->boolean('open'), fn ($q) => $q->open())
                ->orderByDesc('created_at')
                ->paginate(min((int) $request->integer('per_page', 50), 100))
        );
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->can('view', $lead), 403);

        return response()->json(['data' => $lead->load('contact', 'conversation')]);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->can('update', $lead), 403);

        $lead->fill($request->validate([
            'status'      => ['required', Rule::enum(LeadStatus::class)],
            'score'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'lost_reason' => ['nullable', 'string', 'max:160'],
        ]))->save();

        return response()->json(['data' => $lead]);
    }
}
