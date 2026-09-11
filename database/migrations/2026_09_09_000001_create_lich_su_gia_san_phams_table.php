<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lich_su_gia_san_phams')) {
            Schema::create('lich_su_gia_san_phams', function (Blueprint $table) {
                $table->id();
                $table->string('MASP', 50);
                $table->decimal('GIA_CU', 15, 2)->nullable();
                $table->decimal('GIA_MOI', 15, 2);
                $table->float('PHAN_TRAM_GIAM')->default(0);
                $table->string('LY_DO', 255)->nullable();
                $table->string('USERNAME_NV', 100)->nullable();
                $table->dateTime('NGAY_AP_DUNG')->useCurrent();
                $table->timestamps();

                $table->foreign('MASP')->references('MASP')->on('sanpham')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_gia_san_phams');
    }
};
