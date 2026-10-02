<?php

namespace App\Services;

use App\Models\LoHang;
use App\Models\BienBanHuy;
use App\Models\TonKhoCuaHang;
use App\Models\InventoryMovement;
use App\Models\CuaHang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoHangService
{
    /**
     * Sinh mã lô tự động: LH-YYYYMMDD-XXXX
     */
    public function generateMaLoHang(): string
    {
        $prefix = 'LH-' . now()->format('Ymd') . '-';
        $last = LoHang::where('MALOHAN', 'like', $prefix . '%')
                      ->orderByDesc('MALOHAN')
                      ->value('MALOHAN');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Tạo lô hàng mới khi nhập hàng.
     *
     * @param array $data {
     *   MAHDNHAP, MASP, MACUAHANG, NGAYNHAP, NGAYSX (nullable),
     *   HSD, SOLUONG_NHAP, DONGIA_NHAP, GHICHU (nullable)
     * }
     * @param string|null $username Người tạo
     * @return LoHang
     */
    public function createLoHang(array $data, ?string $username = null): LoHang
    {
        return DB::transaction(function () use ($data, $username) {
            $malohan = $this->generateMaLoHang();

            $loHang = LoHang::create([
                'MALOHAN'       => $malohan,
                'MAHDNHAP'      => $data['MAHDNHAP'] ?? null,
                'MASP'          => $data['MASP'],
                'MACUAHANG'     => $data['MACUAHANG'],
                'NGAYNHAP'      => $data['NGAYNHAP'] ?? now()->toDateString(),
                'NGAYSX'        => $data['NGAYSX'] ?? null,
                'HSD'           => $data['HSD'],
                'SOLUONG_NHAP'  => $data['SOLUONG_NHAP'],
                'SOLUONG_CON'   => $data['SOLUONG_NHAP'],
                'SOLUONG_BAN'   => 0,
                'SOLUONG_HUY'   => 0,
                'DONGIA_NHAP'   => $data['DONGIA_NHAP'],
                'TRANGTHAI'     => 'con_hang',
                'GHICHU'        => $data['GHICHU'] ?? null,
            ]);

            // Cập nhật tổng tồn kho cửa hàng (MySQL trigger sẽ tự động ghi 1 log duy nhất với đầy đủ type, reference_id, USERNAME)
            $this->updateTonKho(
                $data['MASP'],
                $data['MACUAHANG'],
                $data['SOLUONG_NHAP'],
                'PURCHASE',
                $username,
                $data['MAHDNHAP'] ?? null,
                'HdNhap'
            );

            return $loHang;
        });
    }

    /**
     * FEFO (First Expired, First Out): Chọn các lô để bán theo thứ tự HSD tăng dần.
     * Đặc biệt: với đơn online, kiểm tra HSD còn đủ sau thời gian giao hàng ước tính.
     *
     * @param string $masp
     * @param int    $maCuaHang
     * @param int    $soLuongCan
     * @param int    $shipDays Số ngày giao hàng ước tính (để lọc lô còn HSD khi đến tay khách)
     * @return array  [{malohan, soLuong}] hoặc [] nếu không đủ hàng
     */
    public function getFefoLo(string $masp, int $maCuaHang, int $soLuongCan, int $shipDays = 0): array
    {
        // Ngày tối thiểu HSD phải còn khi hàng đến tay khách
        $minHSD = Carbon::today()->addDays($shipDays);

        $loHangs = LoHang::where('MASP', $masp)
            ->where('MACUAHANG', $maCuaHang)
            ->where('TRANGTHAI', 'con_hang')
            ->where('SOLUONG_CON', '>', 0)
            ->where('HSD', '>=', $minHSD)
            ->orderBy('HSD', 'asc')  // FEFO: hết hạn sớm nhất trước
            ->lockForUpdate()         // Tránh race condition
            ->get();

        $result = [];
        $remaining = $soLuongCan;

        foreach ($loHangs as $lo) {
            if ($remaining <= 0) break;

            $take = min($lo->SOLUONG_CON, $remaining);
            $result[] = [
                'malohan'  => $lo->MALOHAN,
                'soLuong'  => $take,
                'hsd'      => $lo->HSD->toDateString(),
            ];
            $remaining -= $take;
        }

        // Không đủ hàng
        if ($remaining > 0) {
            return [];
        }

        return $result;
    }

    /**
     * Tìm cửa hàng gần nhất có đủ hàng và HSD phù hợp cho đơn online.
     *
     * @param string $masp
     * @param int    $soLuongCan
     * @param int    $shipDays    Ngày giao ước tính
     * @return int|null MACUAHANG phù hợp hoặc null
     */
    public function findBestStoreForOnlineOrder(string $masp, int $soLuongCan, int $shipDays = 2): ?int
    {
        $minHSD = Carbon::today()->addDays($shipDays);

        // Lấy các cửa hàng đang hoạt động có lô còn hàng và HSD đủ
        $stores = LoHang::select('MACUAHANG', DB::raw('SUM(SOLUONG_CON) as total_con'))
            ->where('MASP', $masp)
            ->where('TRANGTHAI', 'con_hang')
            ->where('SOLUONG_CON', '>', 0)
            ->where('HSD', '>=', $minHSD)
            ->whereHas('cuahang', fn($q) => $q->where('TRANGTHAI', 'Đang hoạt động'))
            ->groupBy('MACUAHANG')
            ->having('total_con', '>=', $soLuongCan)
            ->orderByDesc('total_con') // Ưu tiên cửa hàng nhiều hàng nhất
            ->get();

        return $stores->first()?->MACUAHANG;
    }

    /**
     * Trừ số lượng từ lô sau khi bán.
     *
     * @param string $malohan
     * @param int    $soLuong
     * @param string|null $mahd     Mã hóa đơn bán
     * @param string|null $username
     */
    public function consumeLo(string $malohan, int $soLuong, ?string $mahd = null, ?string $username = null): void
    {
        DB::transaction(function () use ($malohan, $soLuong, $mahd, $username) {
            $lo = LoHang::where('MALOHAN', $malohan)->lockForUpdate()->firstOrFail();

            if ($lo->SOLUONG_CON < $soLuong) {
                throw new \RuntimeException("Lô {$malohan} không đủ số lượng. Cần {$soLuong}, còn {$lo->SOLUONG_CON}.");
            }

            $lo->SOLUONG_CON -= $soLuong;
            $lo->SOLUONG_BAN += $soLuong;
            if ($lo->SOLUONG_CON === 0) {
                $lo->TRANGTHAI = 'da_ban_het';
            }
            $lo->save();

            // Cập nhật tồn kho cửa hàng (MySQL trigger sẽ ghi 1 log SALE duy nhất)
            $this->updateTonKho($lo->MASP, $lo->MACUAHANG, -$soLuong, 'SALE', $username, $mahd, 'HdBan');
        });
    }

    /**
     * Job tự động: Quét lô hết hạn, tạo biên bản hủy, trừ tồn kho.
     *
     * @return array {expired_count, total_quantity_disposed}
     */
    public function autoExpireLo(): array
    {
        $expiredLots = LoHang::where('HSD', '<', Carbon::today())
            ->where('TRANGTHAI', 'con_hang')
            ->where('SOLUONG_CON', '>', 0)
            ->get();

        $expiredCount = 0;
        $totalQty = 0;

        foreach ($expiredLots as $lo) {
            DB::transaction(function () use ($lo, &$expiredCount, &$totalQty) {
                $qty = $lo->SOLUONG_CON;

                // Tạo biên bản hủy tự động chờ các bên ký duyệt
                $bbh = BienBanHuy::create([
                    'MALOHAN'      => $lo->MALOHAN,
                    'MACUAHANG'    => $lo->MACUAHANG,
                    'USERNAME'     => null,
                    'SOLUONG_HUY'  => $qty,
                    'LY_DO'        => 'het_han',
                    'GHICHU'       => "Hệ thống tự động loại trừ lô hết hạn (HSD: {$lo->HSD->format('d/m/Y')}). Lập biên bản hủy chờ phê duyệt ký số.",
                    'NGAY_HUY'     => now(),
                    'TRANGTHAI_KY' => 'cho_ky',
                ]);

                // Cập nhật lô: trừ số lượng tồn về 0 lập tức và chuyển sang trạng thái bị hủy
                $lo->SOLUONG_HUY += $qty;
                $lo->SOLUONG_CON  = 0;
                $lo->TRANGTHAI    = 'bi_huy';
                $lo->save();

                // Trừ tồn kho cửa hàng (MySQL trigger sẽ ghi 1 log DAMAGE duy nhất)
                $this->updateTonKho($lo->MASP, $lo->MACUAHANG, -$qty, 'DAMAGE', null, $bbh->MABBHUY ?? $lo->MALOHAN, 'BienBanHuy');

                $expiredCount++;
                $totalQty += $qty;
            });
        }

        return [
            'expired_count'          => $expiredCount,
            'total_quantity_disposed' => $totalQty,
        ];
    }

    /**
     * Lấy danh sách cảnh báo HSD cho dashboard.
     *
     * @param int|null $maCuaHang null = tất cả cửa hàng
     * @return array
     */
    public function getExpiryAlerts(?int $maCuaHang = null): array
    {
        $base = LoHang::with(['sanpham:MASP,TENSP,HINHANH', 'cuahang:MACUAHANG,TENCUAHANG'])
            ->where('SOLUONG_CON', '>', 0);

        if ($maCuaHang) {
            $base->where('MACUAHANG', $maCuaHang);
        }

        $today = Carbon::today();

        // Đã hết hạn nhưng chưa được xử lý (TRANGTHAI vẫn con_hang)
        $daHetHan = (clone $base)
            ->where('TRANGTHAI', 'con_hang')
            ->where('HSD', '<', $today)
            ->orderBy('HSD')
            ->get()
            ->append(['so_ngay_con_lai', 'muc_canh_bao']);

        // Hết hạn trong hôm nay
        $homNay = (clone $base)
            ->where('TRANGTHAI', 'con_hang')
            ->whereDate('HSD', $today)
            ->orderBy('HSD')
            ->get()
            ->append(['so_ngay_con_lai', 'muc_canh_bao']);

        // Hết hạn trong 3 ngày tới (không tính hôm nay)
        $trong3Ngay = (clone $base)
            ->where('TRANGTHAI', 'con_hang')
            ->where('HSD', '>', $today)
            ->where('HSD', '<=', $today->copy()->addDays(3))
            ->orderBy('HSD')
            ->get()
            ->append(['so_ngay_con_lai', 'muc_canh_bao']);

        // Hết hạn trong 7 ngày tới
        $trong7Ngay = (clone $base)
            ->where('TRANGTHAI', 'con_hang')
            ->where('HSD', '>', $today->copy()->addDays(3))
            ->where('HSD', '<=', $today->copy()->addDays(7))
            ->orderBy('HSD')
            ->get()
            ->append(['so_ngay_con_lai', 'muc_canh_bao']);

        return [
            'da_het_han'  => $daHetHan,
            'hom_nay'     => $homNay,
            'trong_3_ngay' => $trong3Ngay,
            'trong_7_ngay' => $trong7Ngay,
            'summary' => [
                'tong_da_het_han'   => $daHetHan->count(),
                'tong_hom_nay'      => $homNay->count(),
                'tong_trong_3_ngay' => $trong3Ngay->count(),
                'tong_trong_7_ngay' => $trong7Ngay->count(),
                'tong_sl_rui_ro'    => $daHetHan->sum('SOLUONG_CON') + $homNay->sum('SOLUONG_CON'),
            ],
        ];
    }

    // ─────────────────────────── Private Helpers ──────────────────────────

    /**
     * Cập nhật tồn kho cửa hàng (cộng hoặc trừ).
     * Đẩy Session Variables cho MySQL Trigger để ghi 1 log duy nhất với đầy đủ type, reference_id, USERNAME.
     */
    private function updateTonKho(
        string $masp,
        int $maCuaHang,
        int $delta,
        string $type = 'ADJUSTMENT',
        ?string $username = null,
        ?string $referenceId = null,
        ?string $referenceType = null
    ): void {
        // Nạp session variables cho MySQL Trigger trg_after_tonkho_update
        DB::statement("SET @inv_type = ?, @inv_ref_type = ?, @inv_ref_id = ?, @inv_username = ?", [
            $type, $referenceType, $referenceId, $username
        ]);

        $exists = DB::table('tonKhoChiNhanh')
            ->where('MACUAHANG', $maCuaHang)
            ->where('MASP', $masp)
            ->exists();

        if ($exists) {
            DB::table('tonKhoChiNhanh')
                ->where('MACUAHANG', $maCuaHang)
                ->where('MASP', $masp)
                ->update([
                    'SOLUONG_TON' => DB::raw("GREATEST(0, SOLUONG_TON + {$delta})"),
                    'updatedAt'  => now(),
                ]);
        } else if ($delta > 0) {
            DB::table('tonKhoChiNhanh')->insert([
                'MACUAHANG'   => $maCuaHang,
                'MASP'        => $masp,
                'SOLUONG_TON' => $delta,
                'createdAt'  => now(),
                'updatedAt'  => now(),
            ]);
            // Do insert không kích hoạt UPDATE trigger nên chủ động ghi 1 log cho bản ghi mới
            $this->recordMovement($masp, $maCuaHang, $type, $delta, $username, $referenceId, $referenceType, null);
        }

        // Đồng bộ tổng tồn kho sang bảng sanpham
        $totalStock = DB::table('tonKhoChiNhanh')->where('MASP', $masp)->sum('SOLUONG_TON');
        DB::table('sanPham')->where('MASP', $masp)->update(['SOLUONG' => max(0, $totalStock)]);

        // Reset session variables
        DB::statement("SET @inv_type = NULL, @inv_ref_type = NULL, @inv_ref_id = NULL, @inv_username = NULL");
    }

    /**
     * Ghi lịch sử biến động kho vào inventory_movements.
     */
    private function recordMovement(
        string $masp,
        int $maCuaHang,
        string $type,
        int $quantity,
        ?string $username,
        ?string $referenceId,
        ?string $referenceType,
        ?string $note = null
    ): void {
        $current = DB::table('tonKhoChiNhanh')
            ->where('MACUAHANG', $maCuaHang)
            ->where('MASP', $masp)
            ->value('SOLUONG_TON') ?? 0;

        InventoryMovement::create([
            'MASP'           => $masp,
            'MACUAHANG'      => $maCuaHang,
            'type'           => $type,
            'quantity'       => $quantity,
            'before_quantity' => $current - $quantity,
            'after_quantity'  => $current,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'USERNAME'       => $username,
            'note'           => $note,
            'createdAt'     => now(),
        ]);
    }
}
