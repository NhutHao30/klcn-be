<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::statement("ALTER TABLE `diaChi` MODIFY `maTinh` INT NULL DEFAULT 0");
            DB::statement("ALTER TABLE `diaChi` MODIFY `maHuyen` INT NULL DEFAULT 0");
            DB::statement("ALTER TABLE `diaChi` MODIFY `maXa` VARCHAR(50) NULL DEFAULT ''");
        } catch (\Exception $e) {
            // Fallback for snake_case table name if exists
            try {
                DB::statement("ALTER TABLE `dia_chi` MODIFY `ma_tinh` INT NULL DEFAULT 0");
                DB::statement("ALTER TABLE `dia_chi` MODIFY `ma_huyen` INT NULL DEFAULT 0");
                DB::statement("ALTER TABLE `dia_chi` MODIFY `ma_xa` VARCHAR(50) NULL DEFAULT ''");
            } catch (\Exception $e2) {
                // Ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
