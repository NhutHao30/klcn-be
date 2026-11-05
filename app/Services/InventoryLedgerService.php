<?php

namespace App\Services;

use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryLedgerService
{
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
            if ($soLuongTonSau === null) {
                $stock = TonKhoChiNhanh::where('cuaHangId', $cuaHangId)
                    ->where('sanPhamId', $sanPhamId)
                    ->first();
                $soLuongTonSau = $stock ? (int)$stock->soLuongTon : 0;
            }

            return NhatKyKho::create([
                'cuaHangId' => $cuaHangId,
                'sanPhamId' => $sanPhamId,
                'loaiBienDong' => $loaiBienDong,
                'soLuongThayDoi' => $soLuongThayDoi,
                'soLuongTonSau' => $soLuongTonSau,
                'maThamChieu' => $maThamChieu,
            ]);
        } catch (\Throwable $e) {
            Log::warning("InventoryLedgerService::record error: " . $e->getMessage());
            return null;
        }
    }
}
