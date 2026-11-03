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
        $ycTable = Schema::hasTable('yeuCauDieuChuyen') ? 'yeuCauDieuChuyen' : (Schema::hasTable('yeucaudieuchuyen') ? 'yeucaudieuchuyen' : 'yeucau_dieuchuyen');
        $ctTable = Schema::hasTable('chiTietDieuChuyen') ? 'chiTietDieuChuyen' : (Schema::hasTable('chitietdieuchuyen') ? 'chitietdieuchuyen' : 'chitiet_dieuchuyen');
        $spTable = Schema::hasTable('sanPham') ? 'sanPham' : (Schema::hasTable('sanpham') ? 'sanpham' : 'san_pham');

        // 1. Alter yeucaudieuchuyen
        if (Schema::hasTable($ycTable)) {
            Schema::table($ycTable, function (Blueprint $table) use ($ycTable) {
                if (Schema::hasColumn($ycTable, 'cuaHangXuatId')) {
                    $table->unsignedInteger('cuaHangXuatId')->nullable()->change();
                } elseif (Schema::hasColumn($ycTable, 'cuahang_xuat_id')) {
                    $table->unsignedInteger('cuahang_xuat_id')->nullable()->change();
                }
            });
        }

        // Rename columns in chitietdieuchuyen for clarity
        if (Schema::hasTable($ctTable)) {
            if (Schema::hasColumn($ctTable, 'soLuongChuyen') && !Schema::hasColumn($ctTable, 'soLuongYeuCau')) {
                Schema::table($ctTable, function (Blueprint $table) {
                    $table->renameColumn('soLuongChuyen', 'soLuongYeuCau');
                });
            } elseif (Schema::hasColumn($ctTable, 'so_luong_chuyen') && !Schema::hasColumn($ctTable, 'soLuongYeuCau')) {
                Schema::table($ctTable, function (Blueprint $table) {
                    $table->renameColumn('so_luong_chuyen', 'soLuongYeuCau');
                });
            }
        }

        // 2. Create phieudieuchuyen (transfer voucher) table
        if (!Schema::hasTable('phieudieuchuyen')) {
            Schema::create('phieudieuchuyen', function (Blueprint $table) use ($ycTable) {
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

                $table->foreign('yeuCauId')->references('id')->on($ycTable);
                $table->foreign('cuaHangXuatId')->references('id')->on('cuahang');
                $table->foreign('cuaHangNhapId')->references('id')->on('cuahang');
                $table->foreign('nguoiTaoId')->references('id')->on('nguoidung');
            });
        }

        // 3. Create chitiet_phieu_dieuchuyen (transfer voucher details)
        if (!Schema::hasTable('chitiet_phieu_dieuchuyen')) {
            Schema::create('chitiet_phieu_dieuchuyen', function (Blueprint $table) use ($spTable) {
                $table->id();
                $table->unsignedBigInteger('phieuId');
                $table->unsignedInteger('sanPhamId');
                $table->integer('soLuongXuat')->default(0);
                $table->integer('soLuongNhan')->nullable();
                $table->string('lyDoHaoHut', 255)->nullable();
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();

                $table->foreign('phieuId')->references('id')->on('phieudieuchuyen');
                $table->foreign('sanPhamId')->references('id')->on($spTable);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chitiet_phieu_dieuchuyen');
        Schema::dropIfExists('phieudieuchuyen');

        $ctTable = Schema::hasTable('chiTietDieuChuyen') ? 'chiTietDieuChuyen' : (Schema::hasTable('chitietdieuchuyen') ? 'chitietdieuchuyen' : 'chitiet_dieuchuyen');
        if (Schema::hasTable($ctTable) && Schema::hasColumn($ctTable, 'soLuongYeuCau')) {
            Schema::table($ctTable, function (Blueprint $table) {
                $table->renameColumn('soLuongYeuCau', 'soLuongChuyen');
            });
        }
    }
};
