<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DanhGiaSanPham;
use App\Models\KhachHang;
use App\Models\HdBan;
use App\Models\ChiTietHdBan;
use Illuminate\Support\Facades\Auth;
use App\Services\UploadService;
use Carbon\Carbon;

class ReviewController extends Controller
{
    // GET: /api/products/{id}/reviews
    public function getProductReviews($masp)
    {
        $reviews = DanhGiaSanPham::with('khachhang')
            ->where('MASP', $masp)
            ->where('TRANG_THAI', true)
            ->orderBy('NGAY_DG', 'desc')
            ->get();

        $mapped = $reviews->map(function ($review) {
            $avatar = $review->khachhang->AVATAR ?? null;
            if ($avatar && !str_starts_with($avatar, 'http')) {
                // If it's just filename, frontend will prepend path, or we can send it directly
            }
            return [
                'MADG' => $review->MADG,
                'SO_SAO' => $review->SO_SAO,
                'NOI_DUNG' => $review->NOI_DUNG,
                'HINH_ANH' => $review->HINH_ANH,
                'NGAY_DG' => Carbon::parse($review->NGAY_DG)->format('d/m/Y H:i'),
                'TEN_KHACH_HANG' => $review->khachhang->TENKH ?? 'Khách hàng',
                'AVATAR' => $avatar
            ];
        });

        return response()->json($mapped);
    }

    // POST: /api/reviews
    public function storeReview(Request $request)
    {
        $request->validate([
            'MASP' => 'required|string',
            'MAHD' => 'required|string',
            'SO_SAO' => 'required|integer|min:1|max:5',
            'NOI_DUNG' => 'nullable|string',
            'image' => 'nullable|image|max:5120' // 5MB max
        ]);

        $user = Auth::guard('api')->user();
        $khachHang = KhachHang::where('USERNAME', $user->USERNAME)->first();

        if (!$khachHang) {
            return response()->json(['error' => 'Bạn phải là khách hàng mới có thể đánh giá.'], 403);
        }

        // Verify if user bought this product in this order
        $order = HdBan::where('MAHD', $request->MAHD)
            ->where('MAKH', $khachHang->MAKH)
            ->where('TRANGTHAITHANHTOAN', 'Đã hoàn thành') // Fix column and string value
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Đơn hàng không hợp lệ hoặc chưa hoàn thành.'], 400);
        }

        $bought = ChiTietHdBan::where('MAHD', $request->MAHD)
            ->where('MASP', $request->MASP)
            ->exists();

        if (!$bought) {
            return response()->json(['error' => 'Sản phẩm này không nằm trong đơn hàng của bạn.'], 400);
        }

        // Check if already reviewed
        $existing = DanhGiaSanPham::where('MAHD', $request->MAHD)
            ->where('MASP', $request->MASP)
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Bạn đã đánh giá sản phẩm này cho đơn hàng này rồi.'], 400);
        }

        try {
            $imageUrl = null;
            if ($request->hasFile('image')) {
                $uploadService = new UploadService();
                $imageUrl = $uploadService->uploadImage($request->file('image'), 'reviews');
            }

            $review = DanhGiaSanPham::create([
                'MASP' => $request->MASP,
                'MAKH' => $khachHang->MAKH,
                'MAHD' => $request->MAHD,
                'SO_SAO' => $request->SO_SAO,
                'NOI_DUNG' => $request->NOI_DUNG,
                'HINH_ANH' => $imageUrl,
                'NGAY_DG' => Carbon::now(),
                'TRANG_THAI' => true
            ]);

            return response()->json(['message' => 'Đánh giá thành công!', 'data' => $review]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    // GET: /api/admin/reviews (For Admin)
    public function getAdminReviews()
    {
        $reviews = DanhGiaSanPham::with(['khachhang', 'sanpham'])->orderBy('NGAY_DG', 'desc')->get();
        return response()->json($reviews);
    }

    // PUT: /api/admin/reviews/{id}/toggle (Hide/Show review)
    public function toggleReviewStatus($id)
    {
        $review = DanhGiaSanPham::findOrFail($id);
        $review->TRANG_THAI = !$review->TRANG_THAI;
        $review->save();

        return response()->json(['message' => 'Cập nhật trạng thái thành công']);
    }
}
