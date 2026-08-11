<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Audit trail viewer (admin/HR only). Filterable by action, model type,
     * and free-text search across actor name/email and record id.
     */
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('model'), fn ($q) => $q->where('model_type', 'like', '%' . $request->string('model') . '%'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->string('search'));
                $q->where(function ($inner) use ($search) {
                    $inner->where('model_id', 'like', Search::contains($search))
                        ->orWhere('ip_address', 'like', Search::contains($search))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', Search::contains($search))->orWhere('email', 'like', Search::contains($search)));
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
