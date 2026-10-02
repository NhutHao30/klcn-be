<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Bảng danhgia_sanpham (Product Reviews & Ratings)
        Schema::create('danhgia_sanpham', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('donhang_id');
            $table->unsignedInteger('sanpham_id');
            $table->unsignedBigInteger('nguoidung_id');
            $table->tinyInteger('so_sao')->default(5); // 1 đến 5 sao
            $table->text('noi_dung')->nullable();
            $table->string('hinh_anh_kem_theo', 255)->nullable();
            $table->timestamps();

            $table->foreign('donhang_id')->references('id')->on('don_hang')->onDelete('cascade');
            $table->foreign('sanpham_id')->references('id')->on('san_pham')->onDelete('cascade');
            $table->foreign('nguoidung_id')->references('id')->on('nguoidung')->onDelete('cascade');
        });

        // 2. Bảng yeucau_dieuchuyen (Inter-branch Transfer Requests)
        Schema::create('yeucau_dieuchuyen', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cuahang_xuat_id');
            $table->unsignedInteger('cuahang_nhap_id');
            $table->unsignedBigInteger('nguoi_tao_id');
            $table->unsignedBigInteger('nguoi_duyet_id')->nullable();
            $table->tinyInteger('trang_thai')->default(0); // 0: Chờ duyệt, 1: Đang vận chuyển, 2: Đã nhận thành công, 3: Từ chối/Hủy
            $table->text('ghi_chu')->nullable();
            $table->timestamps();

            $table->foreign('cuahang_xuat_id')->references('id')->on('cuahang');
            $table->foreign('cuahang_nhap_id')->references('id')->on('cuahang');
            $table->foreign('nguoi_tao_id')->references('id')->on('nguoidung');
            $table->foreign('nguoi_duyet_id')->references('id')->on('nguoidung')->onDelete('set null');
        });

        // 3. Bảng chitiet_dieuchuyen (Transfer Order Details)
        Schema::create('chitiet_dieuchuyen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('yeucau_id');
            $table->unsignedInteger('sanpham_id');
            $table->integer('so_luong_chuyen');
            $table->integer('so_luong_thuc_nhan')->nullable();
            $table->timestamps();

            $table->foreign('yeucau_id')->references('id')->on('yeucau_dieuchuyen')->onDelete('cascade');
            $table->foreign('sanpham_id')->references('id')->on('san_pham');
        });

        // 4. Bảng nhatky_kho (Inventory Movement Ledger)
        Schema::create('nhatky_kho', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cuahang_id');
            $table->unsignedInteger('sanpham_id');
            $table->tinyInteger('loai_bien_dong')->default(0); // 0: Bán POS, 1: Bán Online, 2: Chuyển kho xuất, 3: Chuyển kho nhập, 4: Nhập hàng mới
            $table->integer('so_luong_thay_doi');
            $table->integer('so_luong_ton_sau');
            $table->string('ma_tham_chieu', 50)->nullable();
            $table->timestamps();

            $table->foreign('cuahang_id')->references('id')->on('cuahang');
            $table->foreign('sanpham_id')->references('id')->on('san_pham');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhatky_kho');
        Schema::dropIfExists('chitiet_dieuchuyen');
        Schema::dropIfExists('yeucau_dieuchuyen');
        Schema::dropIfExists('danhgia_sanpham');
    }
};
