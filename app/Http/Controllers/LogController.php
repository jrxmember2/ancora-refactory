<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.logs', [
            'title' => 'Logs e auditoria',
            'items' => AuditLog::query()->latest('created_at')->paginate(25),
        ]);
    }
}
