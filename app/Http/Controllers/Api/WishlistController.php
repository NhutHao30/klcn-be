<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Wishlist;
use App\Models\SanPham;
use App\Models\NguoiDung;

class WishlistController extends Controller
{
    private function getNguoiDung()
    {
        $user = Auth::guard('api')->user();
        if (!$user) return null;

        $nd = $user->nguoidung;
        if (!$nd) {
            $nd = NguoiDung::firstOrCreate(
                ['taiKhoanId' => $user->id],
                [
                    'hoTen' => $user->username ?? 'Khách hàng',
                    'soDienThoai' => '0900000000',
                    'loaiNguoiDung' => 0,
                ]
            );
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

        $items = Wishlist::with(['sanpham.loaiSanPham', 'sanpham.loai'])
            ->where('nguoidungId', $nd->id)
            ->orderBy('id', 'desc')
            ->get();

        $result = $items->map(function ($w) {
            $sp = $w->sanpham;
            if (!$sp) return null;
            return [
                'id' => $sp->id,
                'masp' => $sp->id,
                'MASP' => $sp->id,
                'name' => $sp->tenSanPham ?? $sp->TENSP ?? 'Sản phẩm',
                'tenSP' => $sp->tenSanPham ?? $sp->TENSP ?? 'Sản phẩm',
                'TenSP' => $sp->tenSanPham ?? $sp->TENSP ?? 'Sản phẩm',
                'price' => (float)($sp->giaBan ?? 0),
                'salePrice' => (float)($sp->giaGiam ?? $sp->giaBan ?? 0),
                'image' => $sp->hinhAnh ?? $sp->HINHANH,
                'category' => $sp->loaiSanPham?->tenLoai ?? 'Bánh ngọt',
                'sanpham' => $sp,
                'product' => [
                    'id' => $sp->id,
                    'masp' => $sp->id,
                    'name' => $sp->tenSanPham ?? $sp->TENSP ?? 'Sản phẩm',
                    'tenSP' => $sp->tenSanPham ?? $sp->TENSP ?? 'Sản phẩm',
                    'TenSP' => $sp->tenSanPham ?? $sp->TENSP ?? 'Sản phẩm',
                    'price' => (float)($sp->giaBan ?? 0),
                    'salePrice' => (float)($sp->giaGiam ?? $sp->giaBan ?? 0),
                    'image' => $sp->hinhAnh ?? $sp->HINHANH,
                    'hinh_anh' => $sp->hinhAnh ?? $sp->HINHANH,
                    'imageUrl' => $sp->hinhAnh ?? $sp->HINHANH,
                    'HINHANH' => $sp->hinhAnh ?? $sp->HINHANH,
                    'category' => $sp->loaiSanPham?->tenLoai ?? 'Bánh ngọt',
                ],
            ];
        })->filter()->values();

        return response()->json($result);
    }

    /**
     * Add to wishlist
     */
    public function store(Request $request)
    {
        $nd = $this->getNguoiDung();
        if (!$nd) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $rawId = $request->input('masp') ?? $request->input('MASP') ?? $request->input('sanPhamId') ?? $request->input('id');
        $spId = $this->resolveProductId($rawId);

        if (!$spId) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        $wish = Wishlist::firstOrCreate([
            'nguoidungId' => $nd->id,
            'sanPhamId' => $spId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã thêm sản phẩm vào danh sách yêu thích!',
            'data' => $wish
        ]);
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

        $spId = $this->resolveProductId($masp);
        if ($spId) {
            Wishlist::where('nguoidungId', $nd->id)->where('sanPhamId', $spId)->delete();
        } else {
            Wishlist::where('nguoidungId', $nd->id)->where('id', $masp)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa sản phẩm khỏi danh sách yêu thích!'
        ]);
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

        $spId = $this->resolveProductId($masp);
        $exists = Wishlist::where('nguoidungId', $nd->id)->where('sanPhamId', $spId)->exists();

        return response()->json(['is_favorite' => $exists]);
    }
}
