<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = Schema::hasTable('donHang') ? 'donHang' : (Schema::hasTable('donhang') ? 'donhang' : null);
        if ($table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'toWardCode')) {
                    if (Schema::hasColumn($table, 'diaChiGiaoHang')) {
                        $t->string('toWardCode', 20)->nullable()->after('diaChiGiaoHang');
                    } else {
                        $t->string('toWardCode', 20)->nullable();
                    }
                }
                if (!Schema::hasColumn($table, 'toDistrictId')) {
                    if (Schema::hasColumn($table, 'toWardCode')) {
                        $t->integer('toDistrictId')->nullable()->after('toWardCode');
                    } else {
                        $t->integer('toDistrictId')->nullable();
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = Schema::hasTable('donHang') ? 'donHang' : (Schema::hasTable('donhang') ? 'donhang' : null);
        if ($table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $colsToDrop = [];
                if (Schema::hasColumn($table, 'toDistrictId')) {
                    $colsToDrop[] = 'toDistrictId';
                }
                if (Schema::hasColumn($table, 'toWardCode')) {
                    $colsToDrop[] = 'toWardCode';
                }
                if (!empty($colsToDrop)) {
                    $t->dropColumn($colsToDrop);
                }
            });
        }
    }
};
