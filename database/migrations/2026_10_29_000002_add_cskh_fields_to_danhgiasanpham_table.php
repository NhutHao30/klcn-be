<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = Schema::hasTable('danhGiaSanPham') ? 'danhGiaSanPham' : 'danhgia_sanpham';

        Schema::table($table, function (Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'trangThai')) {
                $table->tinyInteger('trangThai')->default(1)->after('hinhAnhKemTheo');
            }
            if (!Schema::hasColumn($table->getTable(), 'phanHoiCskh')) {
                $table->text('phanHoiCskh')->nullable()->after('trangThai');
            }
            if (!Schema::hasColumn($table->getTable(), 'cskhId')) {
                $table->unsignedBigInteger('cskhId')->nullable()->after('phanHoiCskh');
            }
            if (!Schema::hasColumn($table->getTable(), 'ngayPhanHoi')) {
                $table->dateTime('ngayPhanHoi')->nullable()->after('cskhId');
            }
        });
    }

    public function down(): void
    {
        $table = Schema::hasTable('danhGiaSanPham') ? 'danhGiaSanPham' : 'danhgia_sanpham';

        Schema::table($table, function (Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'trangThai')) {
                $table->dropColumn('trangThai');
            }
            if (Schema::hasColumn($table->getTable(), 'phanHoiCskh')) {
                $table->dropColumn('phanHoiCskh');
            }
            if (Schema::hasColumn($table->getTable(), 'cskhId')) {
                $table->dropColumn('cskhId');
            }
            if (Schema::hasColumn($table->getTable(), 'ngayPhanHoi')) {
                $table->dropColumn('ngayPhanHoi');
            }
        });
    }
};

