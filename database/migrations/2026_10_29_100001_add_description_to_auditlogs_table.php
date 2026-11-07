<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = Schema::hasTable('auditLogs') ? 'auditLogs' : (Schema::hasTable('auditlogs') ? 'auditlogs' : (Schema::hasTable('audit_logs') ? 'audit_logs' : null));
        if ($table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'moTa')) {
                    if (Schema::hasColumn($table, 'tableName')) {
                        $t->string('moTa', 255)->nullable()->after('tableName');
                    } elseif (Schema::hasColumn($table, 'table_name')) {
                        $t->string('moTa', 255)->nullable()->after('table_name');
                    } else {
                        $t->string('moTa', 255)->nullable();
                    }
                }
                if (!Schema::hasColumn($table, 'entityId')) {
                    if (Schema::hasColumn($table, 'moTa')) {
                        $t->string('entityId', 64)->nullable()->after('moTa');
                    } else {
                        $t->string('entityId', 64)->nullable();
                    }
                }
                if (!Schema::hasColumn($table, 'requestId')) {
                    if (Schema::hasColumn($table, 'userAgent')) {
                        $t->string('requestId', 64)->nullable()->after('userAgent');
                    } elseif (Schema::hasColumn($table, 'user_agent')) {
                        $t->string('requestId', 64)->nullable()->after('user_agent');
                    } else {
                        $t->string('requestId', 64)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        $table = Schema::hasTable('auditLogs') ? 'auditLogs' : (Schema::hasTable('auditlogs') ? 'auditlogs' : (Schema::hasTable('audit_logs') ? 'audit_logs' : null));
        if ($table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $colsToDrop = [];
                foreach (['moTa', 'entityId', 'requestId'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $colsToDrop[] = $col;
                    }
                }
                if (!empty($colsToDrop)) {
                    $t->dropColumn($colsToDrop);
                }
            });
        }
    }
};
