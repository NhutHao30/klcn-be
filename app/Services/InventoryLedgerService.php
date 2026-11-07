<?php

namespace App\Services;

use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InventoryLedgerService
{
    private static bool $schemaChecked = false;

    /**
     * Tự động đồng bộ tên cột bảng nhatKyKho sang camelCase nếu DB còn giữ tên cột snake_case cũ
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        $tablesMap = [
            'nhatKyKho' => [
                'cuahang_id' => 'cuaHangId',
                'sanpham_id' => 'sanPhamId',
                'loai_bien_dong' => 'loaiBienDong',
                'so_luong_thay_doi' => 'soLuongThayDoi',
                'so_luong_ton_sau' => 'soLuongTonSau',
                'ma_tham_chieu' => 'maThamChieu',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'hoaDonNhap' => [
                'ma_hoadon_nhap' => 'maHoaDonNhap',
                'nhacungcap_id' => 'nhaCungCapId',
                'cuahang_id' => 'cuaHangId',
                'nguoi_nhap_id' => 'nguoiNhapId',
                'ngay_nhap' => 'ngayNhap',
                'tong_tien' => 'tongTien',
                'ghi_chu' => 'ghiChu',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'loHang' => [
                'ma_lo_hang' => 'maLoHang',
                'sanpham_id' => 'sanPhamId',
                'cuahang_id' => 'cuaHangId',
                'nhacungcap_id' => 'nhaCungCapId',
                'so_luong_nhap' => 'soLuongNhap',
                'so_luong_con' => 'soLuongCon',
                'ngay_san_xuat' => 'ngaySanXuat',
                'han_su_dung' => 'hsd',
                'hanSuDung' => 'hsd',
                'gia_nhap' => 'giaNhap',
                'don_gia_nhap' => 'giaNhap',
                'donGiaNhap' => 'giaNhap',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'chiTietHoaDonNhap' => [
                'hoadon_nhap_id' => 'hoaDonNhapId',
                'lohang_id' => 'loHangId',
                'sanpham_id' => 'sanPhamId',
                'so_luong' => 'soLuong',
                'so_luong_nhap' => 'soLuong',
                'soLuongNhap' => 'soLuong',
                'don_gia_nhap' => 'donGiaNhap',
                'thanh_tien' => 'thanhTien',
            ],
            'tonKhoChiNhanh' => [
                'cuahang_id' => 'cuaHangId',
                'sanpham_id' => 'sanPhamId',
                'so_luong_ton' => 'soLuongTon',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'bienBanHuy' => [
                'ma_bien_ban' => 'maBienBan',
                'cuahang_id' => 'cuaHangId',
                'nguoi_lap_id' => 'nguoiLapId',
                'ngay_lap' => 'ngayLap',
                'ly_do' => 'lyDo',
                'tong_gia_tri_huy' => 'tongGiaTriHuy',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'chiTietBienBanHuy' => [
                'bien_ban_huy_id' => 'bienBanHuyId',
                'lohang_id' => 'loHangId',
                'sanpham_id' => 'sanPhamId',
                'so_luong_huy' => 'soLuongHuy',
                'don_gia' => 'donGia',
                'thanh_tien' => 'thanhTien',
            ],
            'chuKySo' => [
                'ma_chu_ky' => 'maChuKy',
                'loai_tai_lieu' => 'loaiTaiLieu',
                'tai_lieu_id' => 'taiLieuId',
                'vai_tro_ky' => 'vaiTroKy',
                'nguoi_ky_id' => 'nguoiKyId',
                'ten_nguoi_ky' => 'tenNguoiKy',
                'digital_signature_hash' => 'digitalSignatureHash',
                'public_key' => 'publicKey',
                'trang_thai' => 'trangThai',
                'ngay_ky' => 'ngayKy',
                'ghi_chu' => 'ghiChu',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
        ];

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } catch (\Throwable $eFk) {}

        $tablesRequiringCuaHangId = ['nhatKyKho', 'hoaDonNhap', 'loHang', 'tonKhoChiNhanh', 'bienBanHuy'];

        foreach ($tablesMap as $targetTable => $colMap) {
            try {
                if (!Schema::hasTable($targetTable)) {
                    continue;
                }
                foreach ($colMap as $oldCol => $newCol) {
                    if (Schema::hasColumn($targetTable, $oldCol) && !Schema::hasColumn($targetTable, $newCol)) {
                        try {
                            DB::statement("ALTER TABLE `{$targetTable}` RENAME COLUMN `{$oldCol}` TO `{$newCol}`");
                        } catch (\Throwable $eAlter) {
                            Log::warning("Could not rename {$targetTable}.{$oldCol} to {$newCol}: " . $eAlter->getMessage());
                        }
                    }
                }
                if (in_array($targetTable, $tablesRequiringCuaHangId, true) && !Schema::hasColumn($targetTable, 'cuaHangId')) {
                    try {
                        DB::statement("ALTER TABLE `{$targetTable}` ADD COLUMN `cuaHangId` INT UNSIGNED NULL DEFAULT 1");
                    } catch (\Throwable $eAddCol) {
                        Log::warning("Could not add cuaHangId to {$targetTable}: " . $eAddCol->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("InventoryLedgerService::ensureSchema error on {$targetTable}: " . $e->getMessage());
            }
        }

        try {
            if (Schema::hasTable('nhatKyKho')) {
                DB::statement("CREATE OR REPLACE VIEW `nhatkykho` AS SELECT * FROM `nhatKyKho`");
            }
        } catch (\Throwable $eView) {}

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } catch (\Throwable $eFk) {}
    }

    /**
     * Ghi nhận biến động vào Sổ cái tồn kho (Inventory Movement Ledger)
     *
     * @param int $cuaHangId      ID chi nhánh
     * @param int $sanPhamId      ID sản phẩm
     * @param int $loaiBienDong   0: Bán hàng (POS), 1: Bán hàng (Online), 2: Chuyển kho (Xuất), 3: Chuyển kho (Nhập), 4: Nhập lô mới, 5: Xuất hủy hàng
     * @param int $soLuongThayDoi Số lượng biến động (+ hoặc -)
     * @param string|null $maThamChieu Mã hóa đơn, mã phiếu chuyển, mã lô, mã biên bản hủy
     * @param int|null $soLuongTonSau Tồn kho thực tế sau biến động (nếu null sẽ tự tra cứu)
     * @return NhatKyKho|null
     */
    public static function record(
        int $cuaHangId,
        int $sanPhamId,
        int $loaiBienDong,
        int $soLuongThayDoi,
        ?string $maThamChieu = null,
        ?int $soLuongTonSau = null
    ): ?NhatKyKho {
        try {
            self::ensureSchema();

            if ($soLuongTonSau === null) {
                $stock = TonKhoChiNhanh::where('cuaHangId', $cuaHangId)
                    ->where('sanPhamId', $sanPhamId)
                    ->first();
                $soLuongTonSau = $stock ? (int)$stock->soLuongTon : 0;
            }

            if (Schema::hasColumn('nhatKyKho', 'cuaHangId')) {
                return NhatKyKho::create([
                    'cuaHangId' => $cuaHangId,
                    'sanPhamId' => $sanPhamId,
                    'loaiBienDong' => $loaiBienDong,
                    'soLuongThayDoi' => $soLuongThayDoi,
                    'soLuongTonSau' => $soLuongTonSau,
                    'maThamChieu' => $maThamChieu,
                ]);
            }

            // Fallback nếu DB không cho phép ALTER TABLE và vẫn dùng snake_case
            DB::table('nhatKyKho')->insert([
                'cuahang_id' => $cuaHangId,
                'sanpham_id' => $sanPhamId,
                'loai_bien_dong' => $loaiBienDong,
                'so_luong_thay_doi' => $soLuongThayDoi,
                'so_luong_ton_sau' => $soLuongTonSau,
                'ma_tham_chieu' => $maThamChieu,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::warning("InventoryLedgerService::record error: " . $e->getMessage());
            return null;
        }
    }
}

