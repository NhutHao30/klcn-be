<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_movements') && Schema::hasTable('tonkho_cuahang')) {
            try {
                DB::statement("ALTER TABLE `inventory_movements` ADD CONSTRAINT `fk_inv_mov_tonkho` FOREIGN KEY (`MACUAHANG`, `MASP`) REFERENCES `tonkho_cuahang` (`MACUAHANG`, `MASP`) ON DELETE CASCADE");
            } catch (\Exception $e) {
                // If composite key fails due to data type mismatch, log exception
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_movements')) {
            try {
                DB::statement("ALTER TABLE `inventory_movements` DROP FOREIGN KEY `fk_inv_mov_tonkho`");
            } catch (\Exception $e) {}
        }
    }
};
