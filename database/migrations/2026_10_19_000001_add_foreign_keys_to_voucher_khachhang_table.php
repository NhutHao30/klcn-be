<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('voucher_khachhang', function (Blueprint $table) {
            // Thêm ràng buộc khóa ngoại với bảng nguoidung (thông tin khách hàng)
            $table->foreign('nguoidungId', 'fk_voucher_kh_nguoidung')
                ->references('id')
                ->on('nguoidung')
                ->onDelete('cascade');

            // Thêm ràng buộc khóa ngoại với bảng voucher
            $table->foreign('maVoucher', 'fk_voucher_kh_voucher')
                ->references('MA_VOUCHER')
                ->on('voucher')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_khachhang', function (Blueprint $table) {
            $table->dropForeign('fk_voucher_kh_nguoidung');
            $table->dropForeign('fk_voucher_kh_voucher');
        });
    }
};
