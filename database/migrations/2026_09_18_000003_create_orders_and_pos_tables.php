<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Bảng gio_hang (Cart)
        Schema::create('gio_hang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nguoidung_id')->unique();
            $table->timestamps();

            $table->foreign('nguoidung_id')->references('id')->on('nguoidung')->onDelete('cascade');
        });

        // 2. Bảng chitiet_gio_hang (Cart Items)
        Schema::create('chitiet_gio_hang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('giohang_id');
            $table->unsignedInteger('sanpham_id');
            $table->integer('so_luong')->default(1);
            $table->timestamps();

            $table->foreign('giohang_id')->references('id')->on('gio_hang')->onDelete('cascade');
            $table->foreign('sanpham_id')->references('id')->on('san_pham')->onDelete('cascade');
        });

        // 3. Bảng voucher (Promotions & Coupons)
        Schema::create('voucher', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ma_voucher', 30)->unique();
            $table->tinyInteger('loai_giam_gia')->default(0); // 0: Giảm %, 1: Giảm số tiền cố định
            $table->decimal('gia_tri_giam', 15, 2);
            $table->decimal('don_hang_toi_thieu', 15, 2)->default(0);
            $table->decimal('giam_toi_da', 15, 2)->nullable();
            $table->integer('so_luong')->default(0);
            $table->integer('da_su_dung')->default(0);
            $table->dateTime('ngay_bat_dau');
            $table->dateTime('ngay_ket_thuc');
            $table->tinyInteger('trang_thai')->default(1); // 0: Khóa, 1: Đang hoạt động
            $table->timestamps();
        });

        // 4. Bảng don_hang (Orders & Invoices - Unified Online & POS)
        Schema::create('don_hang', function (Blueprint $table) {
            $table->id();
            $table->string('ma_don_hang', 30)->unique();
            $table->unsignedBigInteger('nguoidung_id')->nullable();
            $table->unsignedInteger('cuahang_id');
            $table->unsignedInteger('voucher_id')->nullable();
            $table->tinyInteger('loai_don_hang')->default(0); // 0: Online, 1: POS quầy
            $table->decimal('tong_tien_hang', 15, 2)->default(0);
            $table->decimal('so_tien_giam', 15, 2)->default(0);
            $table->decimal('phi_van_chuyen', 15, 2)->default(0);
            $table->decimal('tong_thanhtoan', 15, 2)->default(0);
            $table->tinyInteger('phuong_thuc_thanhtoan')->default(0); // 0: CASH/COD, 1: VNPAY, 2: MOMO
            $table->tinyInteger('trang_thai_thanhtoan')->default(0); // 0: CHUA_THANH_TOAN, 1: DA_THANH_TOAN, 2: HOAN_TIEN
            $table->tinyInteger('trang_thai_don_hang')->default(0); // 0: CHO_XAC_NHAN, 1: CHUAN_BI_HANG, 2: DA_GIAO_GHN, 3: DANG_VAN_CHUYEN, 4: DANG_GIAO, 5: DA_GIAO_THANH_CONG, 6: DA_HUY
            $table->string('ma_van_don_ghn', 50)->nullable();
            $table->string('ghn_status_code', 50)->nullable();
            $table->string('dia_chi_giao_hang', 255)->nullable();
            $table->text('ghi_chu')->nullable();
            $table->timestamps();

            $table->foreign('nguoidung_id')->references('id')->on('nguoidung')->onDelete('set null');
            $table->foreign('cuahang_id')->references('id')->on('cuahang');
            $table->foreign('voucher_id')->references('id')->on('voucher')->onDelete('set null');
        });

        // 5. Bảng chitiet_don_hang (Order Items)
        Schema::create('chitiet_don_hang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('donhang_id');
            $table->unsignedInteger('sanpham_id');
            $table->integer('so_luong')->default(1);
            $table->decimal('don_gia', 15, 2);
            $table->decimal('thanh_tien', 15, 2);
            $table->timestamps();

            $table->foreign('donhang_id')->references('id')->on('don_hang')->onDelete('cascade');
            $table->foreign('sanpham_id')->references('id')->on('san_pham');
        });

        // 6. Bảng lich_su_trang_thai_don_hang (GHN Webhook Order Status Timeline)
        Schema::create('lich_su_trang_thai_don_hang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('donhang_id');
            $table->tinyInteger('trang_thai_cu')->nullable();
            $table->tinyInteger('trang_thai_moi');
            $table->string('ghn_status_code', 50)->nullable();
            $table->string('mo_ta_chi_tiet', 255)->nullable();
            $table->tinyInteger('nguon_cap_nhat')->default(0); // 0: GHN Webhook, 1: Hệ thống, 2: Thủ công
            $table->timestamps();

            $table->foreign('donhang_id')->references('id')->on('don_hang')->onDelete('cascade');
        });

        // 7. Bảng pos_shifts (POS Shifts & Cash Registers)
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cuahang_id');
            $table->unsignedBigInteger('nhanvien_id');
            $table->timestamp('thoi_gian_mo_ca')->useCurrent();
            $table->timestamp('thoi_gian_dong_ca')->nullable();
            $table->decimal('tien_mat_dau_ca', 15, 2)->default(0);
            $table->decimal('tien_mat_sach', 15, 2)->default(0);
            $table->decimal('tien_mat_thuc_te', 15, 2)->nullable();
            $table->decimal('chenh_lech', 15, 2)->nullable();
            $table->tinyInteger('trang_thai')->default(0); // 0: Ca đang mở, 1: Ca đã đóng
            $table->timestamps();

            $table->foreign('cuahang_id')->references('id')->on('cuahang');
            $table->foreign('nhanvien_id')->references('id')->on('nguoidung');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('lich_su_trang_thai_don_hang');
        Schema::dropIfExists('chitiet_don_hang');
        Schema::dropIfExists('don_hang');
        Schema::dropIfExists('voucher');
        Schema::dropIfExists('chitiet_gio_hang');
        Schema::dropIfExists('gio_hang');
    }
};
