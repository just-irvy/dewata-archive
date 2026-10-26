<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('view-audit-logs');

        $entity = (string) $request->query('entity');

        $logs = AuditLog::query()->with('actor')
            ->when($entity !== '', fn ($q) => $q->where('entity_type', $entity))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'entities' => AuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'entity' => $entity,
        ]);
    }
}
