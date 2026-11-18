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
        // 1. nha_cung_cap (Suppliers)
        Schema::create('nha_cung_cap', function (Blueprint $table) {
            $table->id();
            $table->string('ten_ncc', 150);
            $table->string('so_dien_thoai', 15)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('dia_chi', 255)->nullable();
            $table->tinyInteger('trang_thai')->default(1)->comment('0: Ngung_coop, 1: Dang_coop');
            $table->timestamps();
        });

        // 2. chu_ky_so (Digital Signatures - Trình ký Đa bên 1-N)
        Schema::create('chu_ky_so', function (Blueprint $table) {
            $table->id();
            $table->string('ma_chu_ky', 50)->unique();
            $table->tinyInteger('loai_tai_lieu')->comment('1: HoaDonNhap, 2: BienBanHuy, 3: PhieuChuyen');
            $table->unsignedBigInteger('tai_lieu_id');
            $table->tinyInteger('vai_tro_ky')->comment('1: KeToan, 2: QuanLyKho, 3: ChuCuaHang, 4: NhaCungCap');
            $table->foreignId('nguoi_ky_id')->nullable()->constrained('nguoidung')->onDelete('set null');
            $table->string('ten_nguoi_ky', 150)->nullable();
            $table->text('digital_signature_hash')->nullable();
            $table->text('public_key')->nullable();
            $table->tinyInteger('trang_thai')->default(0)->comment('0: Cho_ky, 1: Da_ky, 2: Tu_choi');
            $table->dateTime('ngay_ky')->nullable();
            $table->string('ghi_chu', 255)->nullable();
            $table->timestamps();
        });

        // 3. hoadon_nhap (Goods Import Invoices - Bắt buộc 4 chữ ký)
        Schema::create('hoadon_nhap', function (Blueprint $table) {
            $table->id();
            $table->string('ma_hoadon_nhap', 50)->unique();
            $table->foreignId('nhacungcap_id')->constrained('nha_cung_cap')->onDelete('cascade');
            $table->unsignedInteger('cuahang_id');
            $table->foreign('cuahang_id')->references('id')->on('cuahang')->onDelete('cascade');
            $table->foreignId('nguoi_nhap_id')->constrained('nguoidung')->onDelete('cascade');
            $table->dateTime('ngay_nhap');
            $table->decimal('tong_tien', 15, 2)->default(0);
            $table->text('ghi_chu')->nullable();
            $table->tinyInteger('trang_thai')->default(0)->comment('0: Cho_ky_du_4_ben, 1: Da_ky_du_va_nhap_kho, 2: Da_huy');
            $table->timestamps();
        });

        // 4. lo_hang (Batches & Product Expiry HSD)
        Schema::create('lo_hang', function (Blueprint $table) {
            $table->id();
            $table->string('ma_lo_hang', 50)->unique();
            $table->unsignedInteger('sanpham_id');
            $table->foreign('sanpham_id')->references('id')->on('san_pham')->onDelete('cascade');
            $table->unsignedInteger('cuahang_id');
            $table->foreign('cuahang_id')->references('id')->on('cuahang')->onDelete('cascade');
            $table->foreignId('nhacungcap_id')->nullable()->constrained('nha_cung_cap')->onDelete('set null');
            $table->integer('so_luong_nhap');
            $table->integer('so_luong_con');
            $table->date('ngay_san_xuat')->nullable();
            $table->date('hsd')->comment('Han su dung bánh');
            $table->decimal('gia_nhap', 12, 2)->default(0);
            $table->tinyInteger('trang_thai')->default(0)->comment('0: Con_hang, 1: Het_hang, 2: Het_han, 3: Da_huy');
            $table->timestamps();
        });

        // 5. chitiet_hoadon_nhap (Import Details)
        Schema::create('chitiet_hoadon_nhap', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hoadon_nhap_id')->constrained('hoadon_nhap')->onDelete('cascade');
            $table->foreignId('lohang_id')->nullable()->constrained('lo_hang')->onDelete('set null');
            $table->unsignedInteger('sanpham_id');
            $table->foreign('sanpham_id')->references('id')->on('san_pham')->onDelete('cascade');
            $table->integer('so_luong');
            $table->decimal('don_gia_nhap', 12, 2);
            $table->decimal('thanh_tien', 15, 2);
        });

        // 6. bien_ban_huy (Disposal Minutes - Bắt buộc 2 chữ ký)
        Schema::create('bien_ban_huy', function (Blueprint $table) {
            $table->id();
            $table->string('ma_bien_ban', 50)->unique();
            $table->unsignedInteger('cuahang_id');
            $table->foreign('cuahang_id')->references('id')->on('cuahang')->onDelete('cascade');
            $table->foreignId('nguoi_lap_id')->constrained('nguoidung')->onDelete('cascade');
            $table->dateTime('ngay_lap');
            $table->string('ly_do', 255);
            $table->decimal('tong_gia_tri_huy', 15, 2)->default(0);
            $table->tinyInteger('trang_thai')->default(0)->comment('0: Cho_ky_du_2_ben, 1: Da_ky_du_va_xac_nhan_huy, 2: Da_huy_bo');
            $table->timestamps();
        });

        // 7. chitiet_bien_ban_huy (Disposal Details)
        Schema::create('chitiet_bien_ban_huy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_ban_huy_id')->constrained('bien_ban_huy')->onDelete('cascade');
            $table->foreignId('lohang_id')->constrained('lo_hang')->onDelete('cascade');
            $table->unsignedInteger('sanpham_id');
            $table->foreign('sanpham_id')->references('id')->on('san_pham')->onDelete('cascade');
            $table->integer('so_luong_huy');
            $table->decimal('don_gia', 12, 2);
            $table->decimal('thanh_tien', 15, 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chitiet_bien_ban_huy');
        Schema::dropIfExists('bien_ban_huy');
        Schema::dropIfExists('chitiet_hoadon_nhap');
        Schema::dropIfExists('lo_hang');
        Schema::dropIfExists('hoadon_nhap');
        Schema::dropIfExists('chu_ky_so');
        Schema::dropIfExists('nha_cung_cap');
    }
};
