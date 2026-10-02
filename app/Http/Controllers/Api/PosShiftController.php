<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PosShift;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;
use App\Models\IdempotencyKey;
use App\Models\SanPham;
use Illuminate\Support\Facades\DB;

class PosShiftController extends Controller
{
    /**
     * Lấy thông tin ca làm việc đang mở của nhân viên hiện tại
     */
    public function getActiveShift(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $nguoidung = $user->nguoidung;
        $nhanvienId = $nguoidung ? $nguoidung->id : $user->id;

        $shift = PosShift::where('nhanVienId', $nhanvienId)
            ->where('trangThai', 0)
            ->first();

        if (!$shift) {
            return response()->json(['error' => 'Không có ca làm việc nào đang mở'], 404);
        }

        $cuahangId = $shift->cuaHangId;
        $moCa = $shift->thoiGianMoCa;

        // Doanh thu theo phương thức thanh toán trong ca đang mở
        $cashSales = (float) DonHang::where('cuaHangId', $cuahangId)
            ->where('loaiDonHang', 1)
            ->where('phuongThucThanhToan', 0)
            ->where('trangThaiThanhToan', 1)
            ->where('createdAt', '>=', $moCa)
            ->sum('tongThanhToan');

        $bankSales = (float) DonHang::where('cuaHangId', $cuahangId)
            ->where('loaiDonHang', 1)
            ->where('phuongThucThanhToan', 1)
            ->where('trangThaiThanhToan', 1)
            ->where('createdAt', '>=', $moCa)
            ->sum('tongThanhToan');

        $momoSales = (float) DonHang::where('cuaHangId', $cuahangId)
            ->where('loaiDonHang', 1)
            ->where('phuongThucThanhToan', 2)
            ->where('trangThaiThanhToan', 1)
            ->where('createdAt', '>=', $moCa)
            ->sum('tongThanhToan');

        $totalSales = $cashSales + $bankSales + $momoSales;
        $expectedCash = (float) $shift->tienMatDauCa + $cashSales;

        return response()->json([
            'id' => $shift->id,
            'cuaHangId' => $shift->cuaHangId,
            'nhanVienId' => $shift->nhanVienId,
            'thoiGianMoCa' => $shift->thoiGianMoCa,
            'opened_at' => $shift->thoiGianMoCa,
            'tienMatDauCa' => (float) $shift->tienMatDauCa,
            'opening_cash' => (float) $shift->tienMatDauCa,
            'cash_sales' => $cashSales,
            'bank_sales' => $bankSales,
            'momo_sales' => $momoSales,
            'total_sales' => $totalSales,
            'expected_cash' => $expectedCash,
            'trangThai' => $shift->trangThai,
        ]);
    }

    /**
     * Mở ca làm việc POS (Thông qua Stored Procedure sp_open_pos_shift)
     */
    public function openShift(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $nguoidung = $user->nguoidung;
        $cuahangId = ($nguoidung && $nguoidung->cuaHangId) ? $nguoidung->cuaHangId : 1;
        $nhanvienId = $nguoidung ? $nguoidung->id : $user->id;

        $openingCash = $request->input('tienMatDauCa', $request->input('opening_cash'));
        if ($openingCash === null || !is_numeric($openingCash) || $openingCash < 0) {
            return response()->json(['error' => 'Số tiền mở ca không hợp lệ'], 422);
        }

        DB::statement('CALL sp_open_pos_shift(?, ?, ?, @shift_id, @error)', [
            $cuahangId,
            $nhanvienId,
            $openingCash,
        ]);

        $res = DB::select('SELECT @shift_id AS shift_id, @error AS error')[0];

        if ($res->error) {
            $activeShift = PosShift::where('nhanVienId', $nhanvienId)->where('trangThai', 0)->first();
            return response()->json([
                'error' => $res->error,
                'data' => $activeShift ? [
                    'id' => $activeShift->id,
                    'opened_at' => $activeShift->thoiGianMoCa,
                    'opening_cash' => (float) $activeShift->tienMatDauCa,
                ] : null
            ], 400);
        }

        $shift = PosShift::find($res->shift_id);
        return response()->json([
            'message' => 'Mở ca làm việc thành công',
            'data' => [
                'id' => $shift->id,
                'cuaHangId' => $shift->cuaHangId,
                'opened_at' => $shift->thoiGianMoCa,
                'opening_cash' => (float) $shift->tienMatDauCa,
                'trangThai' => $shift->trangThai,
            ]
        ], 201);
    }

