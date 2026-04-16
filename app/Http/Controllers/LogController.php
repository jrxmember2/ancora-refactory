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
        $sortState = SortableQuery::apply($query, $request, [
            'created_at' => 'created_at',
            'user' => 'user_email',
            'action' => 'action',
            'details' => 'details',
        ], 'created_at', 'desc');

        return view('pages.admin.logs', [
            'title' => 'Logs e auditoria',
            'items' => $query->paginate(25)->withQueryString(),
            'sortState' => $sortState,
        ]);
    }
}
