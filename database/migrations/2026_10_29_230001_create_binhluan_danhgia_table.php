<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('binhluan_danhgia')) {
            Schema::create('binhluan_danhgia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('danhGiaId');
                $table->unsignedBigInteger('nguoidungId')->nullable();
                $table->unsignedBigInteger('taiKhoanId')->nullable();
                $table->text('noiDung');
                $table->unsignedBigInteger('parentId')->nullable();
                $table->tinyInteger('trangThai')->default(1);
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

                $table->index('danhGiaId');
                $table->index('nguoidungId');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('binhluan_danhgia');
    }
};

