<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\GioHang;
use App\Models\ChiTietGioHang;
use App\Models\SanPham;

class CartController extends Controller
{
    private function getCart()
    {
        $user = Auth::guard('api')->user();
        if (!$user) return null;

        $nguoidung = $user->nguoidung;
        if (!$nguoidung) return null;

        return GioHang::firstOrCreate(['nguoidung_id' => $nguoidung->id]);
    }

    public function index()
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        $items = ChiTietGioHang::with('sanpham')->where('giohang_id', $cart->id)->get();
        return response()->json(['cart_id' => $cart->id, 'items' => $items]);
    }

    public function addToCart(Request $request)
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        $request->validate([
            'sanpham_id' => 'required|exists:san_pham,id',
            'so_luong' => 'required|integer|min:1',
        ]);

        $item = ChiTietGioHang::where('giohang_id', $cart->id)
            ->where('sanpham_id', $request->sanpham_id)
            ->first();

        if ($item) {
            $item->increment('so_luong', $request->so_luong);
        } else {
            $item = ChiTietGioHang::create([
                'giohang_id' => $cart->id,
                'sanpham_id' => $request->sanpham_id,
                'so_luong' => $request->so_luong,
            ]);
        }

        return response()->json(['message' => 'Đã thêm sản phẩm vào giỏ hàng', 'data' => $item]);
    }

    public function updateCart(Request $request, $id)
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        $request->validate([
            'so_luong' => 'required|integer|min:1',
        ]);

        $item = ChiTietGioHang::where('giohang_id', $cart->id)->where('id', $id)->first();
        if (!$item) {
            return response()->json(['error' => 'Không tìm thấy món trong giỏ'], 404);
        }

        $item->update(['so_luong' => $request->so_luong]);
        return response()->json(['message' => 'Đã cập nhật số lượng', 'data' => $item]);
    }

    public function removeFromCart($id)
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        ChiTietGioHang::where('giohang_id', $cart->id)->where('id', $id)->delete();
        return response()->json(['message' => 'Đã xóa khỏi giỏ hàng']);
    }

    public function clearCart()
    {
        $cart = $this->getCart();
        if ($cart) {
            ChiTietGioHang::where('giohang_id', $cart->id)->delete();
        }

        return response()->json(['message' => 'Đã xóa sạch giỏ hàng']);
    }
}
