<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('nha_cung_cap')) {
            Schema::create('nha_cung_cap', function (Blueprint $table) {
            $table->string('MANCC', 10)->primary();
            $table->string('TENNCC', 150);
            $table->string('DIENTHOAI', 20)->nullable();
            $table->string('EMAIL', 100)->nullable();
            $table->string('DIACHI', 255)->nullable();
            $table->string('NGUOIDAIDIEN', 100)->nullable(); // Tên người đại diện / liên hệ
            $table->string('MASOTHUE', 20)->nullable();      // Mã số thuế NCC
            $table->enum('TRANGTHAI', ['Đang hợp tác', 'Ngừng hợp tác'])->default('Đang hợp tác');
            $table->text('GHICHU')->nullable();
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nha_cung_cap');
    }
};
