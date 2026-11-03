<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DanhGiaSanPham;
use App\Models\DonHang;
use App\Models\SanPham;

class ReviewController extends Controller
{
    /**
     * Get reviews for a specific product
     */
    public function getProductReviews($sanphamId)
    {
        $sp = null;
        if (is_numeric($sanphamId)) {
            $sp = SanPham::find((int)$sanphamId);
        }
        if (!$sp) {
            $cleanId = (int) preg_replace('/\D/', '', (string) $sanphamId);
            if ($cleanId > 0) {
                $sp = SanPham::find($cleanId);
            }
        }
        if (!$sp) {
            $sp = SanPham::where('slug', $sanphamId)->first();
        }

        $resolvedSpId = $sp ? $sp->id : (is_numeric($sanphamId) ? (int)$sanphamId : $sanphamId);

        $reviews = DanhGiaSanPham::with(['nguoidung.taikhoan', 'sanpham', 'binhLuans.nguoidung.taikhoan'])
            ->where('sanPhamId', $resolvedSpId)
            ->where(function ($q) {
                $q->whereNull('trangThai')->orWhere('trangThai', 1);
            })
            ->orderBy('id', 'desc')
            ->get();

        $data = $reviews->map(function ($r) {
            $nd = $r->nguoidung;
            $customerName = $nd->hoTen ?? $nd->HOTEN ?? 'Khách hàng';
            $dateFormatted = ($r->createdAt ?? $r->created_at) ? date('d/m/Y H:i', strtotime($r->createdAt ?? $r->created_at)) : '';
            $replyDateFormatted = $r->ngayPhanHoi ? date('d/m/Y H:i', strtotime($r->ngayPhanHoi)) : '';

            $comments = ($r->binhLuans ?? collect())->map(function ($bl) {
                $cUser = $bl->nguoidung;
                $tk = $bl->taikhoan ?? $cUser?->taikhoan;
                $cName = $cUser->hoTen ?? $tk?->username ?? 'Khách hàng';
                $role = (int)($tk?->vaiTroId ?? $tk?->MAROLE ?? 3);
                $isAdmin = in_array($role, [0, 1, 2, 4, 5, 8]) || strtolower($tk?->username ?? '') === 'admin';

                return [
                    'id' => $bl->id,
                    'noiDung' => $bl->noiDung,
                    'NOIDUNG' => $bl->noiDung,
                    'hoTen' => $cName,
                    'avatar' => $tk?->avatar ?? $cUser?->avatar ?? null,
                    'isAdmin' => $isAdmin,
                    'created_at' => $bl->createdAt ?? $bl->created_at,
                    'date' => ($bl->createdAt ?? $bl->created_at) ? date('d/m/Y H:i', strtotime($bl->createdAt ?? $bl->created_at)) : '',
                ];
            })->values()->all();

            return [
                'id' => $r->id,
                'MADG' => $r->id,
                'soSao' => (int)$r->soSao,
                'SO_SAO' => (int)$r->soSao,
                'rating' => (int)$r->soSao,
                'noiDung' => $r->noiDung,
                'NOIDUNG' => $r->noiDung,
                'NOI_DUNG' => $r->noiDung,
                'comment' => $r->noiDung,
                'hinhAnhKemTheo' => $r->hinhAnhKemTheo,
                'HINH_ANH' => $r->hinhAnhKemTheo,
                'created_at' => $r->createdAt ?? $r->created_at,
                'date' => $dateFormatted,
                'NGAY_DG' => $dateFormatted,
                'hoTen' => $customerName,
                'khachHang' => $customerName,
                'TEN_KHACH_HANG' => $customerName,
                'avatar' => $nd->taikhoan?->avatar ?? null,
                'AVATAR' => $nd->taikhoan?->avatar ?? null,
                'phanHoiCskh' => $r->phanHoiCskh,
                'PHAN_HOI_CSKH' => $r->phanHoiCskh,
                'ngayPhanHoi' => $replyDateFormatted,
                'NGAY_PHAN_HOI' => $replyDateFormatted,
                'nguoidung' => $nd,
                'binhLuans' => $comments,
                'comments' => $comments,
            ];
        });

        return response()->json($data);
    }

    /**
     * Store new review (supports both method names: store & storeReview)
     */
    public function storeReview(Request $request)
    {
        return $this->store($request);
    }