    /**
     * Đóng ca làm việc & Kết toán doanh thu (Thông qua Stored Procedure sp_close_pos_shift)
     */
    public function closeShift(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $nguoidung = $user->nguoidung;
        $nhanvienId = $nguoidung ? $nguoidung->id : $user->id;

        $actualCash = $request->input('tienMatThucTe', $request->input('actual_cash'));
        if ($actualCash === null || !is_numeric($actualCash) || $actualCash < 0) {
            return response()->json(['error' => 'Số tiền đóng ca không hợp lệ! Không được nhập số âm.'], 422);
        }

        $activeShift = PosShift::where('nhanVienId', $nhanvienId)->where('trangThai', 0)->first();
        if (!$activeShift) {
            return response()->json(['error' => 'Không tìm thấy ca làm việc đang mở'], 404);
        }

        $openingCash = (float) $activeShift->tienMatDauCa;
        $cuahangId = $activeShift->cuaHangId;
        $moCa = $activeShift->thoiGianMoCa;

        // Thống kê doanh thu ca theo từng phương thức thanh toán
        $cashSales = (float) DonHang::where('cuaHangId', $cuahangId)
            ->where('loaiDonHang', 1)
            ->where('phuongThucThanhToan', 0)
            ->where('trangThaiThanhToan', 1)
            ->where('createdAt', '>=', $moCa)
            ->sum('tongThanhToan');

        $bankSales = (float) DonHang::where('cuaHangId', $cuahangId)
            ->where('loaiDonHang', 1)
            ->where('phuongThucThanhToan', 1)
            ->where('trangThaiThanhToan', 1)
            ->where('createdAt', '>=', $moCa)
            ->sum('tongThanhToan');

        $momoSales = (float) DonHang::where('cuaHangId', $cuahangId)
            ->where('loaiDonHang', 1)
            ->where('phuongThucThanhToan', 2)
            ->where('trangThaiThanhToan', 1)
            ->where('createdAt', '>=', $moCa)
            ->sum('tongThanhToan');

        $totalSales = $cashSales + $bankSales + $momoSales;

        $expectedCash = $openingCash + $cashSales;
        $difference = (float) $actualCash - $expectedCash;

        $confirmDiscrepancy = $request->boolean('confirm_discrepancy') || $request->boolean('force_close');

        if ($difference != 0 && !$confirmDiscrepancy) {
            return response()->json([
                'requires_confirmation' => true,
                'opening_cash' => $openingCash,
                'cash_sales' => $cashSales,
                'bank_sales' => $bankSales,
                'momo_sales' => $momoSales,
                'total_sales' => $totalSales,
                'expected_cash' => $expectedCash,
                'actual_cash' => (float) $actualCash,
                'difference' => $difference,
                'message' => 'Phát hiện chênh lệch két tiền!'
            ]);
        }

        DB::statement('CALL sp_close_pos_shift(?, ?, @shift_id, @error)', [
            $nhanvienId,
            $actualCash,
        ]);

        $res = DB::select('SELECT @shift_id AS shift_id, @error AS error')[0];

        if ($res->error) {
            return response()->json(['error' => $res->error], 500);
        }

        $shift = PosShift::find($res->shift_id);

        $diffText = '';
        if ($difference < 0) {
            $diffText = ' (Ghi nhận THIẾU ' . number_format(abs($difference), 0, ',', '.') . ' ₫)';
        } elseif ($difference > 0) {
            $diffText = ' (Ghi nhận THỪA ' . number_format(abs($difference), 0, ',', '.') . ' ₫)';
        } else {
            $diffText = ' (Két tiền khớp 100%)';
        }

        return response()->json([
            'message' => 'Đã chốt ca và đóng ca thành công' . $diffText,
            'shift_closed' => true,
            'data' => [
                'id' => $shift->id,
                'opening_cash' => (float) $shift->tienMatDauCa,
                'cash_sales' => (float) ($shift->doanhThuTienMat ?? $cashSales),
                'bank_sales' => (float) ($shift->doanhThuChuyenKhoan ?? $bankSales),
                'momo_sales' => (float) ($shift->doanhThuMomo ?? $momoSales),
                'total_sales' => (float) ($shift->tongDoanhThu ?? $totalSales),
                'actual_cash' => (float) $shift->tienMatThucTe,
                'expected_cash' => (float) $shift->tienMatSach,
                'difference' => (float) $shift->chenhLech,
                'trangThai' => $shift->trangThai,
            ],
            'actual_cash' => (float) $shift->tienMatThucTe
        ]);
    }

