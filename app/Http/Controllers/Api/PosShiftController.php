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
use App\Models\NguoiDung;
use App\Models\TonKhoChiNhanh;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        // Doanh thu theo phương thức thanh toán trong ca đang mở (loaiDonHang = 1 là POS quầy)
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
     * Mở ca làm việc POS & Ràng buộc ca làm việc đã đăng ký của nhân viên bán hàng
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
            return response()->json(['error' => 'Số tiền mở ca không hợp lệ! Vui lòng nhập số tiền không âm.'], 422);
        }

        // Ràng buộc ca làm việc: Nhân viên bán hàng chỉ được mở ca theo ca đã đăng ký
        $vaiTroId = (int)($user->vaiTroId ?? $user->MAROLE ?? 3);
        $isAdminOrManager = in_array($vaiTroId, [1, 2, 8]); // Admin, Quản lý, Giám đốc được linh hoạt

        if (!$isAdminOrManager) {
            // Ca làm việc đăng ký của nhân viên (từ hồ sơ hoặc từ form đăng ký)
            $registeredShift = $nguoidung?->caLamViec ?? $request->input('caLamViec') ?? 'Ca Sáng';
            $requestedShift = $request->input('caLamViec', $registeredShift);

            // Nếu hồ sơ nhân viên đã có ca đăng ký cố định mà chọn ca khác -> Báo lỗi
            if (!empty($nguoidung?->caLamViec) && $requestedShift !== $nguoidung->caLamViec) {
                return response()->json([
                    'error' => "Bạn chỉ được phép mở ca theo ca làm việc đã đăng ký (" . $nguoidung->caLamViec . ")."
                ], 403);
            }

            // Kiểm tra khung giờ làm việc theo giờ Việt Nam
            $nowVn = Carbon::now('Asia/Ho_Chi_Minh');
            $currentMinutes = $nowVn->hour * 60 + $nowVn->minute;
            $shiftName = $requestedShift;

            // Khung giờ cho phép mở ca (dung sai 30 phút trước và sau ca)
            // Ca Sáng: 06:00 - 14:00 (Cho phép 05:30 -> 14:30 => 330p -> 870p)
            // Ca Chiều: 14:00 - 22:00 (Cho phép 13:30 -> 22:30 => 810p -> 1350p)
            // Hành chính: 08:00 - 17:00 (Cho phép 07:30 -> 18:00 => 450p -> 1080p)
            $isWithinWindow = true;
            $allowedTimeDesc = '';

            if (stripos($shiftName, 'Sáng') !== false) {
                $isWithinWindow = ($currentMinutes >= 330 && $currentMinutes <= 870);
                $allowedTimeDesc = 'Ca Sáng (06:00 - 14:00)';
            } elseif (stripos($shiftName, 'Chiều') !== false) {
                $isWithinWindow = ($currentMinutes >= 810 && $currentMinutes <= 1350);
                $allowedTimeDesc = 'Ca Chiều (14:00 - 22:00)';
            } elseif (stripos($shiftName, 'Hành chính') !== false) {
                $isWithinWindow = ($currentMinutes >= 450 && $currentMinutes <= 1080);
                $allowedTimeDesc = 'Ca Hành chính (08:00 - 17:00)';
            }

            // Nếu không nằm trong khung giờ làm việc và không có cờ bypass
            if (!$isWithinWindow && !$request->boolean('bypass_time_check')) {
                return response()->json([
                    'error' => "Hiện tại là {$nowVn->format('H:i')}, không thuộc khung giờ cho phép mở {$allowedTimeDesc}. Nhân viên bán hàng chỉ được mở ca đúng ca đã đăng ký."
                ], 403);
            }
        }

        // Kiểm tra xem nhân viên đã có ca đang mở chưa
        $activeShift = PosShift::where('nhanVienId', $nhanvienId)->where('trangThai', 0)->first();
        if ($activeShift) {
            return response()->json([
                'error' => 'Bạn đang có một ca làm việc đang mở',
                'data' => [
                    'id' => $activeShift->id,
                    'opened_at' => $activeShift->thoiGianMoCa,
                    'opening_cash' => (float) $activeShift->tienMatDauCa,
                ]
            ], 400);
        }

        // Tạo ca làm việc mới qua Eloquent
        $shift = PosShift::create([
            'cuaHangId' => $cuahangId,
            'nhanVienId' => $nhanvienId,
            'thoiGianMoCa' => now(),
            'tienMatDauCa' => (float) $openingCash,
            'tienMatSach' => (float) $openingCash,
            'trangThai' => 0,
        ]);

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
     * Đóng ca làm việc & Kết toán doanh thu
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

        // Cập nhật chốt ca
        $activeShift->update([
            'thoiGianDongCa' => now(),
            'doanhThuTienMat' => $cashSales,
            'doanhThuChuyenKhoan' => $bankSales,
            'doanhThuMomo' => $momoSales,
            'tongDoanhThu' => $totalSales,
            'tienMatSach' => $expectedCash,
            'tienMatThucTe' => (float) $actualCash,
            'chenhLech' => $difference,
            'trangThai' => 1,
        ]);

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
                'id' => $activeShift->id,
                'opening_cash' => (float) $activeShift->tienMatDauCa,
                'cash_sales' => $cashSales,
                'bank_sales' => $bankSales,
                'momo_sales' => $momoSales,
                'total_sales' => $totalSales,
                'actual_cash' => (float) $activeShift->tienMatThucTe,
                'expected_cash' => (float) $activeShift->tienMatSach,
                'difference' => (float) $activeShift->chenhLech,
                'trangThai' => $activeShift->trangThai,
            ],
            'actual_cash' => (float) $activeShift->tienMatThucTe
        ]);
    }

    /**
     * Bán hàng & Thanh toán tại quầy POS (Pure Eloquent Transaction & Trừ tồn kho chi nhánh)
     */
    public function posCheckout(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->header('Idempotency-Key') ?? $request->idempotency_key;

        if ($idempotencyKey) {
            try {
                $existing = IdempotencyKey::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    $payload = $existing->response_payload ?? $existing->responsePayload ?? null;
                    $statusCode = (int)($existing->status_code ?? $existing->statusCode ?? 200);
                    if ($payload) {
                        return response()->json(json_decode($payload, true), $statusCode > 0 ? $statusCode : 200);
                    }
                }
            } catch (\Throwable $eIdemLookup) {
                try {
                    $existing = IdempotencyKey::where('idempotencyKey', $idempotencyKey)->first();
                    if ($existing) {
                        $payload = $existing->responsePayload ?? $existing->response_payload ?? null;
                        $statusCode = (int)($existing->statusCode ?? $existing->status_code ?? 200);
                        if ($payload) {
                            return response()->json(json_decode($payload, true), $statusCode > 0 ? $statusCode : 200);
                        }
                    }
                } catch (\Throwable $eIdemLookup2) {}
            }
        }

        // 1. Chuẩn hóa items từ payload cart hoặc items
        $items = $request->input('items', []);
        if (empty($items) && $request->has('cart')) {
            $cart = $request->input('cart', []);
            $items = array_map(function ($item) {
                $spId = $item['sanPhamId'] ?? $item['id'] ?? $item['sanpham_id'] ?? $item['MASP'] ?? $item['maSP'] ?? null;
                if ($spId && !is_numeric($spId)) {
                    $cleanId = preg_replace('/^(SP|#)/i', '', (string)$spId);
                    if (is_numeric($cleanId)) {
                        $spId = (int)$cleanId;
                    } else {
                        $sp = SanPham::where('slug', $spId)->first();
                        $spId = $sp ? $sp->id : null;
                    }
                }
                return [
                    'sanPhamId' => (int) $spId,
                    'soLuong' => (int) ($item['soLuong'] ?? $item['quantity'] ?? $item['so_luong'] ?? 1),
                ];
            }, $cart);
            $items = array_values(array_filter($items, fn($i) => !empty($i['sanPhamId'])));
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
        } else {
            $pm = (int) $pm;
        }

        // 3. Map store ID
        $cuahangId = (int)($request->input('cuaHangId') ?? 1);
        if ($user && $user->nguoidung && $user->nguoidung->cuaHangId) {
            $cuahangId = (int)$user->nguoidung->cuaHangId;
        }

        // 4. Resolve products & prices
        $resolvedItems = [];
        $subtotal = 0;

        foreach ($items as $it) {
            $spId = $it['sanPhamId'] ?? $it['sanpham_id'] ?? $it['id'] ?? null;
            $sp = $spId ? SanPham::find($spId) : null;
            if ($sp) {
                $qty = max(1, (int)($it['soLuong'] ?? $it['quantity'] ?? $it['so_luong'] ?? 1));
                $price = (float) ($sp->giaSauGiam ?? $sp->giaBan);
                $thanhTien = $price * $qty;
                $subtotal += $thanhTien;

                $resolvedItems[] = [
                    'sanPhamId' => $sp->id,
                    'soLuong' => $qty,
                    'donGia' => $price,
                    'thanhTien' => $thanhTien,
                    'sp' => $sp
                ];
            }
        }

        if (empty($resolvedItems)) {
            return response()->json(['error' => 'Danh sách sản phẩm thanh toán trống hoặc không hợp lệ!'], 400);
        }

        // Customer ID if provided
        $customerId = $request->input('customerId', $request->input('khachHangId', $request->input('nguoidungId')));
        if ($customerId) {
            if (!is_numeric($customerId)) {
                $kh = NguoiDung::where('soDienThoai', $customerId)->first();
                if ($kh) $customerId = $kh->id;
            }
            if (!is_numeric($customerId) || !NguoiDung::find($customerId)) {
                $customerId = null;
            }
        }

        DB::beginTransaction();
        try {
            $requestedCode = strtoupper(trim((string)($request->input('maDonHang') ?? $request->input('orderCode') ?? '')));
            if ($requestedCode !== '' && !DonHang::where('maDonHang', $requestedCode)->exists()) {
                $maDonHang = $requestedCode;
            } else {
                $maDonHang = 'POS' . date('YmdHis') . rand(10, 99);
                while (DonHang::where('maDonHang', $maDonHang)->exists()) {
                    $maDonHang = 'POS' . date('YmdHis') . rand(100, 999);
                }
            }

            $donHang = DonHang::create([
                'maDonHang' => $maDonHang,
                'cuaHangId' => $cuahangId,
                'nguoidungId' => $customerId ?: null,
                'loaiDonHang' => 1, // POS quầy
                'tongTienHang' => $subtotal,
                'soTienGiam' => 0,
                'phiVanChuyen' => 0,
                'tongThanhToan' => $subtotal,
                'phuongThucThanhToan' => $pm,
                'trangThaiThanhToan' => 1, // Đã thanh toán tại quầy
                'trangThaiDonHang' => 5, // 5: Hoàn thành / Đã giao thành công
                'diaChiGiaoHang' => 'Mua trực tiếp tại quầy',
            ]);

            foreach ($resolvedItems as $it) {
                ChiTietDonHang::create([
                    'donHangId' => $donHang->id,
                    'sanPhamId' => $it['sanPhamId'],
                    'soLuong' => $it['soLuong'],
                    'donGia' => $it['donGia'],
                    'thanhTien' => $it['thanhTien'],
                ]);

                // Trừ tồn kho chi nhánh
                $tonKho = TonKhoChiNhanh::firstOrCreate(
                    ['cuaHangId' => $cuahangId, 'sanPhamId' => $it['sanPhamId']],
                    ['soLuongTon' => 0]
                );
                $tonKho->soLuongTon = max(0, (int)$tonKho->soLuongTon - $it['soLuong']);
                $tonKho->save();

                // Ghi nhận vào sổ cái tồn kho (Loại 0: Bán hàng POS)
                \App\Services\InventoryLedgerService::record(
                    (int)$cuahangId,
                    (int)$it['sanPhamId'],
                    0, // Bán hàng (POS)
                    -$it['soLuong'],
                    $donHang->maDonHang ?? ('POS_' . $donHang->id),
                    (int)$tonKho->soLuongTon
                );
            }

            DB::commit();

            try {
                ProductController::clearProductCache();
            } catch (\Throwable $eCache) {}

            $responseData = [
                'message' => 'Thanh toán POS thành công',
                'data' => $donHang->load('chitiet.sanpham')
            ];

            if ($idempotencyKey) {
                try {
                    IdempotencyKey::create([
                        'idempotency_key' => $idempotencyKey,
                        'response_payload' => json_encode($responseData),
                        'status_code' => 200,
                    ]);
                } catch (\Throwable $eIdem) {}
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Thanh toán POS thất bại: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lấy danh sách lịch sử ca làm việc để quản lý đối soát chênh lệch
     */
    public function getShiftHistory(Request $request)
    {
        $query = PosShift::with(['cuahang', 'nhanvien.taikhoan'])
            ->orderBy('id', 'desc');

        if ($request->has('nhanVienId') && $request->nhanVienId !== '') {
            $query->where('nhanVienId', $request->nhanVienId);
        }

        if ($request->has('cuaHangId') && $request->cuaHangId !== '') {
            $query->where('cuaHangId', $request->cuaHangId);
        }

        // Hỗ trợ cả trang_thai và trangThai từ query params
        $status = $request->input('trang_thai', $request->input('trangThai'));
        if ($status !== null && $status !== '' && strtoupper((string)$status) !== 'ALL') {
            $query->where('trangThai', (int)$status);
        }

        $perPage = (int) $request->input('per_page', 50);
        $shifts = $query->paginate($perPage);

        return response()->json($shifts);
    }
}