    public function store(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập để thực hiện đánh giá'], 401);
        }

        $nguoidung = $user->nguoidung;
        if (!$nguoidung) {
            $nguoidung = \App\Models\NguoiDung::firstOrCreate(
                ['taiKhoanId' => $user->id],
                [
                    'hoTen' => $user->username ?? 'Khách hàng',
                    'soDienThoai' => '0900000000',
                    'loaiNguoiDung' => 0,
                ]
            );
        }

        // 1. Resolve DonHang (can be ID or maDonHang like 'HD...')
        $rawHd = $request->input('donHangId') 
            ?? $request->input('donhang_id') 
            ?? $request->input('MAHD') 
            ?? $request->input('maHD') 
            ?? $request->input('order_id');

        if (!$rawHd) {
            return response()->json(['error' => 'Mã hóa đơn không được để trống'], 400);
        }

        $donHang = DonHang::where('id', $rawHd)
            ->orWhere('maDonHang', $rawHd)
            ->first();

        if (!$donHang) {
            return response()->json(['error' => 'Không tìm thấy thông tin đơn hàng này'], 404);
        }

        // Kiểm tra quyền sở hữu đơn hàng (chỉ khách hàng đặt đơn hoặc quản trị viên mới được đánh giá)
        $orderOwnerId = $donHang->nguoidungId ?? $donHang->nguoiDungId;
        if ($orderOwnerId && $orderOwnerId != $nguoidung->id && (int)$user->vaiTroId !== 1 && strtolower($user->username) !== 'admin') {
            return response()->json(['error' => 'Bạn không thể đánh giá đơn hàng của người khác'], 403);
        }

        // Cho phép đánh giá khi đơn hàng đã hoàn thành (trangThaiDonHang = 4 HOAN_THANH hoặc 5 Đã giao)
        if (!in_array((int)$donHang->trangThaiDonHang, [4, 5])) {
            return response()->json(['error' => 'Bạn chỉ có thể đánh giá sản phẩm sau khi đơn hàng đã được giao hoàn thành'], 400);
        }

        // 2. Resolve SanPham list for this order (Khách hàng đánh giá 1 lần -> tự động áp dụng chung cho TẤT CẢ sản phẩm trong đơn)
        $rawSp = $request->input('sanPhamId') 
            ?? $request->input('sanpham_id') 
            ?? $request->input('MASP') 
            ?? $request->input('maSP') 
            ?? $request->input('product_id');

        $orderProductIds = $donHang->chitiet ? $donHang->chitiet->pluck('sanPhamId')->unique()->filter()->values()->all() : [];
        if (empty($orderProductIds)) {
            $orderProductIds = \App\Models\ChiTietDonHang::where('donHangId', $donHang->id)->pluck('sanPhamId')->unique()->filter()->values()->all();
        }

        $productIds = !empty($orderProductIds) ? $orderProductIds : [];
        if (empty($productIds) && $rawSp) {
            $sanPham = null;
            if (is_numeric($rawSp)) {
                $sanPham = SanPham::find((int)$rawSp);
            } else {
                $cleanId = (int) preg_replace('/\D/', '', (string) $rawSp);
                $sanPham = SanPham::where('id', $cleanId)->orWhere('id', $rawSp)->orWhere('slug', $rawSp)->first();
            }
            if ($sanPham) {
                $productIds = [$sanPham->id];
            }
        }

        if (empty($productIds)) {
            return response()->json(['error' => 'Đơn hàng không có sản phẩm nào để đánh giá'], 400);
        }

        // 3. Resolve rating & comment
        $rating = (int) ($request->input('soSao') 
            ?? $request->input('so_sao') 
            ?? $request->input('SO_SAO') 
            ?? $request->input('rating') 
            ?? 5);

        if ($rating < 1 || $rating > 5) {
            return response()->json(['error' => 'Số sao đánh giá phải từ 1 đến 5'], 400);
        }

        $comment = $request->input('noiDung') 
            ?? $request->input('noi_dung') 
            ?? $request->input('NOI_DUNG') 
            ?? $request->input('comment') 
            ?? '';

