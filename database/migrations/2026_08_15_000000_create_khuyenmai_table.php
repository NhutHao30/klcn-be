<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('khuyenmai')) {
            Schema::create('khuyenmai', function (Blueprint $table) {
                $table->string('MA_VOUCHER', 50)->primary();
                $table->string('TEN_VOUCHER');
                $table->enum('LOAI_GIAM', ['PHAN_TRAM', 'TIEN_MAT'])->default('PHAN_TRAM');
                $table->integer('GIA_TRI')->default(0);
                $table->integer('DON_TOI_THIEU')->default(0);
                $table->integer('GIAM_TOI_DA')->nullable();
                $table->integer('SO_LUONG_GIOI_HAN')->default(100);
                $table->integer('SO_LUONG_DA_DUNG')->default(0);
                $table->dateTime('NGAY_BAT_DAU')->nullable();
                $table->dateTime('NGAY_KET_THUC')->nullable();
                $table->boolean('TRANG_THAI')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('khuyenmai');
    }
};
