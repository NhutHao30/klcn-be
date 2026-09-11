<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dat_hang_truoc')) {
            Schema::create('dat_hang_truoc', function (Blueprint $table) {
            $table->string('MADHT', 25)->primary();
            $table->unsignedBigInteger('MACUAHANG');
            $table->string('USERNAME_NV', 50)->nullable();
            $table->string('MAKH', 50)->nullable();          // Khách có tài khoản
            $table->string('HOTEN_KH', 100);
            $table->string('SDT_KH', 20);
            $table->dateTime('NGAY_DAT');                    // Auto-set by controller
            $table->dateTime('NGAY_HEN_LAY');                // Must be > NGAY_DAT
            $table->text('GHI_CHU')->nullable();
            $table->bigInteger('TONG_TIEN')->default(0);
            $table->bigInteger('TIEN_COC')->default(0);      // 0 nếu ≤200k
            $table->string('PHUONG_THUC_COC', 50)->nullable();
            $table->enum('LY_DO_DAT_TRUOC', ['het_hang', 'hen_ngay'])->default('hen_ngay');
            $table->enum('TRANG_THAI', [
                'cho_xac_nhan',
                'da_coc',
                'san_sang',
                'da_giao',
                'da_huy',
            ])->default('cho_xac_nhan');
            $table->string('MAHD_HOAN_THANH', 20)->nullable(); // Mã HdBan sau khi hoàn thành
            $table->timestamps();

            $table->foreign('MACUAHANG')->references('MACUAHANG')->on('cuahang')->onDelete('restrict');
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dat_hang_truoc');
    }
};
