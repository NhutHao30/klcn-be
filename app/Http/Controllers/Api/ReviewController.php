<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DanhGiaSanPham;
use App\Models\DonHang;

class ReviewController extends Controller
{
    public function getProductReviews($sanphamId)
    {
        $reviews = DanhGiaSanPham::with(['nguoidung'])
            ->where('sanPhamId', $sanphamId)
            ->orderBy('id', 'desc')
            ->get();
        return response()->json($reviews);
    }

    public function store(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user || !$user->nguoidung) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $request->validate([
            'donHangId' => 'required|exists:donHang,id',
            'sanPhamId' => 'required|exists:sanPham,id',
            'so_sao' => 'required|integer|min:1|max:5',
            'noi_dung' => 'nullable|string',
        ]);

        $donHang = DonHang::find($request->donHangId);
        if ($donHang->trangThai_don_hang != 5) { // 5: DA_GIAO_THANH_CONG
            return response()->json(['error' => 'Chỉ có thể đánh giá sản phẩm khi đơn hàng đã giao thành công'], 400);
        }

        $review = DanhGiaSanPham::create([
            'donHangId' => $request->donHangId,
            'sanPhamId' => $request->sanPhamId,
            'nguoidungId' => $user->nguoidung->id,
            'so_sao' => $request->so_sao,
            'noi_dung' => $request->noi_dung ?? null,
        ]);

        return response()->json(['message' => 'Đã gửi đánh giá sản phẩm thành công', 'data' => $review], 201);
    }
}
