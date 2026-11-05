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
        // Thêm cột chuKySo vào bảng nguoidung
        foreach (['nguoidung', 'nguoi_dung'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'chuKySo')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->longText('chuKySo')->nullable()->after('avatar');
                });
            }
        }

        // Thêm cột chuKySo vào bảng taikhoan
        foreach (['taikhoan', 'tai_khoan'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'chuKySo')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->longText('chuKySo')->nullable()->after('avatar');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['nguoidung', 'nguoi_dung'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'chuKySo')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('chuKySo');
                });
            }
        }

        foreach (['taikhoan', 'tai_khoan'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'chuKySo')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('chuKySo');
                });
            }
        }
    }
};
