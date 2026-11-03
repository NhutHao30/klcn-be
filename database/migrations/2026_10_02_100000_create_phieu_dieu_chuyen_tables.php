<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Điều chuyển kho v2:
     * - yeucaudieuchuyen: Bỏ cuaHangXuatId (vì chưa biết ai xuất), thêm hinhThucVanChuyen, thêm cột mới
     * - phieudieuchuyen: Bảng mới cho phiếu điều chuyển (1 yêu cầu có thể có nhiều phiếu từ nhiều chi nhánh)
     * - chitiet_phieu_dieuchuyen: Chi tiết phiếu (sản phẩm + số lượng xuất/nhận)
     */
    public function up(): void
    {
        // 1. Alter yeucaudieuchuyen: make cuaHangXuatId nullable (request doesn't know source yet)
        //    Add new status field as varchar for descriptive statuses
        Schema::table('yeucaudieuchuyen', function (Blueprint $table) {
            // Make cuaHangXuatId nullable since request doesn't specify source store
            $table->unsignedInteger('cuaHangXuatId')->nullable()->change();
            // Rename soLuongChuyen -> soLuongYeuCau in chitiet
        });

        // Rename columns in chitietdieuchuyen for clarity
        if (Schema::hasColumn('chitietdieuchuyen', 'soLuongChuyen')) {
            Schema::table('chitietdieuchuyen', function (Blueprint $table) {
                $table->renameColumn('soLuongChuyen', 'soLuongYeuCau');
            });
        }

        // 2. Create phieudieuchuyen (transfer voucher) table
        if (!Schema::hasTable('phieudieuchuyen')) {
            Schema::create('phieudieuchuyen', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('yeuCauId');
                $table->unsignedInteger('cuaHangXuatId'); // store that provides goods
                $table->unsignedInteger('cuaHangNhapId'); // store that requested goods
                $table->unsignedBigInteger('nguoiTaoId'); // person who created the transfer
                $table->string('trangThai', 50)->default('Đang xử lý');
                // Đang xử lý -> Đang vận chuyển -> Đã nhận hàng / Hủy
                $table->string('hinhThucVanChuyen', 50)->nullable(); // 'Tự vận chuyển' or 'Giao hàng nhanh (GHN)'
                $table->string('maVanDon', 100)->nullable();
                $table->string('nguoiVanChuyen', 100)->nullable();
                $table->decimal('phiVanChuyen', 12, 0)->default(0);
                $table->text('ghiChu')->nullable();
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();

                $table->foreign('yeuCauId')->references('id')->on('yeucaudieuchuyen');
                $table->foreign('cuaHangXuatId')->references('id')->on('cuahang');
                $table->foreign('cuaHangNhapId')->references('id')->on('cuahang');
                $table->foreign('nguoiTaoId')->references('id')->on('nguoidung');
            });
        }

        // 3. Create chitiet_phieu_dieuchuyen (transfer voucher details)
        if (!Schema::hasTable('chitiet_phieu_dieuchuyen')) {
            Schema::create('chitiet_phieu_dieuchuyen', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('phieuId');
                $table->unsignedInteger('sanPhamId');
                $table->integer('soLuongXuat')->default(0);
                $table->integer('soLuongNhan')->nullable();
                $table->string('lyDoHaoHut', 255)->nullable();
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();

                $table->foreign('phieuId')->references('id')->on('phieudieuchuyen');
                $table->foreign('sanPhamId')->references('id')->on('sanpham');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chitiet_phieu_dieuchuyen');
        Schema::dropIfExists('phieudieuchuyen');

        if (Schema::hasColumn('chitietdieuchuyen', 'soLuongYeuCau')) {
            Schema::table('chitietdieuchuyen', function (Blueprint $table) {
                $table->renameColumn('soLuongYeuCau', 'soLuongChuyen');
            });
        }
    }
};