        // 4. Handle review image upload if any
        $imageUrl = null;
        if ($request->hasFile('image') || $request->hasFile('hinhAnh') || $request->hasFile('HINH_ANH')) {
            $file = $request->file('image') ?? $request->file('hinhAnh') ?? $request->file('HINH_ANH');
            try {
                $uploadService = new \App\Services\UploadService();
                $imageUrl = $uploadService->uploadImage($file, 'reviews');
            } catch (\Exception $e) {
                $filename = time() . '_review_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file->getClientOriginalName());
                $dest = public_path('assets/reviews');
                if (!file_exists($dest)) {
                    @mkdir($dest, 0777, true);
                }
                $file->move($dest, $filename);
                $imageUrl = '/assets/reviews/' . $filename;
            }
        } elseif ($request->input('image_url') || $request->input('HINH_ANH')) {
            $imageUrl = $request->input('image_url') ?? $request->input('HINH_ANH');
        }

        // 5. Nếu có sanPhamId cụ thể → đánh giá sản phẩm đó, nếu không → tất cả
        $savedReviews = [];
        $targetIds = $rawSp ? [$productIds[0] ?? null] : $productIds;
        // Nếu có sanPhamId cụ thể từ request, resolve ID đó
        if ($rawSp) {
            $spResolved = null;
            if (is_numeric($rawSp)) {
                $spResolved = SanPham::find((int)$rawSp);
            } else {
                $cleanId = (int) preg_replace('/\D/', '', (string) $rawSp);
                $spResolved = SanPham::where('id', $cleanId)->orWhere('id', $rawSp)->orWhere('slug', $rawSp)->first();
            }
            if ($spResolved && in_array($spResolved->id, $orderProductIds)) {
                $targetIds = [$spResolved->id];
            } else {
                $targetIds = $productIds;
            }
        }

        foreach ($targetIds as $spId) {
            if (!$spId) continue;
            $savedReviews[] = DanhGiaSanPham::updateOrCreate(
                [
                    'donHangId' => $donHang->id,
                    'sanPhamId' => $spId,
                ],
                [
                    'nguoidungId' => $nguoidung->id,
                    'soSao' => $rating,
                    'noiDung' => $comment,
                    'hinhAnhKemTheo' => $imageUrl,
                    'trangThai' => 1,
                ]
            );
            try {
                \Illuminate\Support\Facades\Cache::forget('product_detail_' . $spId);
            } catch (\Exception $eCache) {}
        }

        $msg = count($targetIds) === 1
            ? 'Cảm ơn bạn đã đánh giá sản phẩm!'
            : 'Cảm ơn bạn đã đánh giá! Đánh giá đã được ghi nhận cho tất cả sản phẩm trong đơn.';

