<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            if (!Schema::hasColumn('auditlogs', 'moTa')) {
                $table->string('moTa', 255)->nullable()->after('tableName');
            }
            if (!Schema::hasColumn('auditlogs', 'entityId')) {
                $table->string('entityId', 64)->nullable()->after('moTa');
            }
            if (!Schema::hasColumn('auditlogs', 'requestId')) {
                $table->string('requestId', 64)->nullable()->after('userAgent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            foreach (['moTa', 'entityId', 'requestId'] as $col) {
                if (Schema::hasColumn('auditlogs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

