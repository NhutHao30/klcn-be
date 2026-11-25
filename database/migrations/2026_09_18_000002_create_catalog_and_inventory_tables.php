<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Bảng loai_san_pham (Categories)
        Schema::create('loai_san_pham', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ten_loai', 100);
            $table->string('slug', 100)->unique();
            $table->string('hinh_anh', 255)->nullable();
            $table->tinyInteger('trang_thai')->default(1); // 0: Ẩn, 1: Hiển thị
            $table->timestamps();
        });

        // 2. Bảng san_pham (Products / Cakes)
        Schema::create('san_pham', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('loai_id');
            $table->string('ten_san_pham', 150);
            $table->string('slug', 150)->unique();
            $table->decimal('gia_ban', 15, 2)->default(0);
            $table->string('hinh_anh', 255)->nullable();
            $table->text('mo_ta')->nullable();
            $table->string('don_vi_tinh', 20)->default('Cái');
            $table->tinyInteger('trang_thai')->default(1); // 0: Ngừng bán, 1: Đang kinh doanh
            $table->timestamps();

            $table->foreign('loai_id')->references('id')->on('loai_san_pham')->onDelete('cascade');
        });

        // 3. Bảng lich_su_gia_san_pham (Price History & Discount Tracker)
        Schema::create('lich_su_gia_san_pham', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sanpham_id');
            $table->decimal('gia_cu', 15, 2)->nullable();
            $table->decimal('gia_moi', 15, 2);
            $table->float('phan_tram_giam')->default(0);
            $table->string('ly_do', 255)->nullable();
            $table->unsignedBigInteger('nguoidung_id')->nullable();
            $table->timestamp('ngay_ap_dung')->useCurrent();
            $table->timestamps();

            $table->foreign('sanpham_id')->references('id')->on('san_pham')->onDelete('cascade');
            $table->foreign('nguoidung_id')->references('id')->on('nguoidung')->onDelete('set null');
        });

        // 4. Bảng tonkho_chinhanh (Branch Inventory Stock)
        Schema::create('tonkho_chinhanh', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cuahang_id');
            $table->unsignedInteger('sanpham_id');
            $table->integer('so_luong_ton')->default(0);
            $table->timestamps();

            $table->unique(['cuahang_id', 'sanpham_id']);
            $table->foreign('cuahang_id')->references('id')->on('cuahang')->onDelete('cascade');
            $table->foreign('sanpham_id')->references('id')->on('san_pham')->onDelete('cascade');
        });

        // 5. Bảng wishlist (Wishlist)
        Schema::create('wishlist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nguoidung_id');
            $table->unsignedInteger('sanpham_id');
            $table->timestamps();

            $table->unique(['nguoidung_id', 'sanpham_id']);
            $table->foreign('nguoidung_id')->references('id')->on('nguoidung')->onDelete('cascade');
            $table->foreign('sanpham_id')->references('id')->on('san_pham')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist');
        Schema::dropIfExists('tonkho_chinhanh');
        Schema::dropIfExists('lich_su_gia_san_pham');
        Schema::dropIfExists('san_pham');
        Schema::dropIfExists('loai_san_pham');
    }
};
