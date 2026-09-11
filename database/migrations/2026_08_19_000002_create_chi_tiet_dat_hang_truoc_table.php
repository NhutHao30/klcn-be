<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chi_tiet_dat_hang_truoc')) {
            Schema::create('chi_tiet_dat_hang_truoc', function (Blueprint $table) {
            $table->id();
            $table->string('MADHT', 25);
            $table->string('MASP', 50)->nullable();          // Nullable nếu nhập tay
            $table->string('TEN_SP', 255);                   // Luôn lưu tên tại thời điểm đặt
            $table->integer('SO_LUONG')->default(1);
            $table->bigInteger('DON_GIA')->default(0);
            $table->text('GHI_CHU_SP')->nullable();          // "kem dâu, chữ sinh nhật"
            $table->timestamps();

            $table->foreign('MADHT')
                  ->references('MADHT')
                  ->on('dat_hang_truoc')
                  ->onDelete('cascade');
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tiet_dat_hang_truoc');
    }
};
