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
            ->where('sanpham_id', $sanphamId)
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
            'donhang_id' => 'required|exists:don_hang,id',
            'sanpham_id' => 'required|exists:san_pham,id',
            'so_sao' => 'required|integer|min:1|max:5',
            'noi_dung' => 'nullable|string',
        ]);

        $donHang = DonHang::find($request->donhang_id);
        if ($donHang->trang_thai_don_hang != 5) { // 5: DA_GIAO_THANH_CONG
            return response()->json(['error' => 'Chỉ có thể đánh giá sản phẩm khi đơn hàng đã giao thành công'], 400);
        }

        $review = DanhGiaSanPham::create([
            'donhang_id' => $request->donhang_id,
            'sanpham_id' => $request->sanpham_id,
            'nguoidung_id' => $user->nguoidung->id,
            'so_sao' => $request->so_sao,
            'noi_dung' => $request->noi_dung ?? null,
        ]);

        return response()->json(['message' => 'Đã gửi đánh giá sản phẩm thành công', 'data' => $review], 201);
    }
}
