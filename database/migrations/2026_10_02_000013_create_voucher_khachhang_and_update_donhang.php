<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $table = Schema::hasTable('donHang') ? 'donHang' : (Schema::hasTable('donhang') ? 'donhang' : null);
        // 1. Ensure donhang has maVoucher column
        if ($table && !Schema::hasColumn($table, 'maVoucher')) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'voucherId')) {
                    $t->string('maVoucher', 50)->nullable()->after('voucherId');
                } else {
                    $t->string('maVoucher', 50)->nullable();
                }
            });
        }

        // 2. Create voucher_khachhang table for saving vouchers & tracking customer voucher usage
        if (!Schema::hasTable('voucher_khachhang')) {
            Schema::create('voucher_khachhang', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('nguoidungId');
                $table->string('maVoucher', 50);
                $table->string('maDonHang', 50)->nullable();
                $table->dateTime('ngayThuThap')->nullable();
                $table->dateTime('ngaySuDung')->nullable();
                $table->tinyInteger('trangThai')->default(0); // 0: CHUA_SU_DUNG, 1: DA_SU_DUNG
                $table->timestamps();

                $table->index(['nguoidungId', 'maVoucher']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_khachhang');
        $table = Schema::hasTable('donHang') ? 'donHang' : (Schema::hasTable('donhang') ? 'donhang' : null);
        if ($table && Schema::hasColumn($table, 'maVoucher')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('maVoucher');
            });
        }
    }
};
