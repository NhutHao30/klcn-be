<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Drop foreign key constraint on donhang if it exists
        if (Schema::hasTable('donhang')) {
            try {
                Schema::table('donhang', function ($table) {
                    $table->dropForeign('don_hang_voucher_id_foreign');
                });
            } catch (\Throwable $e) {
                // Ignore if constraint does not exist
            }
        }

        // 2. Drop old unused voucher table if exists
        if (Schema::hasTable('voucher')) {
            Schema::dropIfExists('voucher');
        }

        // 3. Rename khuyenmai table to voucher
        if (Schema::hasTable('khuyenmai')) {
            Schema::rename('khuyenmai', 'voucher');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        if (Schema::hasTable('voucher') && !Schema::hasTable('khuyenmai')) {
            Schema::rename('voucher', 'khuyenmai');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
