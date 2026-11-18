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
use App\Models\LichSuTrangThaiDonHang;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PosShiftController extends Controller
{
    /**
     * Tính toán doanh thu & tiền mặt két trong ca làm việc (đã trừ hoàn tiền mặt POS trong ca)
     */
    private function computeShiftMetrics(PosShift $shift): array
    {
        $cuahangId = $shift->cuaHangId;
        $moCa = $shift->thoiGianMoCa;
        $endAt = $shift->thoiGianDongCa;

        $baseSales = DonHang::where('cuaHangId', $cuahangId)
            ->where('loaiDonHang', 1)
            ->whereIn('trangThaiThanhToan', [1, 3])
            ->where('createdAt', '>=', $moCa);

        if ($endAt) {
            $baseSales->where('createdAt', '<=', $endAt);
        }

        $cashSales = (float) (clone $baseSales)->where('phuongThucThanhToan', 0)->sum('tongThanhToan');
        $bankSales = (float) (clone $baseSales)->where('phuongThucThanhToan', 1)->sum('tongThanhToan');
        $momoSales = (float) (clone $baseSales)->where('phuongThucThanhToan', 2)->sum('tongThanhToan');

        $refundQuery = DonHang::where('cuaHangId', $cuahangId)
            ->where('loaiDonHang', 1)
            ->where('trangThaiThanhToan', 3)
            ->where('ngayHoanTien', '>=', $moCa);

        if ($endAt) {
            $refundQuery->where('ngayHoanTien', '<=', $endAt);
        }

        $cashRefunds = (float) $refundQuery->sum('soTienHoan');
        $grossSales = $cashSales + $bankSales + $momoSales;
        $totalSales = max(0, $grossSales - $cashRefunds);
        $expectedCash = (float) $shift->tienMatDauCa + $cashSales - $cashRefunds;

        return [
            'cash_sales' => $cashSales,
            'bank_sales' => $bankSales,
            'momo_sales' => $momoSales,
            'cash_refunds' => $cashRefunds,
            'gross_sales' => $grossSales,
            'total_sales' => $totalSales,
            'expected_cash' => $expectedCash,
        ];
    }

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

        $metrics = $this->computeShiftMetrics($shift);

        return response()->json([
            'id' => $shift->id,
            'cuaHangId' => $shift->cuaHangId,
            'nhanVienId' => $shift->nhanVienId,
            'thoiGianMoCa' => $shift->thoiGianMoCa,
            'opened_at' => $shift->thoiGianMoCa,
            'tienMatDauCa' => (float) $shift->tienMatDauCa,
            'opening_cash' => (float) $shift->tienMatDauCa,
            'cash_sales' => $metrics['cash_sales'],
            'bank_sales' => $metrics['bank_sales'],
            'momo_sales' => $metrics['momo_sales'],
            'cash_refunds' => $metrics['cash_refunds'],
            'total_sales' => $metrics['total_sales'],
            'expected_cash' => $metrics['expected_cash'],
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
        $metrics = $this->computeShiftMetrics($activeShift);
        $cashSales = $metrics['cash_sales'];
        $bankSales = $metrics['bank_sales'];
        $momoSales = $metrics['momo_sales'];
        $cashRefunds = $metrics['cash_refunds'];
        $totalSales = $metrics['total_sales'];
        $expectedCash = $metrics['expected_cash'];
        $difference = (float) $actualCash - $expectedCash;

        $confirmDiscrepancy = $request->boolean('confirm_discrepancy') || $request->boolean('force_close');

        if ($difference != 0 && !$confirmDiscrepancy) {
            return response()->json([
                'requires_confirmation' => true,
                'opening_cash' => $openingCash,
                'cash_sales' => $cashSales,
                'bank_sales' => $bankSales,
                'momo_sales' => $momoSales,
                'cash_refunds' => $cashRefunds,
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
                'cash_refunds' => $cashRefunds,
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

    /**
     * Tra cứu hóa đơn POS theo mã hóa đơn để kiểm tra sản phẩm trước khi hoàn tiền mặt
     */
    public function lookupPosOrder(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $rawCode = trim((string)($request->input('maDonHang', $request->input('code', ''))));
        $code = ltrim(strtoupper($rawCode), '#');

        if ($code === '') {
            return response()->json(['error' => 'Vui lòng nhập mã hóa đơn cần tra cứu.'], 422);
        }

        $query = DonHang::with(['chitiet.sanpham', 'nguoidung', 'cuahang'])
            ->where('loaiDonHang', 1)
            ->where(function ($q) use ($code) {
                $q->whereRaw('UPPER(maDonHang) = ?', [$code]);
                if (is_numeric($code)) {
                    $q->orWhere('id', (int)$code);
                }
            });

        $order = $query->first();
        if (!$order) {
            return response()->json(['error' => "Không tìm thấy hóa đơn bán tại quầy với mã '{$rawCode}'."], 404);
        }

        $vtId = (int)($user->vaiTroId ?? 0);
        $nguoidung = $user->nguoidung;
        if (!in_array($vtId, [1, 8], true) && $nguoidung && $nguoidung->cuaHangId) {
            if ((int)$order->cuaHangId !== (int)$nguoidung->cuaHangId) {
                return response()->json(['error' => 'Hóa đơn này thuộc chi nhánh khác, bạn không có quyền hoàn tiền tại quầy này.'], 403);
            }
        }

        $canRefund = true;
        $blockedReason = null;
        if ((int)$order->trangThaiThanhToan === 3 || (int)$order->trangThaiDonHang === 6) {
            $canRefund = false;
            $blockedReason = 'Hóa đơn này đã được hoàn tiền trước đó.';
        } elseif ((int)$order->trangThaiThanhToan !== 1) {
            $canRefund = false;
            $blockedReason = 'Hóa đơn chưa ở trạng thái đã thanh toán.';
        }

        return response()->json([
            'data' => $order,
            'can_refund' => $canRefund,
            'blocked_reason' => $blockedReason,
        ]);
    }

    /**
     * Hoàn tiền mặt cho hóa đơn đã thanh toán tại quầy POS:
     * - Bắt buộc có ca làm việc đang mở tại chi nhánh
     * - Cộng lại số lượng sản phẩm vào tồn kho chi nhánh & ghi sổ cái kho
     * - Cập nhật hóa đơn sang Đã hoàn tiền (bằng tiền mặt)
     * - Cập nhật két tiền & doanh thu ca hiện tại
     */
    public function refundPosOrder(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $lyDo = trim((string)($request->input('lyDo', $request->input('reason', ''))));
        if ($lyDo === '') {
            return response()->json(['error' => 'Vui lòng nhập lý do hoàn tiền / trả hàng.'], 422);
        }

        $nguoidung = $user->nguoidung;
        $nhanvienId = $nguoidung ? $nguoidung->id : $user->id;
        $vtId = (int)($user->vaiTroId ?? 0);

        DB::beginTransaction();
        try {
            $order = DonHang::lockForUpdate()->with(['chitiet.sanpham', 'cuahang'])->find($id);
            if (!$order) {
                DB::rollBack();
                return response()->json(['error' => 'Không tìm thấy hóa đơn.'], 404);
            }

            if ((int)$order->loaiDonHang !== 1) {
                DB::rollBack();
                return response()->json(['error' => 'Chỉ hỗ trợ hoàn tiền tại quầy cho hóa đơn POS.'], 400);
            }

            if (!in_array($vtId, [1, 8], true) && $nguoidung && $nguoidung->cuaHangId) {
                if ((int)$order->cuaHangId !== (int)$nguoidung->cuaHangId) {
                    DB::rollBack();
                    return response()->json(['error' => 'Bạn chỉ có quyền hoàn tiền hóa đơn thuộc chi nhánh mình.'], 403);
                }
            }

            if ((int)$order->trangThaiThanhToan === 3 || (int)$order->trangThaiDonHang === 6) {
                DB::rollBack();
                return response()->json(['error' => 'Hóa đơn này đã được hoàn tiền trước đó!'], 400);
            }

            if ((int)$order->trangThaiThanhToan !== 1) {
                DB::rollBack();
                return response()->json(['error' => 'Chỉ có thể hoàn tiền cho hóa đơn đã thanh toán.'], 400);
            }

            // Tìm ca làm việc đang mở để trừ tiền mặt trong két
            $activeShift = PosShift::lockForUpdate()
                ->where('cuaHangId', $order->cuaHangId)
                ->where('nhanVienId', $nhanvienId)
                ->where('trangThai', 0)
                ->first();

            if (!$activeShift && in_array($vtId, [1, 2, 8], true)) {
                $activeShift = PosShift::lockForUpdate()
                    ->where('cuaHangId', $order->cuaHangId)
                    ->where('trangThai', 0)
                    ->latest('id')
                    ->first();
            }

            if (!$activeShift) {
                DB::rollBack();
                return response()->json(['error' => 'Chưa có ca làm việc nào đang mở tại chi nhánh này. Vui lòng mở ca trước khi hoàn tiền mặt!'], 400);
            }

            $oldStatus = (int)$order->trangThaiDonHang;
            $refundAmount = (float)$order->tongThanhToan;

            // Cộng lại số lượng từng sản phẩm vào tồn kho chi nhánh & ghi sổ cái
            foreach ($order->chitiet as $item) {
                $qty = (int)$item->soLuong;
                if ($qty <= 0) continue;

                $tonKho = TonKhoChiNhanh::firstOrCreate(
                    ['cuaHangId' => $order->cuaHangId, 'sanPhamId' => $item->sanPhamId],
                    ['soLuongTon' => 0]
                );
                $tonKho->increment('soLuongTon', $qty);

                \App\Services\InventoryLedgerService::record(
                    (int)$order->cuaHangId,
                    (int)$item->sanPhamId,
                    1, // Nhập lại kho do hoàn trả POS
                    $qty,
                    ($order->maDonHang ?? ('POS_' . $order->id)) . ' (Hoàn POS)',
                    (int)$tonKho->fresh()->soLuongTon
                );
            }

            $order->trangThaiDonHang = 6; // Đã hủy / Hoàn trả
            $order->trangThaiThanhToan = 3; // Đã hoàn tiền
            $order->trangThaiHoanHang = 'DA_DUYET';
            $order->trangThaiHoanTien = 'DA_HOAN_TIEN';
            $order->lyDoHoanTra = $lyDo;
            $order->ghiChuHoanTien = "Hoàn tiền mặt tại quầy POS (Ca #{$activeShift->id}). Lý do: {$lyDo}";
            $order->soTienHoan = $refundAmount;
            $order->ngayHoanTien = now();
            $order->nhanVienHoanTienId = $nhanvienId;
            $order->maGiaoDichHoanTien = 'REFUND-POS-' . ($order->maDonHang ?? $order->id) . '-S' . $activeShift->id;
            $order->save();

            LichSuTrangThaiDonHang::create([
                'donHangId' => $order->id,
                'trangThaiCu' => $oldStatus,
                'trangThaiMoi' => 6,
                'moTa' => "Hoàn tiền mặt tại quầy POS: " . number_format($refundAmount, 0, ',', '.') . " ₫ (Ca #{$activeShift->id}). Lý do: {$lyDo}",
            ]);

            // Cập nhật lại số liệu két tiền & doanh thu của ca hiện tại
            $metrics = $this->computeShiftMetrics($activeShift);
            $activeShift->update([
                'doanhThuTienMat' => $metrics['cash_sales'],
                'doanhThuChuyenKhoan' => $metrics['bank_sales'],
                'doanhThuMomo' => $metrics['momo_sales'],
                'tongDoanhThu' => $metrics['total_sales'],
                'tienMatSach' => $metrics['expected_cash'],
            ]);

            DB::commit();

            try {
                ProductController::clearProductCache();
            } catch (\Throwable $eCache) {}

            return response()->json([
                'message' => 'Hoàn tiền mặt hóa đơn ' . $order->maDonHang . ' thành công! Đã cộng lại tồn kho và trừ két tiền mặt ca #' . $activeShift->id . '.',
                'data' => $order->fresh(['chitiet.sanpham', 'cuahang']),
                'shift' => [
                    'id' => $activeShift->id,
                    'cuaHangId' => $activeShift->cuaHangId,
                    'nhanVienId' => $activeShift->nhanVienId,
                    'thoiGianMoCa' => $activeShift->thoiGianMoCa,
                    'opened_at' => $activeShift->thoiGianMoCa,
                    'tienMatDauCa' => (float)$activeShift->tienMatDauCa,
                    'opening_cash' => (float)$activeShift->tienMatDauCa,
                    'cash_sales' => $metrics['cash_sales'],
                    'bank_sales' => $metrics['bank_sales'],
                    'momo_sales' => $metrics['momo_sales'],
                    'cash_refunds' => $metrics['cash_refunds'],
                    'total_sales' => $metrics['total_sales'],
                    'expected_cash' => $metrics['expected_cash'],
                    'trangThai' => $activeShift->trangThai,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Hoàn tiền POS thất bại: ' . $e->getMessage()], 500);
        }
    }
}
