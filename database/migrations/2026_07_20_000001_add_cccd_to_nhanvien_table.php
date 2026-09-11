<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('nhanvien', function (Blueprint $table) {
            if (!Schema::hasColumn('nhanvien', 'CCCD_TRUOC')) {
                $table->string('CCCD_TRUOC')->nullable();
            }
            if (!Schema::hasColumn('nhanvien', 'CCCD_SAU')) {
                $table->string('CCCD_SAU')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('nhanvien', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('nhanvien', 'CCCD_TRUOC')) $cols[] = 'CCCD_TRUOC';
            if (Schema::hasColumn('nhanvien', 'CCCD_SAU')) $cols[] = 'CCCD_SAU';
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};
