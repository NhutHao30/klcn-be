<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voucher;
use App\Models\KhuyenMai;
use Illuminate\Support\Facades\DB;

class VoucherController extends Controller
{
    private function resolveUserIds($user): array
    {
        if (!$user) {
            return [];
        }
        $ids = [(int) $user->id];
        if ($user->nguoidung && $user->nguoidung->id) {
            $ids[] = (int) $user->nguoidung->id;
        } else {
            $nd = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
            if ($nd) {
                $ids[] = (int) $nd->id;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private function resolvePrimaryNguoiDungId($user): ?int
    {
        if (!$user) {
            return null;
        }
        if ($user->nguoidung && $user->nguoidung->id) {
            return (int) $user->nguoidung->id;
        }
        $nd = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
        return $nd ? (int) $nd->id : (int) $user->id;
    }

    public function index()
    {
        $vouchers = Voucher::orderBy('NGAY_BAT_DAU', 'desc')->get();
        return response()->json($vouchers);
    }

    public function show($code)
    {
        $voucher = Voucher::where('MA_VOUCHER', $code)->first();
        if (!$voucher || !$voucher->isValid) {
            return response()->json(['error' => 'Mã Voucher không tồn tại hoặc đã hết hạn'], 404);
        }
        return response()->json($voucher);
    }

    public function store(Request $request)
    {
        $code = strtoupper(trim($request->input('MA_VOUCHER') ?? $request->input('ma_voucher') ?? $request->input('maVoucher') ?? ''));
        $name = trim($request->input('TEN_VOUCHER') ?? $request->input('ten_voucher') ?? $request->input('tenVoucher') ?? $code);
        
        $loaiGiamRaw = $request->input('LOAI_GIAM') ?? $request->input('loai_giam') ?? $request->input('loai_giam_gia');
        $loaiGiam = ($loaiGiamRaw === 'TIEN_MAT' || $loaiGiamRaw == 1 || $loaiGiamRaw === '1') ? 'TIEN_MAT' : 'PHAN_TRAM';
        
        $giaTri = (float)($request->input('GIA_TRI') ?? $request->input('gia_tri') ?? $request->input('gia_tri_giam') ?? 0);
        $donToiThieu = (float)($request->input('DON_TOI_THIEU') ?? $request->input('don_toi_thieu') ?? $request->input('don_hang_toi_thieu') ?? 0);
        $giamToiDa = $request->input('GIAM_TOI_DA') ?? $request->input('giam_toi_da');
        $giamToiDaVal = ($giamToiDa !== null && $giamToiDa !== '') ? (float)$giamToiDa : null;
        
        $soLuong = (int)($request->input('SO_LUONG_GIOI_HAN') ?? $request->input('so_luong_gioi_han') ?? $request->input('soLuong') ?? 100);
        
        $ngayBatDau = $request->input('NGAY_BAT_DAU') ?? $request->input('ngay_bat_dau') ?? now()->toDateTimeString();
        $ngayKetThuc = $request->input('NGAY_KET_THUC') ?? $request->input('ngay_ket_thuc') ?? now()->addDays(7)->toDateTimeString();

        $request->merge([
            'MA_VOUCHER' => $code,
            'TEN_VOUCHER' => $name,
            'LOAI_GIAM' => $loaiGiam,
            'GIA_TRI' => $giaTri,
            'DON_TOI_THIEU' => $donToiThieu,
            'GIAM_TOI_DA' => $giamToiDaVal,
            'SO_LUONG_GIOI_HAN' => $soLuong,
            'NGAY_BAT_DAU' => $ngayBatDau,
            'NGAY_KET_THUC' => $ngayKetThuc,
        ]);

        $validated = $request->validate([
            'MA_VOUCHER' => 'required|string|max:50|unique:voucher,MA_VOUCHER',
            'TEN_VOUCHER' => 'required|string|max:255',
            'LOAI_GIAM' => 'required|string|in:PHAN_TRAM,TIEN_MAT',
            'GIA_TRI' => 'required|numeric|min:0',
            'DON_TOI_THIEU' => 'required|numeric|min:0',
            'GIAM_TOI_DA' => 'nullable|numeric|min:0',
            'SO_LUONG_GIOI_HAN' => 'required|integer|min:1',
            'NGAY_BAT_DAU' => 'required',
            'NGAY_KET_THUC' => 'required',
        ], [
            'MA_VOUCHER.unique' => 'Mã Voucher "' . $code . '" đã tồn tại trong hệ thống! Vui lòng chọn mã khác.',
            'MA_VOUCHER.required' => 'Mã Voucher không được để trống.',
            'TEN_VOUCHER.required' => 'Tên chương trình Voucher không được để trống.',
        ]);

        $voucher = Voucher::create([
            'MA_VOUCHER' => $validated['MA_VOUCHER'],
            'TEN_VOUCHER' => $validated['TEN_VOUCHER'],
            'LOAI_GIAM' => $validated['LOAI_GIAM'],
            'GIA_TRI' => $validated['GIA_TRI'],
            'DON_TOI_THIEU' => $validated['DON_TOI_THIEU'],
            'GIAM_TOI_DA' => $validated['GIAM_TOI_DA'],
            'SO_LUONG_GIOI_HAN' => $validated['SO_LUONG_GIOI_HAN'],
            'SO_LUONG_DA_DUNG' => 0,
            'NGAY_BAT_DAU' => date('Y-m-d H:i:s', strtotime($validated['NGAY_BAT_DAU'])),
            'NGAY_KET_THUC' => date('Y-m-d H:i:s', strtotime($validated['NGAY_KET_THUC'])),
            'TRANG_THAI' => 1,
        ]);

        return response()->json(['message' => 'Tạo Voucher thành công', 'data' => $voucher], 201);
    }

    public function update(Request $request, $id)
    {
        $voucher = Voucher::where('MA_VOUCHER', $id)->first();
        if (!$voucher) {
            return response()->json(['error' => 'Không tìm thấy Voucher'], 404);
        }

        $name = trim($request->input('TEN_VOUCHER') ?? $request->input('ten_voucher') ?? $request->input('tenVoucher') ?? $voucher->TEN_VOUCHER);
        $loaiGiamRaw = $request->input('LOAI_GIAM') ?? $request->input('loai_giam') ?? $voucher->LOAI_GIAM;
        $loaiGiam = ($loaiGiamRaw === 'TIEN_MAT' || $loaiGiamRaw == 1 || $loaiGiamRaw === '1') ? 'TIEN_MAT' : 'PHAN_TRAM';
        
        $giaTri = (float)($request->input('GIA_TRI') ?? $request->input('gia_tri') ?? $voucher->GIA_TRI);
        $donToiThieu = (float)($request->input('DON_TOI_THIEU') ?? $request->input('don_toi_thieu') ?? $voucher->DON_TOI_THIEU);
        $giamToiDa = $request->input('GIAM_TOI_DA') ?? $request->input('giam_toi_da');
        $giamToiDaVal = ($giamToiDa !== null && $giamToiDa !== '') ? (float)$giamToiDa : null;
        $soLuong = (int)($request->input('SO_LUONG_GIOI_HAN') ?? $request->input('so_luong_gioi_han') ?? $voucher->SO_LUONG_GIOI_HAN);
        
        $ngayBatDau = $request->input('NGAY_BAT_DAU') ?? $voucher->NGAY_BAT_DAU;
        $ngayKetThuc = $request->input('NGAY_KET_THUC') ?? $voucher->NGAY_KET_THUC;
        $trangThai = $request->has('TRANG_THAI') ? ($request->input('TRANG_THAI') == '1' || $request->input('TRANG_THAI') === true || $request->input('TRANG_THAI') == 'true' ? 1 : 0) : $voucher->TRANG_THAI;

        $voucher->update([
            'TEN_VOUCHER' => $name,
            'LOAI_GIAM' => $loaiGiam,
            'GIA_TRI' => $giaTri,
            'DON_TOI_THIEU' => $donToiThieu,
            'GIAM_TOI_DA' => $giamToiDaVal,
            'SO_LUONG_GIOI_HAN' => $soLuong,
            'NGAY_BAT_DAU' => date('Y-m-d H:i:s', strtotime($ngayBatDau)),
            'NGAY_KET_THUC' => date('Y-m-d H:i:s', strtotime($ngayKetThuc)),
            'TRANG_THAI' => $trangThai,
        ]);

        return response()->json(['message' => 'Cập nhật Voucher thành công', 'data' => $voucher]);
    }

    public function destroy($id)
    {
        $voucher = Voucher::where('MA_VOUCHER', $id)->first();
        if (!$voucher) {
            return response()->json(['error' => 'Không tìm thấy Voucher'], 404);
        }

        $voucher->delete();
        return response()->json(['message' => 'Xóa Voucher thành công']);
    }

    /**
     * Customer: Apply voucher to order
     */
    public function applyVoucher(Request $request)
    {
        $code = strtoupper(trim($request->input('voucher_code') ?? $request->input('maVoucher') ?? $request->input('code') ?? ''));
        $subtotal = (float)($request->input('subtotal') ?? $request->input('tongTien') ?? 0);

        if (empty($code)) {
            return response()->json(['error' => 'Vui lòng nhập mã Voucher!'], 400);
        }

        $voucher = Voucher::where('MA_VOUCHER', $code)->first();
        if (!$voucher) {
            return response()->json(['error' => "Mã voucher '{$code}' không tồn tại trong hệ thống."], 404);
        }

        if (!$voucher->TRANG_THAI) {
            return response()->json(['error' => "Voucher '{$code}' hiện đang bị ngưng áp dụng."], 400);
        }

        if ($voucher->NGAY_BAT_DAU && strtotime($voucher->NGAY_BAT_DAU) > time()) {
            return response()->json(['error' => "Voucher '{$code}' chưa đến thời gian áp dụng."], 400);
        }
        if ($voucher->NGAY_KET_THUC && strtotime($voucher->NGAY_KET_THUC) < time()) {
            return response()->json(['error' => "Voucher '{$code}' đã hết hạn sử dụng."], 400);
        }

        if ($voucher->SO_LUONG_DA_DUNG >= $voucher->SO_LUONG_GIOI_HAN) {
            return response()->json(['error' => "Voucher '{$code}' đã hết số lượng lượt sử dụng."], 400);
        }

        if ($subtotal < (float)$voucher->DON_TOI_THIEU) {
            return response()->json([
                'error' => "Đơn hàng của bạn (" . number_format($subtotal) . "đ) chưa đạt giá trị tối thiểu " . number_format($voucher->DON_TOI_THIEU) . "đ để áp dụng voucher này."
            ], 400);
        }

        $user = auth('api')->user();
        if ($user) {
            $userIds = $this->resolveUserIds($user);

            $usedInOrder = DB::table('donhang')
                ->whereIn('nguoidungId', $userIds)
                ->whereNotIn('trangThaiDonHang', [0, 6])
                ->where(function($q) use ($code) {
                    $q->where('maVoucher', $code)->orWhere('voucherId', $code);
                })
                ->exists();

            $usedInWallet = DB::table('voucher_khachhang')
                ->whereIn('nguoidungId', $userIds)
                ->where('maVoucher', $code)
                ->where('trangThai', 1)
                ->exists();

            if ($usedInOrder || $usedInWallet) {
                return response()->json([
                    'error' => "Bạn đã sử dụng mã voucher '{$code}' cho một đơn hàng trước đó rồi! Mỗi tài khoản chỉ được dùng 1 lần."
                ], 400);
            }
        }

        $discountAmount = 0;
        if ($voucher->LOAI_GIAM === 'PHAN_TRAM') {
            $discountAmount = $subtotal * ((float)$voucher->GIA_TRI / 100);
            if ($voucher->GIAM_TOI_DA && (float)$voucher->GIAM_TOI_DA > 0) {
                $discountAmount = min($discountAmount, (float)$voucher->GIAM_TOI_DA);
            }
        } else {
            $discountAmount = min((float)$voucher->GIA_TRI, $subtotal);
        }

        $discountAmount = round($discountAmount);

        return response()->json([
            'success' => true,
            'voucher_code' => $voucher->MA_VOUCHER,
            'voucher_name' => $voucher->TEN_VOUCHER,
            'discount_amount' => $discountAmount,
            'loai_giam' => $voucher->LOAI_GIAM,
            'gia_tri' => $voucher->GIA_TRI,
            'don_toi_thieu' => $voucher->DON_TOI_THIEU,
            'giam_toi_da' => $voucher->GIAM_TOI_DA,
            'message' => "Đã áp dụng mã " . $voucher->MA_VOUCHER . " (Giảm " . number_format($discountAmount) . "đ)"
        ]);
    }

    /**
     * Customer: Save / Collect voucher into wallet
     */
    public function collectVoucher(Request $request, $id = null)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Bạn cần đăng nhập để lưu voucher vào ví!'], 401);
        }

        $code = strtoupper(trim($id ?? $request->input('maVoucher') ?? $request->input('voucher_code') ?? $request->input('MA_VOUCHER') ?? ''));
        if (empty($code)) {
            return response()->json(['error' => 'Mã Voucher không hợp lệ!'], 400);
        }

        $voucher = Voucher::where('MA_VOUCHER', $code)->first();
        if (!$voucher || !$voucher->TRANG_THAI) {
            return response()->json(['error' => 'Mã Voucher không tồn tại hoặc đã ngưng hoạt động!'], 404);
        }

        if ($voucher->NGAY_KET_THUC && strtotime($voucher->NGAY_KET_THUC) < time()) {
            return response()->json(['error' => 'Voucher này đã hết hạn sử dụng!'], 400);
        }

        if ($voucher->SO_LUONG_DA_DUNG >= $voucher->SO_LUONG_GIOI_HAN) {
            return response()->json(['error' => 'Voucher này đã hết lượt thu thập!'], 400);
        }

        $userIds = $this->resolveUserIds($user);
        $primaryId = $this->resolvePrimaryNguoiDungId($user);

        $existing = DB::table('voucher_khachhang')
            ->whereIn('nguoidungId', $userIds)
            ->where('maVoucher', $code)
            ->first();

        if ($existing) {
            if ((int)$existing->trangThai === 1) {
                return response()->json(['error' => 'Bạn đã sử dụng Voucher này trước đó rồi!'], 400);
            }
            return response()->json([
                'success' => true,
                'already_saved' => true,
                'message' => 'Voucher này đã có sẵn trong Ví Voucher của bạn!'
            ], 200);
        }

        DB::table('voucher_khachhang')->insert([
            'nguoidungId' => $primaryId,
            'maVoucher' => $code,
            'ngayThuThap' => now(),
            'trangThai' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "🎉 Đã lưu thành công Voucher '{$code}' vào Ví Voucher của bạn!",
            'data' => $voucher
        ]);
    }

    /**
     * Customer: Get saved vouchers in wallet + available store vouchers to collect
     */
    public function getMyWallet(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'saved_vouchers' => [],
                'available_vouchers' => [],
                'used_vouchers' => [],
                'data' => [],
            ]);
        }

        $userIds = $this->resolveUserIds($user);

        $walletRows = DB::table('voucher_khachhang')
            ->whereIn('nguoidungId', $userIds)
            ->orderBy('id', 'desc')
            ->get();

        $savedCodes = $walletRows->where('trangThai', 0)->pluck('maVoucher')->map(fn($c) => strtoupper((string)$c))->toArray();
        $usedWalletCodes = $walletRows->where('trangThai', 1)->pluck('maVoucher')->map(fn($c) => strtoupper((string)$c))->toArray();

        $usedOrderCodes = DB::table('donhang')
            ->whereIn('nguoidungId', $userIds)
            ->whereNotIn('trangThaiDonHang', [0, 6])
            ->whereNotNull('maVoucher')
            ->pluck('maVoucher')
            ->map(fn($c) => strtoupper((string)$c))
            ->toArray();

        $allUsedCodes = array_values(array_unique(array_merge($usedWalletCodes, $usedOrderCodes)));

        $activeVouchers = Voucher::where('TRANG_THAI', 1)
            ->where('NGAY_KET_THUC', '>=', now())
            ->orderBy('GIA_TRI', 'desc')
            ->get();

        $savedVouchers = [];
        $availableToCollect = [];

        foreach ($activeVouchers as $v) {
            $code = strtoupper($v->MA_VOUCHER);
            $isUsed = in_array($code, $allUsedCodes, true);
            $isSaved = in_array($code, $savedCodes, true);

            $item = $v->toArray();
            $item['is_saved'] = $isSaved;
            $item['is_used'] = $isUsed;
            $item['con_lai'] = max(0, (int)$v->SO_LUONG_GIOI_HAN - (int)$v->SO_LUONG_DA_DUNG);

            if ($isSaved && !$isUsed) {
                $savedVouchers[] = $item;
            }
            if (!$isUsed && $item['con_lai'] > 0) {
                $availableToCollect[] = $item;
            }
        }

        return response()->json([
            'saved_vouchers' => $savedVouchers,
            'available_vouchers' => $availableToCollect,
            'used_codes' => $allUsedCodes,
            'data' => $savedVouchers,
        ]);
    }

    /**
     * Customer: Suggest best voucher for current cart subtotal
     */
    public function suggestForOrder(Request $request)
    {
        $subtotal = (float)($request->input('subtotal') ?? $request->input('tongTien') ?? 0);
        $user = auth('api')->user();
        $userUsedVouchers = [];
        $userSavedVouchers = [];

        if ($user) {
            $userIds = $this->resolveUserIds($user);

            $usedInOrder = DB::table('donhang')
                ->whereIn('nguoidungId', $userIds)
                ->whereNotIn('trangThaiDonHang', [0, 6])
                ->whereNotNull('maVoucher')
                ->pluck('maVoucher')
                ->map(fn($c) => strtoupper((string)$c))
                ->toArray();

            $usedInWallet = DB::table('voucher_khachhang')
                ->whereIn('nguoidungId', $userIds)
                ->where('trangThai', 1)
                ->pluck('maVoucher')
                ->map(fn($c) => strtoupper((string)$c))
                ->toArray();

            $userSavedVouchers = DB::table('voucher_khachhang')
                ->whereIn('nguoidungId', $userIds)
                ->where('trangThai', 0)
                ->pluck('maVoucher')
                ->map(fn($c) => strtoupper((string)$c))
                ->toArray();

            $userUsedVouchers = array_unique(array_merge($usedInOrder, $usedInWallet));
        }

        $allVouchers = Voucher::where('TRANG_THAI', 1)
            ->where('NGAY_BAT_DAU', '<=', now())
            ->where('NGAY_KET_THUC', '>=', now())
            ->where('SO_LUONG_DA_DUNG', '<', DB::raw('SO_LUONG_GIOI_HAN'))
            ->get();

        $eligibleVouchers = [];
        $bestVoucher = null;
        $maxDiscount = -1;

        foreach ($allVouchers as $v) {
            $code = strtoupper($v->MA_VOUCHER);
            if (in_array($code, $userUsedVouchers, true)) {
                continue;
            }

            $discount = 0;
            $isEligible = ($subtotal >= (float)$v->DON_TOI_THIEU);

            if ($v->LOAI_GIAM === 'PHAN_TRAM') {
                $discount = $subtotal * ((float)$v->GIA_TRI / 100);
                if ($v->GIAM_TOI_DA && (float)$v->GIAM_TOI_DA > 0) {
                    $discount = min($discount, (float)$v->GIAM_TOI_DA);
                }
            } else {
                $discount = min((float)$v->GIA_TRI, $subtotal);
            }
            $discount = round($discount);

            $vArr = $v->toArray();
            $vArr['calculated_discount'] = $discount;
            $vArr['is_eligible'] = $isEligible;
            $vArr['is_in_wallet'] = in_array($code, $userSavedVouchers, true);
            $vArr['min_order_diff'] = max(0, (float)$v->DON_TOI_THIEU - $subtotal);

            $eligibleVouchers[] = $vArr;

            if ($isEligible && $discount > $maxDiscount) {
                $maxDiscount = $discount;
                $bestVoucher = $vArr;
            }
        }

        usort($eligibleVouchers, function($a, $b) {
            if ($a['is_eligible'] !== $b['is_eligible']) {
                return $b['is_eligible'] <=> $a['is_eligible'];
            }
            if (($a['is_in_wallet'] ?? false) !== ($b['is_in_wallet'] ?? false)) {
                return ($b['is_in_wallet'] ?? false) <=> ($a['is_in_wallet'] ?? false);
            }
            return $b['calculated_discount'] <=> $a['calculated_discount'];
        });

        return response()->json([
            'best_voucher' => $bestVoucher,
            'available_vouchers' => $eligibleVouchers
        ]);
    }
}
