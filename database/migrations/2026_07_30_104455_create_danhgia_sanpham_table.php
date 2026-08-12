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
        Schema::create('danhgia_sanpham', function (Blueprint $table) {
            $table->id('MADG');
            $table->string('MASP', 10);
            $table->string('MAKH', 100);
            $table->string('MAHD', 10)->nullable();
            $table->integer('SO_SAO');
            $table->text('NOI_DUNG')->nullable();
            $table->string('HINH_ANH')->nullable();
            $table->dateTime('NGAY_DG');
            $table->boolean('TRANG_THAI')->default(true);
            $table->timestamps();

            $table->foreign('MASP')->references('MASP')->on('sanpham');
            $table->foreign('MAKH')->references('MAKH')->on('khachhang');
            $table->foreign('MAHD')->references('MAHD')->on('hdban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('danhgia_sanpham');
    }
};