        return response()->json([
            'message' => $msg,
            'data' => count($savedReviews) > 0 ? $savedReviews[0]->load(['sanpham', 'nguoidung']) : null,
            'total_reviewed' => count($savedReviews)
        ], 201);
    }

    /**
     * Admin: get all reviews with filters
     */
    public function getAdminReviews(Request $request)
    {
        $perPage = (int) ($request->input('per_page') ?? 20);
        $search = $request->input('search', '');
        $soSao = $request->input('soSao');
        $trangThai = $request->input('trangThai');
        $sanPhamId = $request->input('sanPhamId');

        $query = DanhGiaSanPham::with(['nguoidung.taikhoan', 'sanpham', 'donhang'])
            ->orderBy('id', 'desc');

        if ($soSao) {
            $query->where('soSao', (int)$soSao);
        }
        if ($trangThai !== null && $trangThai !== '') {
            $query->where('trangThai', (int)$trangThai);
        }
        if ($sanPhamId) {
            $query->where('sanPhamId', (int)$sanPhamId);
        }
        if ($search) {
            $query->whereHas('nguoidung', function($q) use ($search) {
                $q->where('hoTen', 'like', "%{$search}%");
            })->orWhereHas('sanpham', function($q) use ($search) {
                $q->where('tenSanPham', 'like', "%{$search}%");
            })->orWhere('noiDung', 'like', "%{$search}%");
        }

        $reviews = $query->paginate($perPage);

        $reviews->getCollection()->transform(function($r) {
            $nd = $r->nguoidung;
            $sp = $r->sanpham;
            return [
                'id' => $r->id,
                'sanPhamId' => $r->sanPhamId,
                'donHangId' => $r->donHangId,
                'maDonHang' => $r->donhang ? $r->donhang->maDonHang : '',
                'soSao' => (int)$r->soSao,
                'noiDung' => $r->noiDung,
                'hinhAnhKemTheo' => $r->hinhAnhKemTheo,
                'trangThai' => (int)($r->trangThai ?? 1),
                'phanHoiCskh' => $r->phanHoiCskh,
                'ngayPhanHoi' => $r->ngayPhanHoi,
                'createdAt' => $r->createdAt,
                'tenKH' => $nd ? $nd->hoTen : 'Khách hàng',
                'tenSP' => $sp ? ($sp->tenSanPham ?? '') : '',
                'hinhAnhSP' => $sp ? ($sp->hinhAnh ?? null) : null,
                'nguoidung' => $nd,
                'sanpham' => $sp,
            ];
        });

        return response()->json($reviews);
    }

    /**
     * Admin: toggle or delete review
     */
    public function toggleReviewStatus($id)
    {
        return $this->toggleStatus($id);
    }

    /**
     * Admin/CSKH: Toggle review status (ẩn/hiện)
     */
    public function toggleStatus($id)
    {
        $review = DanhGiaSanPham::find($id);
        if (!$review) {
            return response()->json(['error' => 'Không tìm thấy đánh giá'], 404);
        }
        $review->trangThai = $review->trangThai ? 0 : 1;
        $review->save();
        return response()->json([
            'message' => $review->trangThai ? 'Đã hiện đánh giá' : 'Đã ẩn đánh giá',
            'data' => $review,
            'trangThai' => $review->trangThai,
        ]);
    }

    /**
     * Admin/CSKH: Reply to review
     */
    public function reply(Request $request, $id)
    {
        $review = DanhGiaSanPham::find($id);
        if (!$review) {
            return response()->json(['error' => 'Không tìm thấy đánh giá'], 404);
        }
        $user = Auth::guard('api')->user();
        $phanHoi = $request->input('phanHoi') ?? $request->input('phanHoiCskh') ?? $request->input('reply') ?? '';
        $review->phanHoiCskh = $phanHoi;
        $review->cskhId = $user ? $user->id : null;
        $review->ngayPhanHoi = now();
        $review->save();
        return response()->json([
            'message' => 'Phản hồi đã được lưu',
            'data' => $review,
        ]);
    }

    /**
     * Cho phép TẤT CẢ khách hàng (đã đăng nhập) bình luận / thảo luận vào bài đánh giá
     */
    public function commentReview(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Vui lòng đăng nhập để bình luận'], 401);
        }

        $request->validate([
            'noiDung' => 'required|string|max:1000',
        ]);

        $review = DanhGiaSanPham::find($id);
        if (!$review) {
            return response()->json(['error' => 'Không tìm thấy đánh giá'], 404);
        }

        $nguoidung = $user->nguoidung;
        if (!$nguoidung) {
            $nguoidung = \App\Models\NguoiDung::firstOrCreate(
                ['taiKhoanId' => $user->id],
                [
                    'hoTen' => $user->username ?? 'Khách hàng',
                    'soDienThoai' => '0900000000',
                    'loaiNguoiDung' => 0,
                ]
            );
        }

        $comment = \App\Models\BinhLuanDanhGia::create([
            'danhGiaId' => $review->id,
            'nguoidungId' => $nguoidung->id,
            'taiKhoanId' => $user->id,
            'noiDung' => trim($request->input('noiDung')),
            'parentId' => $request->input('parentId'),
            'trangThai' => 1,
        ]);

        $role = (int)($user->vaiTroId ?? $user->MAROLE ?? 3);
        $isAdmin = in_array($role, [0, 1, 2, 4, 5, 8]) || strtolower($user->username ?? '') === 'admin';

        return response()->json([
            'success' => true,
            'message' => 'Gửi bình luận thành công!',
            'data' => [
                'id' => $comment->id,
                'noiDung' => $comment->noiDung,
                'NOIDUNG' => $comment->noiDung,
                'hoTen' => $nguoidung->hoTen ?? $user->username ?? 'Khách hàng',
                'avatar' => $user->avatar ?? $nguoidung->avatar ?? null,
                'isAdmin' => $isAdmin,
                'created_at' => $comment->createdAt,
                'date' => date('d/m/Y H:i'),
            ],
        ], 201);
    }
}
