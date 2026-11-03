<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = Schema::hasTable('donHang') ? 'donHang' : (Schema::hasTable('donhang') ? 'donhang' : null);
        if ($table) {
            if (!Schema::hasColumn($table, 'minhChungThanhToan')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->longText('minhChungThanhToan')->nullable();
                });
            } else {
                DB::statement("ALTER TABLE `{$table}` MODIFY `minhChungThanhToan` LONGTEXT NULL");
            }
        }
    }

    public function down(): void
    {
        $table = Schema::hasTable('donHang') ? 'donHang' : (Schema::hasTable('donhang') ? 'donhang' : null);
        if ($table && Schema::hasColumn($table, 'minhChungThanhToan')) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `minhChungThanhToan` VARCHAR(255) NULL");
        }
    }
};
