<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\Wishlist;
use App\Models\SanPham;
use App\Models\NguoiDung;

class WishlistController extends Controller
{
    private function getUserColumn(): string
    {
        try {
            if (Schema::hasColumn('wishlist', 'nguoidung_id')) {
                return 'nguoidung_id';
            }
        } catch (\Throwable $e) {}
        return 'nguoidungId';
    }

    private function getProductColumn(): string
    {
        try {
            if (Schema::hasColumn('wishlist', 'sanpham_id')) {
                return 'sanpham_id';
            }
        } catch (\Throwable $e) {}
        return 'sanPhamId';
    }

    private function getNguoiDung()
    {
        $user = Auth::guard('api')->user();
        if (!$user) return null;

        $nd = $user->nguoidung;
        if (!$nd) {
            try {
                $nd = NguoiDung::firstOrCreate(
                    ['taiKhoanId' => $user->id],
                    [
                        'hoTen' => $user->username ?? 'Khách hàng',
                        'soDienThoai' => '0900000000',
                        'loaiNguoiDung' => 0,
                    ]
                );
            } catch (\Throwable $e) {
                $nd = NguoiDung::where('taiKhoanId', $user->id)->first();
            }
        }
        return $nd;
    }

    private function resolveProductId($rawId)
    {
        if (empty($rawId)) return null;
        if (is_numeric($rawId)) {
            $sp = SanPham::find((int)$rawId);
            if ($sp) return $sp->id;
        }
        $clean = preg_replace('/\D/', '', (string)$rawId);
        if (!empty($clean)) {
            $sp = SanPham::find((int)$clean);
            if ($sp) return $sp->id;
        }
        $sp = SanPham::where('id', $rawId)->orWhere('slug', $rawId)->first();
        return $sp ? $sp->id : null;
    }

    /**
     * Get user's wishlist
     */
    public function index()
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        try {
            $userCol = $this->getUserColumn();
            $prodCol = $this->getProductColumn();

            $items = Wishlist::where($userCol, $nd->id)
                ->orderBy('id', 'desc')
                ->get();

            $result = $items->map(function ($w) use ($prodCol) {
                $spId = $w->{$prodCol} ?? $w->sanPhamId ?? $w->sanpham_id;
                $sp = SanPham::with(['loaiSanPham', 'loai'])->find($spId);
                if (!$sp) return null;

                $img = $sp->hinhAnh ?? $sp->HINHANH ?? $sp->hinh_anh;
                $ten = $sp->tenSanPham ?? $sp->TENSP ?? $sp->ten_san_pham ?? 'Sản phẩm';
                $gia = (float)($sp->giaBan ?? $sp->GIABAN ?? 0);
                $giaGiam = (float)($sp->giaGiam ?? $sp->giaBan ?? $sp->GIABAN ?? 0);

                return [
                    'id' => $sp->id,
                    'masp' => $sp->id,
                    'MASP' => $sp->id,
                    'name' => $ten,
                    'tenSP' => $ten,
                    'TenSP' => $ten,
                    'price' => $gia,
                    'salePrice' => $giaGiam,
                    'image' => $img,
                    'category' => $sp->loaiSanPham?->tenLoai ?? $sp->loai?->tenLoai ?? 'Bánh ngọt',
                    'sanpham' => $sp,
                    'product' => [
                        'id' => $sp->id,
                        'masp' => $sp->id,
                        'name' => $ten,
                        'tenSP' => $ten,
                        'TenSP' => $ten,
                        'price' => $gia,
                        'salePrice' => $giaGiam,
                        'image' => $img,
                        'hinh_anh' => $img,
                        'imageUrl' => $img,
                        'HINHANH' => $img,
                        'category' => $sp->loaiSanPham?->tenLoai ?? $sp->loai?->tenLoai ?? 'Bánh ngọt',
                    ],
                ];
            })->filter()->values();

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Wishlist index error: ' . $e->getMessage());
            return response()->json([], 200); // Trả về mảng rỗng an toàn thay vì crash 500
        }
    }

    /**
     * Add to wishlist
     */
    public function store(Request $request)
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Vui lòng đăng nhập để lưu sản phẩm yêu thích'], 401);
        }

        $rawId = $request->input('masp') ?? $request->input('MASP') ?? $request->input('sanPhamId') ?? $request->input('id');
        $spId = $this->resolveProductId($rawId);

        if (!$spId) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        try {
            $userCol = $this->getUserColumn();
            $prodCol = $this->getProductColumn();

            $existing = Wishlist::where($userCol, $nd->id)->where($prodCol, $spId)->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sản phẩm đã có trong danh sách yêu thích của bạn!',
                    'data' => $existing,
                    'already_exists' => true,
                ]);
            }

            $wish = new Wishlist();
            $wish->{$userCol} = $nd->id;
            $wish->{$prodCol} = $spId;

            // Thử lưu timestamps, nếu DB không có cột timestamps thì tắt timestamps
            try {
                $wish->save();
            } catch (\Throwable $te) {
                $wish->timestamps = false;
                $wish->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã thêm sản phẩm vào danh sách yêu thích!',
                'data' => $wish
            ]);
        } catch (\Throwable $e) {
            Log::error('Wishlist store error: ' . $e->getMessage());
            return response()->json(['error' => 'Lỗi lưu sản phẩm: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove from wishlist
     */
    public function destroy($masp)
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        try {
            $spId = $this->resolveProductId($masp);
            $userCol = $this->getUserColumn();
            $prodCol = $this->getProductColumn();

            if ($spId) {
                Wishlist::where($userCol, $nd->id)->where($prodCol, $spId)->delete();
            } else {
                Wishlist::where($userCol, $nd->id)->where('id', $masp)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa sản phẩm khỏi danh sách yêu thích!'
            ]);
        } catch (\Throwable $e) {
            Log::error('Wishlist destroy error: ' . $e->getMessage());
            return response()->json(['error' => 'Lỗi xóa sản phẩm: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if product is in wishlist
     */
    public function check($masp)
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['is_favorite' => false]);
        }

        try {
            $spId = $this->resolveProductId($masp);
            $userCol = $this->getUserColumn();
            $prodCol = $this->getProductColumn();

            $exists = Wishlist::where($userCol, $nd->id)->where($prodCol, $spId)->exists();
            return response()->json(['is_favorite' => $exists]);
        } catch (\Throwable $e) {
            return response()->json(['is_favorite' => false]);
        }
    }
}
