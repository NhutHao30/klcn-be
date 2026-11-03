<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('taikhoan.nguoidung');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('table_name')) {
            $query->where('tableName', $request->table_name);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('tableName', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('moTa', 'like', "%{$search}%")
                  ->orWhere('entityId', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('ipAddress', 'like', "%{$search}%")
                  ->orWhereHas('taikhoan', function ($sq) use ($search) {
                      $sq->where('username', 'like', "%{$search}%");
                  })
                  ->orWhereHas('taikhoan.nguoidung', function ($sq) use ($search) {
                      $sq->where('hoTen', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->orderBy('createdAt', 'desc')->orderBy('id', 'desc')->paginate(20);
        return response()->json($logs);
    }
}
