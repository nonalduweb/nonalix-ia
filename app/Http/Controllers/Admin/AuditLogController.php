<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/AuditLogs', [
            'logs' => AuditLog::query()
                ->with('tenant:id,name', 'user:id,name,email')
                ->when($request->string('tenant_id')->toString() !== '', fn ($q) => $q->where('tenant_id', $request->string('tenant_id')))
                ->when($request->string('action')->toString() !== '', fn ($q) => $q->where('action', 'like', $request->string('action')->toString().'%'))
                ->when($request->date('from'), fn ($q, $from) => $q->where('created_at', '>=', $from))
                ->when($request->date('to'), fn ($q, $to) => $q->where('created_at', '<=', $to))
                ->orderByDesc('created_at')
                ->paginate(60)
                ->withQueryString(),
            'filters' => $request->only(['tenant_id', 'action', 'from', 'to']),
        ]);
    }
}
