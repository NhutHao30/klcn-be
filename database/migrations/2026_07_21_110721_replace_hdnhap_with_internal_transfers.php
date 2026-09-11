<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('chitiethdnhap');
        Schema::dropIfExists('hdnhap');

        if (!Schema::hasTable('yeucau_nhaphang')) {
            Schema::create('yeucau_nhaphang', function (Blueprint $table) {
            $table->id('ID_YEUCAU');
            $table->unsignedBigInteger('MACUAHANG_YEUCAU');
            $table->string('NGUOITAO', 255);
            $table->string('TRANGTHAI', 50)->default('Chờ tiếp nhận'); // Chờ tiếp nhận, Đã được tiếp nhận, Từ chối
            $table->text('GHICHU')->nullable();
            $table->timestamps();

            $table->foreign('MACUAHANG_YEUCAU')->references('MACUAHANG')->on('cuahang');
        });
        }

        if (!Schema::hasTable('chitiet_yeucau')) {
            Schema::create('chitiet_yeucau', function (Blueprint $table) {
            $table->id('ID_CHITIET');
            $table->unsignedBigInteger('ID_YEUCAU');
            $table->string('MASP', 10);
            $table->integer('SOLUONG_YEUCAU')->default(1);
            $table->timestamps();

            $table->foreign('ID_YEUCAU')->references('ID_YEUCAU')->on('yeucau_nhaphang')->onDelete('cascade');
            $table->foreign('MASP')->references('MASP')->on('sanpham');
        });
        }

        if (!Schema::hasTable('phieu_dieuchuyen')) {
            Schema::create('phieu_dieuchuyen', function (Blueprint $table) {
            $table->id('ID_PHIEU');
            $table->unsignedBigInteger('MACUAHANG_XUAT');
            $table->unsignedBigInteger('MACUAHANG_NHAP');
            $table->unsignedBigInteger('ID_YEUCAU')->nullable();
            $table->string('NGUOITAO', 255); // Người xuất kho
            $table->string('NGUOINHAN', 255)->nullable(); // Người xác nhận nhập kho
            $table->string('TRANGTHAI', 50)->default('Đang xử lý'); // Đang xử lý, Đang vận chuyển, Đã nhận hàng, Đã hủy
            $table->string('HINHTHUC_VANCHUYEN', 50)->default('Tự vận chuyển'); // Tự vận chuyển, GHN
            $table->string('MA_VANDON', 100)->nullable(); // Mã đơn GHN nếu có
            $table->string('NGUOI_VANCHUYEN', 255)->nullable(); // Tên nhân viên vận chuyển nếu tự giao
            $table->decimal('PHI_VANCHUYEN', 18, 2)->default(0); // Phí vận chuyển nội bộ
            $table->text('GHICHU')->nullable();
            $table->timestamps();

            $table->foreign('MACUAHANG_XUAT')->references('MACUAHANG')->on('cuahang');
            $table->foreign('MACUAHANG_NHAP')->references('MACUAHANG')->on('cuahang');
            $table->foreign('ID_YEUCAU')->references('ID_YEUCAU')->on('yeucau_nhaphang');
        });
        }

        if (!Schema::hasTable('chitiet_dieuchuyen')) {
            Schema::create('chitiet_dieuchuyen', function (Blueprint $table) {
            $table->id('ID_CHITIET');
            $table->unsignedBigInteger('ID_PHIEU');
            $table->string('MASP', 10);
            $table->integer('SOLUONG_XUAT');
            $table->integer('SOLUONG_NHAN')->nullable();
            $table->string('LYDO_HAOHUT', 255)->nullable();
            $table->timestamps();

            $table->foreign('ID_PHIEU')->references('ID_PHIEU')->on('phieu_dieuchuyen')->onDelete('cascade');
            $table->foreign('MASP')->references('MASP')->on('sanpham');
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chitiet_dieuchuyen');
        Schema::dropIfExists('phieu_dieuchuyen');
        Schema::dropIfExists('chitiet_yeucau');
        Schema::dropIfExists('yeucau_nhaphang');

        // Recreate hdnhap and chitiethdnhap (simplified version just for rollback)
        if (!Schema::hasTable('hdnhap')) {
            Schema::create('hdnhap', function (Blueprint $table) {
            $table->id('MAHDN');
            $table->string('MANCC', 10)->nullable();
            $table->date('NGAYNHAP')->nullable();
            $table->decimal('TONGTIEN', 18, 2)->default(0);
            $table->string('MANV', 20)->nullable();
            $table->timestamps();
        });
        }

        if (!Schema::hasTable('chitiethdnhap')) {
            Schema::create('chitiethdnhap', function (Blueprint $table) {
            $table->unsignedBigInteger('MAHDN');
            $table->string('MASP', 10);
            $table->integer('SOLUONG')->default(0);
            $table->decimal('DONGIANHAP', 18, 2)->default(0);
            $table->decimal('THANHTIEN', 18, 2)->default(0);
            $table->primary(['MAHDN', 'MASP']);
        });
        }
    }
};
