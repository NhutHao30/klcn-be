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

        try {
            $table = Schema::hasTable('nhatKyKho') ? 'nhatKyKho' : (Schema::hasTable('nhatkykho') ? 'nhatkykho' : (Schema::hasTable('nhatky_kho') ? 'nhatky_kho' : null));
            if (!$table) {
                return;
            }

            $colMap = [
                'cuahang_id' => 'cuaHangId',
                'sanpham_id' => 'sanPhamId',
                'loai_bien_dong' => 'loaiBienDong',
                'so_luong_thay_doi' => 'soLuongThayDoi',
                'so_luong_ton_sau' => 'soLuongTonSau',
                'ma_tham_chieu' => 'maThamChieu',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ];

            foreach ($colMap as $oldCol => $newCol) {
                if (Schema::hasColumn($table, $oldCol) && !Schema::hasColumn($table, $newCol)) {
                    try {
                        DB::statement("ALTER TABLE `{$table}` RENAME COLUMN `{$oldCol}` TO `{$newCol}`");
                    } catch (\Throwable $eAlter) {
                        Log::warning("Could not rename {$table}.{$oldCol} to {$newCol}: " . $eAlter->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("InventoryLedgerService::ensureSchema error: " . $e->getMessage());
        }
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

