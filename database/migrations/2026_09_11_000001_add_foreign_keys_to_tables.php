<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to add missing Foreign Keys across all tables in MySQL.
     */
    public function up(): void
    {
        $statements = [
            // 1. nhanvien -> cuahang
            "ALTER TABLE `nhanvien` ADD CONSTRAINT `fk_nhanvien_cuahang` FOREIGN KEY (`MACUAHANG`) REFERENCES `cuahang` (`MACUAHANG`) ON DELETE SET NULL",
            // 2. khachhang -> cuahang
            "ALTER TABLE `khachhang` ADD CONSTRAINT `fk_khachhang_cuahang` FOREIGN KEY (`MACUAHANG`) REFERENCES `cuahang` (`MACUAHANG`) ON DELETE SET NULL",
            // 3. hdban -> cuahang & khuyenmai
            "ALTER TABLE `hdban` ADD CONSTRAINT `fk_hdban_cuahang` FOREIGN KEY (`MACUAHANG`) REFERENCES `cuahang` (`MACUAHANG`) ON DELETE SET NULL",
            "ALTER TABLE `hdban` ADD CONSTRAINT `fk_hdban_khuyenmai` FOREIGN KEY (`MA_VOUCHER`) REFERENCES `khuyenmai` (`MA_VOUCHER`) ON DELETE SET NULL",
            // 4. tonkho_cuahang -> cuahang & sanpham
            "ALTER TABLE `tonkho_cuahang` ADD CONSTRAINT `fk_tonkho_cuahang` FOREIGN KEY (`MACUAHANG`) REFERENCES `cuahang` (`MACUAHANG`) ON DELETE CASCADE",
            "ALTER TABLE `tonkho_cuahang` ADD CONSTRAINT `fk_tonkho_sanpham` FOREIGN KEY (`MASP`) REFERENCES `sanpham` (`MASP`) ON DELETE CASCADE",
            // 5. khuyenmai_khachhang -> khuyenmai & khachhang & hdban
            "ALTER TABLE `khuyenmai_khachhang` ADD CONSTRAINT `fk_kmkh_khuyenmai` FOREIGN KEY (`MA_VOUCHER`) REFERENCES `khuyenmai` (`MA_VOUCHER`) ON DELETE CASCADE",
            "ALTER TABLE `khuyenmai_khachhang` ADD CONSTRAINT `fk_kmkh_khachhang` FOREIGN KEY (`MAKH`) REFERENCES `khachhang` (`MAKH`) ON DELETE CASCADE",
            "ALTER TABLE `khuyenmai_khachhang` MODIFY COLUMN `MAHD` VARCHAR(50) NULL",
            "ALTER TABLE `khuyenmai_khachhang` ADD CONSTRAINT `fk_kmkh_hdban` FOREIGN KEY (`MAHD`) REFERENCES `hdban` (`MAHD`) ON DELETE SET NULL",
            // 6. dat_hang_truoc -> khachhang & taikhoan & hdban
            "ALTER TABLE `dat_hang_truoc` ADD CONSTRAINT `fk_dht_khachhang` FOREIGN KEY (`MAKH`) REFERENCES `khachhang` (`MAKH`) ON DELETE SET NULL",
            "ALTER TABLE `dat_hang_truoc` ADD CONSTRAINT `fk_dht_taikhoan` FOREIGN KEY (`USERNAME_NV`) REFERENCES `taikhoan` (`USERNAME`) ON DELETE SET NULL",
            "ALTER TABLE `dat_hang_truoc` ADD CONSTRAINT `fk_dht_hdban` FOREIGN KEY (`MAHD_HOAN_THANH`) REFERENCES `hdban` (`MAHD`) ON DELETE SET NULL",
            // 7. chi_tiet_dat_hang_truoc -> sanpham
            "ALTER TABLE `chi_tiet_dat_hang_truoc` ADD CONSTRAINT `fk_ctdht_sanpham` FOREIGN KEY (`MASP`) REFERENCES `sanpham` (`MASP`) ON DELETE SET NULL",
            // 8. lich_su_gia_san_phams -> taikhoan
            "ALTER TABLE `lich_su_gia_san_phams` ADD CONSTRAINT `fk_lsg_taikhoan` FOREIGN KEY (`USERNAME_NV`) REFERENCES `taikhoan` (`USERNAME`) ON DELETE SET NULL",
        ];

        foreach ($statements as $sql) {
            try {
                DB::statement($sql);
            } catch (\Exception $e) {
                // Skips constraint if already exists or invalid state
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $dropStatements = [
            "ALTER TABLE `nhanvien` DROP FOREIGN KEY `fk_nhanvien_cuahang`",
            "ALTER TABLE `khachhang` DROP FOREIGN KEY `fk_khachhang_cuahang`",
            "ALTER TABLE `hdban` DROP FOREIGN KEY `fk_hdban_cuahang`",
            "ALTER TABLE `hdban` DROP FOREIGN KEY `fk_hdban_khuyenmai`",
            "ALTER TABLE `tonkho_cuahang` DROP FOREIGN KEY `fk_tonkho_cuahang`",
            "ALTER TABLE `tonkho_cuahang` DROP FOREIGN KEY `fk_tonkho_sanpham`",
            "ALTER TABLE `khuyenmai_khachhang` DROP FOREIGN KEY `fk_kmkh_khuyenmai`",
            "ALTER TABLE `khuyenmai_khachhang` DROP FOREIGN KEY `fk_kmkh_khachhang`",
            "ALTER TABLE `khuyenmai_khachhang` DROP FOREIGN KEY `fk_kmkh_hdban`",
            "ALTER TABLE `dat_hang_truoc` DROP FOREIGN KEY `fk_dht_khachhang`",
            "ALTER TABLE `dat_hang_truoc` DROP FOREIGN KEY `fk_dht_taikhoan`",
            "ALTER TABLE `dat_hang_truoc` DROP FOREIGN KEY `fk_dht_hdban`",
            "ALTER TABLE `chi_tiet_dat_hang_truoc` DROP FOREIGN KEY `fk_ctdht_sanpham`",
            "ALTER TABLE `lich_su_gia_san_phams` DROP FOREIGN KEY `fk_lsg_taikhoan`",
        ];

        foreach ($dropStatements as $sql) {
            try {
                DB::statement($sql);
            } catch (\Exception $e) {}
        }
    }
};
