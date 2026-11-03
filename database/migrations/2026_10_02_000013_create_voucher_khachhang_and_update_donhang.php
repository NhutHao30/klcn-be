<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Ensure donhang has maVoucher column
        if (Schema::hasTable('donhang') && !Schema::hasColumn('donhang', 'maVoucher')) {
            Schema::table('donhang', function (Blueprint $table) {
                $table->string('maVoucher', 50)->nullable()->after('voucherId');
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
        if (Schema::hasColumn('donhang', 'maVoucher')) {
            Schema::table('donhang', function (Blueprint $table) {
                $table->dropColumn('maVoucher');
            });
        }
    }
};