    /**
     * Bán hàng & Thanh toán tại quầy POS (Thông qua Stored Procedure sp_pos_checkout & chống trùng Idempotency)
     */
    public function posCheckout(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->header('Idempotency-Key') ?? $request->idempotency_key;

        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return response()->json(json_decode($existing->response_payload, true), $existing->status_code);
            }
        }

        // 1. Map items from cart if provided
        $items = $request->input('items', []);
        if (empty($items) && $request->has('cart')) {
            $cart = $request->input('cart', []);
            $items = array_map(function ($item) {
                $spId = $item['sanPhamId'] ?? $item['id'] ?? null;
                if ($spId && !is_numeric($spId)) {
                    $sp = SanPham::where('ma_san_pham', $spId)->first();
                    $spId = $sp ? $sp->id : null;
                }
                return [
                    'sanPhamId' => (int) $spId,
                    'soLuong' => (int) ($item['soLuong'] ?? $item['quantity'] ?? 1),
                ];
            }, $cart);
            $items = array_filter($items, fn($i) => !empty($i['sanPhamId']));
            $request->merge(['items' => array_values($items)]);
        }

        // 2. Map payment method
        $pm = $request->input('phuongThucThanhToan', $request->input('paymentMethod', 0));
        if (!is_numeric($pm)) {
            $pmUpper = strtoupper((string) $pm);
            if (in_array($pmUpper, ['VIETQR', 'CHUYENKHOAN', '1'])) {
                $pm = 1;
            } elseif (in_array($pmUpper, ['MOMO', '2'])) {
                $pm = 2;
            } else {
                $pm = 0; // CASH / Tiền mặt
            }
            $request->merge(['phuongThucThanhToan' => $pm]);
        }

        // 3. Map store ID
        if (!$request->has('cuaHangId')) {
            $cuahangId = 1;
            if ($user && $user->nguoidung && $user->nguoidung->cuaHangId) {
                $cuahangId = $user->nguoidung->cuaHangId;
            }
            $request->merge(['cuaHangId' => $cuahangId]);
        }

        $request->validate([
            'cuaHangId' => 'required|exists:cuahang,id',
            'items' => 'required|array|min:1',
            'items.*.sanpham_id' => 'required|exists:sanPham,id',
            'items.*.so_luong' => 'required|integer|min:1',
            'phuongThucThanhToan' => 'required|integer|in:0,1,2',
        ]);

        try {
            DB::statement('CALL sp_pos_checkout(?, ?, ?, @donhang_id, @ma_don_hang, @error)', [
                $request->cuaHangId,
                $request->phuongThucThanhToan,
                json_encode($request->items),
            ]);

            $res = DB::select('SELECT @donhang_id AS donhang_id, @ma_don_hang AS ma_don_hang, @error AS error')[0];

            if ($res->error) {
                return response()->json(['error' => 'Thanh toán POS thất bại: ' . $res->error], 500);
            }

            $donHang = DonHang::find($res->donHangId);

            // Update customer ID if provided
            $customerId = $request->input('customerId', $request->input('khachHangId', $request->input('nguoidungId')));
            if ($customerId && $donHang) {
                if (!is_numeric($customerId)) {
                    $kh = \App\Models\NguoiDung::where('soDienThoai', $customerId)->first();
                    if ($kh) $customerId = $kh->id;
                }
                if (is_numeric($customerId) && \App\Models\NguoiDung::find($customerId)) {
                    $donHang->nguoidungId = $customerId;
                    $donHang->save();
                }
            }

            $responseData = ['message' => 'Thanh toán POS thành công', 'data' => $donHang ? $donHang->load('chitiet') : null];

            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Thanh toán POS thất bại: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lấy danh sách lịch sử ca làm việc để quản lý đối soát chênh lệch
     */
    public function getShiftHistory(Request $request)
    {
        $query = PosShift::with(['cuahang', 'nhanvien'])
            ->orderBy('id', 'desc');

        if ($request->has('nhanVienId')) {
            $query->where('nhanVienId', $request->nhanVienId);
        }

        if ($request->has('trangThai')) {
            $query->where('trangThai', $request->trangThai);
        }

        $shifts = $query->paginate($request->input('per_page', 20));

        return response()->json($shifts);
    }
}

