<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('taikhoan');

        if ($request->filled('table_name')) {
            $query->where('table_name', $request->table_name);
        }

        $logs = $query->orderBy('id', 'desc')->paginate(20);
        return response()->json($logs);
    }
}
