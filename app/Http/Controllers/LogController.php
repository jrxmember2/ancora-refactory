<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\SortableQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query();
        if ($term = trim((string) $request->input('q', ''))) {
            $query->where(function ($sub) use ($term) {
                $sub->where('details', 'like', "%{$term}%")
                    ->orWhere('action', 'like', "%{$term}%")
                    ->orWhere('user_email', 'like', "%{$term}%")
                    ->orWhere('entity_type', 'like', "%{$term}%");
            });
        }
        if ($request->filled('action')) {
            $query->where('action', (string) $request->input('action'));
        }
        if ($request->filled('entity_type')) {
            $query->where('entity_type', (string) $request->input('entity_type'));
        }
        if ($request->filled('user_email')) {
            $query->where('user_email', (string) $request->input('user_email'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $sortState = SortableQuery::apply($query, $request, [
            'created_at' => 'created_at',
            'user' => 'user_email',
            'action' => 'action',
            'entity_type' => 'entity_type',
            'details' => 'details',
        ], 'created_at', 'desc');

        return view('pages.admin.logs', [
            'title' => 'Logs e auditoria',
            'items' => $query->paginate(25)->withQueryString(),
            'filters' => $request->all(),
            'filterOptions' => [
                'actions' => AuditLog::query()->select('action')->whereNotNull('action')->distinct()->orderBy('action')->pluck('action'),
                'entityTypes' => AuditLog::query()->select('entity_type')->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
                'users' => AuditLog::query()->select('user_email')->whereNotNull('user_email')->distinct()->orderBy('user_email')->pluck('user_email'),
            ],
            'sortState' => $sortState,
        ]);
    }
}
