<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Đảm bảo cuaHangXuatId trong yeuCauDieuChuyen có thể NULL
        $ycTables = ['yeuCauDieuChuyen', 'yeucaudieuchuyen', 'yeucau_dieuchuyen'];
        foreach ($ycTables as $table) {
            if (Schema::hasTable($table)) {
                if (Schema::hasColumn($table, 'cuaHangXuatId')) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `cuaHangXuatId` INT UNSIGNED NULL;");
                } elseif (Schema::hasColumn($table, 'cuahang_xuat_id')) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `cuahang_xuat_id` INT UNSIGNED NULL;");
                }
            }
        }

        // 2. Đảm bảo bảng nhatKyKho tương thích mọi môi trường Linux / Windows
        if (Schema::hasTable('nhatKyKho') && !Schema::hasTable('nhatkykho')) {
            try {
                DB::statement("CREATE OR REPLACE VIEW `nhatkykho` AS SELECT * FROM `nhatKyKho`;");
            } catch (\Throwable $e) {}
        } elseif (Schema::hasTable('nhatkykho') && !Schema::hasTable('nhatKyKho')) {
            try {
                DB::statement("CREATE OR REPLACE VIEW `nhatKyKho` AS SELECT * FROM `nhatkykho`;");
            } catch (\Throwable $e) {}
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        // No destructive reverse
    }
};
