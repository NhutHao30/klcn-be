<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KhuyenMai;
use App\Models\KhuyenMaiKhachHang;
use App\Models\KhachHang;
use Illuminate\Support\Facades\Auth;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = KhuyenMai::orderBy('created_at', 'desc')->get();
        return response()->json($vouchers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'MA_VOUCHER' => 'required|string|unique:khuyenmai,MA_VOUCHER',
            'TEN_VOUCHER' => 'required|string',
            'LOAI_GIAM' => 'required|in:PHAN_TRAM,TIEN_MAT',
            'GIA_TRI' => 'required|integer|min:1',
            'DON_TOI_THIEU' => 'required|integer|min:0',
            'GIAM_TOI_DA' => 'nullable|integer|min:0',
            'SO_LUONG_GIOI_HAN' => 'required|integer|min:1',
            'NGAY_BAT_DAU' => 'required|date',
            'NGAY_KET_THUC' => 'required|date|after:NGAY_BAT_DAU',
            'TRANG_THAI' => 'boolean'
        ]);

        $voucher = KhuyenMai::create($request->all());

        return response()->json(['message' => 'Tạo voucher thành công', 'data' => $voucher], 201);
    }

    public function show($id)
    {
        $voucher = KhuyenMai::find($id);
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher'], 404);
        }
        return response()->json($voucher);
    }

    public function update(Request $request, $id)
    {
        $voucher = KhuyenMai::find($id);
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher'], 404);
        }

        $request->validate([
            'TEN_VOUCHER' => 'required|string',
            'LOAI_GIAM' => 'required|in:PHAN_TRAM,TIEN_MAT',
            'GIA_TRI' => 'required|integer|min:1',
            'DON_TOI_THIEU' => 'required|integer|min:0',
            'GIAM_TOI_DA' => 'nullable|integer|min:0',
            'SO_LUONG_GIOI_HAN' => 'required|integer|min:1',
            'NGAY_BAT_DAU' => 'required|date',
            'NGAY_KET_THUC' => 'required|date|after:NGAY_BAT_DAU',
            'TRANG_THAI' => 'boolean'
        ]);

        $voucher->update($request->all());

        return response()->json(['message' => 'Cập nhật voucher thành công', 'data' => $voucher]);
    }

    public function destroy($id)
    {
        $voucher = KhuyenMai::find($id);
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher'], 404);
        }

        $voucher->TRANG_THAI = !$voucher->TRANG_THAI;
        $voucher->save();

        $msg = $voucher->TRANG_THAI ? 'Đã kích hoạt voucher' : 'Đã vô hiệu hóa voucher';
        return response()->json(['message' => $msg]);
    }

    /**
     * 1. Khách hàng thu thập mã giảm giá vào ví cá nhân (khuyenmai_khachhang)
     */
    public function collectVoucher(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập để thu thập mã giảm giá'], 401);
        }

        $khachHang = KhachHang::where('USERNAME', $user->USERNAME)->first();
        if (!$khachHang) {
            return response()->json(['error' => 'Không tìm thấy thông tin khách hàng'], 404);
        }

        $voucher = KhuyenMai::where('MA_VOUCHER', $id)->first();
        if (!$voucher) {
            return response()->json(['error' => 'Mã giảm giá không tồn tại'], 404);
        }

        if (!$voucher->is_valid) {
            return response()->json(['error' => 'Mã giảm giá đã hết hạn, bị vô hiệu hóa hoặc hết lượt sử dụng trên hệ thống'], 400);
        }

        // Kiểm tra xem khách đã lưu mã này chưa
        $exists = KhuyenMaiKhachHang::where('MA_VOUCHER', $voucher->MA_VOUCHER)
            ->where('MAKH', $khachHang->MAKH)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Bạn đã lưu mã giảm giá này vào ví rồi'], 400);
        }

        $walletItem = KhuyenMaiKhachHang::create([
            'MA_VOUCHER' => $voucher->MA_VOUCHER,
            'MAKH' => $khachHang->MAKH,
            'NGAY_THU_THAP' => now(),
            'TRANG_THAI' => 'CHUA_SU_DUNG'
        ]);

        return response()->json([
            'message' => 'Thu thập mã giảm giá vào ví thành công',
            'data' => [
                'id' => $walletItem->id,
                'voucher' => $voucher
            ]
        ], 201);
    }

    /**
     * 2. Xem danh sách ví voucher cá nhân của khách hàng
     */
    public function getMyWallet(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $khachHang = KhachHang::where('USERNAME', $user->USERNAME)->first();
        if (!$khachHang) {
            return response()->json(['error' => 'Không tìm thấy thông tin khách hàng'], 404);
        }

        $query = KhuyenMaiKhachHang::with('voucher')
            ->where('MAKH', $khachHang->MAKH);

        $status = $request->input('status', 'all');
        if ($status === 'chua_su_dung') {
            $query->chuaSuDung();
        } elseif ($status === 'da_su_dung') {
            $query->daSuDung();
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        $now = now();
        $formatted = $items->map(function ($item) use ($now) {
            $voucher = $item->voucher;
            $isUsed = !empty($item->MAHD) || $item->TRANG_THAI === 'DA_SU_DUNG';
            $isExpired = $voucher ? ($voucher->NGAY_KET_THUC < $now) : true;
            $isValid = !$isUsed && !$isExpired && ($voucher ? $voucher->TRANG_THAI : false);

            return [
                'id' => $item->id,
                'ma_voucher' => $item->MA_VOUCHER,
                'ten_voucher' => $voucher ? $voucher->TEN_VOUCHER : null,
                'loai_giam' => $voucher ? $voucher->LOAI_GIAM : null,
                'gia_tri' => $voucher ? $voucher->GIA_TRI : 0,
                'don_toi_thieu' => $voucher ? $voucher->DON_TOI_THIEU : 0,
                'giam_toi_da' => $voucher ? $voucher->GIAM_TOI_DA : null,
                'ngay_bat_dau' => $voucher ? $voucher->NGAY_BAT_DAU : null,
                'ngay_ket_thuc' => $voucher ? $voucher->NGAY_KET_THUC : null,
                'ngay_thu_thap' => $item->NGAY_THU_THAP,
                'ngay_su_dung' => $item->NGAY_SU_DUNG,
                'ma_hd_su_dung' => $item->MAHD,
                'trang_thai' => $isUsed ? 'DA_SU_DUNG' : ($isExpired ? 'HET_HAN' : 'CHUA_SU_DUNG'),
                'is_valid' => $isValid
            ];
        });

        return response()->json(['wallet' => $formatted]);
    }

    /**
     * 3. Gợi ý các voucher phù hợp & tốt nhất cho đơn hàng hiện tại của khách
     */
    public function suggestForOrder(Request $request)
    {
        $request->validate([
            'subtotal' => 'required|numeric|min:0'
        ]);

        $subtotal = (float)$request->subtotal;
        $user = Auth::guard('api')->user();

        // Lấy danh sách tất cả các voucher đang có hiệu lực trên hệ thống
        $allVouchers = KhuyenMai::where('TRANG_THAI', 1)
            ->where('SO_LUONG_DA_DUNG', '<', \DB::raw('SO_LUONG_GIOI_HAN'))
            ->where('NGAY_BAT_DAU', '<=', now())
            ->where('NGAY_KET_THUC', '>=', now())
            ->get();

        $collectedVoucherCodes = [];
        $usedVoucherCodes = [];

        if ($user) {
            $khachHang = KhachHang::where('USERNAME', $user->USERNAME)->first();
            if ($khachHang) {
                $myWallet = KhuyenMaiKhachHang::where('MAKH', $khachHang->MAKH)->get();
                foreach ($myWallet as $item) {
                    if (!empty($item->MAHD) || $item->TRANG_THAI === 'DA_SU_DUNG') {
                        $usedVoucherCodes[] = $item->MA_VOUCHER;
                    } else {
                        $collectedVoucherCodes[] = $item->MA_VOUCHER;
                    }
                }
            }
        }

        $eligibleVouchers = [];
        $ineligibleVouchers = [];

        foreach ($allVouchers as $voucher) {
            // Nếu khách đã dùng mã này rồi thì bỏ qua
            if (in_array($voucher->MA_VOUCHER, $usedVoucherCodes)) {
                continue;
            }

            // Tính số tiền giảm
            $discountAmount = 0;
            if ($voucher->LOAI_GIAM === 'PHAN_TRAM') {
                $discountAmount = $subtotal * ($voucher->GIA_TRI / 100);
                if ($voucher->GIAM_TOI_DA && $discountAmount > $voucher->GIAM_TOI_DA) {
                    $discountAmount = (float)$voucher->GIAM_TOI_DA;
                }
            } else {
                $discountAmount = (float)$voucher->GIA_TRI;
            }
            $discountAmount = min($discountAmount, $subtotal);

            $isCollected = in_array($voucher->MA_VOUCHER, $collectedVoucherCodes);
            $isEligible = ($subtotal >= $voucher->DON_TOI_THIEU);

            $itemData = [
                'ma_voucher' => $voucher->MA_VOUCHER,
                'ten_voucher' => $voucher->TEN_VOUCHER,
                'loai_giam' => $voucher->LOAI_GIAM,
                'gia_tri' => $voucher->GIA_TRI,
                'don_toi_thieu' => $voucher->DON_TOI_THIEU,
                'giam_toi_da' => $voucher->GIAM_TOI_DA,
                'discount_amount' => $discountAmount,
                'ngay_ket_thuc' => $voucher->NGAY_KET_THUC,
                'is_collected' => $isCollected,
                'is_eligible' => $isEligible,
                'amount_needed' => max(0, $voucher->DON_TOI_THIEU - $subtotal)
            ];

            if ($isEligible) {
                $eligibleVouchers[] = $itemData;
            } else {
                $ineligibleVouchers[] = $itemData;
            }
        }

        // Sắp xếp voucher khả dụng ưu tiên giảm nhiều tiền nhất
        usort($eligibleVouchers, function ($a, $b) {
            return $b['discount_amount'] <=> $a['discount_amount'];
        });

        // Sắp xếp voucher chưa đủ điều kiện theo giá trị thiếu từ nhỏ đến lớn
        usort($ineligibleVouchers, function ($a, $b) {
            return $a['amount_needed'] <=> $b['amount_needed'];
        });

        return response()->json([
            'subtotal' => $subtotal,
            'recommended_voucher' => count($eligibleVouchers) > 0 ? $eligibleVouchers[0] : null,
            'eligible_vouchers' => $eligibleVouchers,
            'ineligible_vouchers' => $ineligibleVouchers
        ]);
    }
}
