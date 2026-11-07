<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        try {
            $model = new AuditLog();
            $table = $model->getTable();

            // Verify if table actually exists in database
            if (!Schema::hasTable($table)) {
                if (Schema::hasTable('auditLogs')) {
                    $table = 'auditLogs';
                    $model->setTable($table);
                } elseif (Schema::hasTable('auditlogs')) {
                    $table = 'auditlogs';
                    $model->setTable($table);
                } elseif (Schema::hasTable('audit_logs')) {
                    $table = 'audit_logs';
                    $model->setTable($table);
                } else {
                    return response()->json([
                        'current_page' => 1,
                        'data' => [],
                        'last_page' => 1,
                        'total' => 0,
                    ]);
                }
            }

            $hasMoTa = Schema::hasColumn($table, 'moTa');
            $hasEntityId = Schema::hasColumn($table, 'entityId');
            $tableNameCol = Schema::hasColumn($table, 'tableName') ? 'tableName' : (Schema::hasColumn($table, 'table_name') ? 'table_name' : null);
            $ipCol = Schema::hasColumn($table, 'ipAddress') ? 'ipAddress' : (Schema::hasColumn($table, 'ip_address') ? 'ip_address' : null);
            $createdAtCol = Schema::hasColumn($table, 'createdAt') ? 'createdAt' : (Schema::hasColumn($table, 'created_at') ? 'created_at' : 'id');

            $query = $model->newQuery()->with('taikhoan.nguoidung');

            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }

            if ($request->filled('table_name') && $tableNameCol) {
                $query->where($tableNameCol, $request->table_name);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search, $hasMoTa, $hasEntityId, $tableNameCol, $ipCol) {
                    if ($tableNameCol) {
                        $q->where($tableNameCol, 'like', "%{$search}%");
                    }
                    $q->orWhere('action', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");

                    if ($hasMoTa) {
                        $q->orWhere('moTa', 'like', "%{$search}%");
                    }
                    if ($hasEntityId) {
                        $q->orWhere('entityId', 'like', "%{$search}%");
                    }
                    if ($ipCol) {
                        $q->orWhere($ipCol, 'like', "%{$search}%");
                    }
                    $q->orWhereHas('taikhoan', function ($sq) use ($search) {
                        $sq->where('username', 'like', "%{$search}%");
                    })
                    ->orWhereHas('taikhoan.nguoidung', function ($sq) use ($search) {
                        $sq->where('hoTen', 'like', "%{$search}%");
                    });
                });
            }

            $logs = $query->orderBy($createdAtCol, 'desc')->orderBy('id', 'desc')->paginate(20);
            return response()->json($logs);
        } catch (\Throwable $e) {
            Log::error('AuditLogController index error: ' . $e->getMessage());
            return response()->json([
                'current_page' => 1,
                'data' => [],
                'last_page' => 1,
                'total' => 0,
                'error' => $e->getMessage()
            ], 200);
        }
    }
}
