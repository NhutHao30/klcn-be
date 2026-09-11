<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('khuyenmai_khachhang')) {
            Schema::create('khuyenmai_khachhang', function (Blueprint $table) {
                $table->id();
                $table->string('MA_VOUCHER', 50);
                $table->string('MAKH', 50);
                $table->string('MAHD', 50)->nullable();
                $table->dateTime('NGAY_THU_THAP')->useCurrent();
                $table->dateTime('NGAY_SU_DUNG')->nullable();
                $table->string('TRANG_THAI', 20)->default('CHUA_SU_DUNG'); // CHUA_SU_DUNG, DA_SU_DUNG, HET_HAN
                $table->timestamps();
            });
        } else {
            Schema::table('khuyenmai_khachhang', function (Blueprint $table) {
                if (!Schema::hasColumn('khuyenmai_khachhang', 'NGAY_THU_THAP')) {
                    $table->dateTime('NGAY_THU_THAP')->useCurrent()->after('MAHD');
                }
                if (!Schema::hasColumn('khuyenmai_khachhang', 'TRANG_THAI')) {
                    $table->string('TRANG_THAI', 20)->default('CHUA_SU_DUNG')->after('NGAY_SU_DUNG');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('khuyenmai_khachhang');
    }
};
