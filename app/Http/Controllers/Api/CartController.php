<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\GioHang;
use App\Models\ChiTietGioHang;
use App\Models\SanPham;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;
use App\Models\KhuyenMai;
use App\Models\TonKhoChiNhanh;

class CartController extends Controller
{
    private function getCart()
    {
        $user = Auth::guard('api')->user();
        if (!$user) return null;

        $nguoidung = $user->nguoidung;
        if (!$nguoidung) return null;

        return GioHang::firstOrCreate(['nguoidungId' => $nguoidung->id]);
    }

    public function index()
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        $items = ChiTietGioHang::with('sanpham')->where('gioHangId', $cart->id)->get();
        return response()->json(['cart_id' => $cart->id, 'items' => $items]);
    }

    public function addToCart(Request $request)
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        $request->merge([
            'sanPhamId' => $request->input('sanPhamId') ?? $request->input('masp') ?? $request->input('MASP') ?? $request->input('product_id'),
            'soLuong' => $request->input('soLuong') ?? $request->input('quantity') ?? $request->input('soluong') ?? 1,
        ]);

        $request->validate([
            'sanPhamId' => 'required|exists:sanPham,id',
            'soLuong' => 'required|integer|min:1',
        ]);

        $item = ChiTietGioHang::where('gioHangId', $cart->id)
            ->where('sanPhamId', $request->sanPhamId)
            ->first();

        $product = SanPham::find($request->sanPhamId);
        $stock = $product ? (int) $product->soLuong_ton : 0;
        $existingQty = $item ? (int) $item->soLuong : 0;
        $totalRequested = $existingQty + (int) $request->soLuong;

        if ($stock <= 0) {
            return response()->json(['error' => 'Sản phẩm này hiện tại đã hết hàng!'], 400);
        }

        if ($totalRequested > $stock) {
            return response()->json(['error' => "Số lượng sản phẩm trong kho không đủ (Rất tiếc hiện tại chỉ còn {$stock} sản phẩm)!"], 400);
        }

        if ($item) {
            $item->increment('soLuong', $request->soLuong);
        } else {
            $item = ChiTietGioHang::create([
                'gioHangId' => $cart->id,
                'sanPhamId' => $request->sanPhamId,
                'soLuong' => $request->soLuong,
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

        $request->merge([
            'soLuong' => $request->input('soLuong') ?? $request->input('quantity') ?? $request->input('soluong') ?? 1,
        ]);

        $request->validate([
            'soLuong' => 'required|integer|min:1',
        ]);

        $item = ChiTietGioHang::where('gioHangId', $cart->id)
            ->where(function($q) use ($id) {
                $q->where('id', $id)->orWhere('sanPhamId', $id);
            })->first();

        if (!$item) {
            return response()->json(['error' => 'Không tìm thấy món trong giỏ'], 404);
        }

        $product = SanPham::find($item->sanPhamId);
        $stock = $product ? (int) $product->soLuong_ton : 0;
        if ((int) $request->soLuong > $stock) {
            return response()->json(['error' => "Số lượng sản phẩm trong kho không đủ (Rất tiếc hiện tại chỉ còn {$stock} sản phẩm)!"], 400);
        }

        $item->update(['soLuong' => $request->soLuong]);
        return response()->json(['message' => 'Đã cập nhật số lượng', 'data' => $item]);
    }

    public function removeFromCart($id)
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        ChiTietGioHang::where('gioHangId', $cart->id)
            ->where(function($q) use ($id) {
                $q->where('id', $id)->orWhere('sanPhamId', $id);
            })
            ->delete();
        return response()->json(['message' => 'Đã xóa khỏi giỏ hàng']);
    }

    public function clearCart()
    {
        $cart = $this->getCart();
        if ($cart) {
            ChiTietGioHang::where('gioHangId', $cart->id)->delete();
        }

        return response()->json(['message' => 'Đã xóa sạch giỏ hàng']);
    }

    public function checkout(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Bạn chưa đăng nhập!'], 401);
        }

        $nguoidung = $user->nguoidung;
        if (!$nguoidung) {
            $nguoidung = \App\Models\NguoiDung::create([
                'taiKhoanId' => $user->id,
                'hoTen' => $user->username ?? 'Khách hàng',
                'soDienThoai' => '0900000000',
                'loaiNguoiDung' => 0,
            ]);
        }

        $cart = GioHang::where('nguoidungId', $nguoidung->id)->first();
        if (!$cart) {
            return response()->json(['error' => 'Giỏ hàng của bạn đang trống!'], 400);
        }

        $cartItems = ChiTietGioHang::with('sanpham')->where('gioHangId', $cart->id)->get();
        if ($cartItems->isEmpty()) {
            return response()->json(['error' => 'Giỏ hàng của bạn đang trống!'], 400);
        }

        $paymentMethodStr = $request->input('paymentMethod') ?? $request->input('phuongThucThanhToan') ?? 'COD';
        $pmCode = 0; // 0: COD, 1: VIETQR/CK, 2: MOMO
        if ($paymentMethodStr === 'VIETQR' || $paymentMethodStr === '1' || $paymentMethodStr == 1) {
            $pmCode = 1;
        } else if ($paymentMethodStr === 'MOMO' || $paymentMethodStr === '2' || $paymentMethodStr == 2) {
            $pmCode = 2;
        }

        $shippingFee = (float)($request->input('shippingFee') ?? 30000);
        $address = $request->input('address') ?? $request->input('diaChiGiaoHang') ?? 'Địa chỉ mặc định';
        $note = $request->input('note') ?? $request->input('ghiChu') ?? '';
        $maDonHang = $request->input('mahd') ?? ('HD' . date('YmdHis') . rand(100, 999));

        $subtotal = 0;
        foreach ($cartItems as $ci) {
            $sp = $ci->sanpham;
            $price = $sp ? (float)$sp->giaBan : 0;
            $subtotal += $price * (int)$ci->soLuong;
        }

        $voucherDiscount = 0;
        $voucherId = null;
        $voucherCode = $request->input('voucher_code');
        if ($voucherCode) {
            $km = KhuyenMai::where('maVoucher', $voucherCode)->where('trangThai', 1)->first();
            if ($km) {
                $voucherId = $km->id;
                if ($km->loaiGiamGia === 'PHAN_TRAM') {
                    $voucherDiscount = ($subtotal * $km->giaTriGiam) / 100;
                    if ($km->giamToiDa > 0 && $voucherDiscount > $km->giamToiDa) {
                        $voucherDiscount = $km->giamToiDa;
                    }
                } else {
                    $voucherDiscount = $km->giaTriGiam;
                }
            }
        }

        $totalPayment = max(0, ($subtotal + $shippingFee) - $voucherDiscount);
        
        // Dynamically find nearest store with sufficient stock for ALL cart items
        $storeId = $request->input('cuaHangId') ?? $this->findBestFulfillingStore($cartItems, $address);

        DB::beginTransaction();
        try {
            $donHang = DonHang::create([
                'maDonHang' => $maDonHang,
                'nguoidungId' => $nguoidung->id,
                'cuaHangId' => $storeId,
                'voucherId' => $voucherId,
                'loaiDonHang' => 0,
                'tongTienHang' => $subtotal,
                'soTienGiam' => $voucherDiscount,
                'phiVanChuyen' => $shippingFee,
                'tongThanhToan' => $totalPayment,
                'phuongThucThanhToan' => $pmCode,
                'trangThaiThanhToan' => $pmCode == 0 ? 0 : 1,
                'trangThaiDonHang' => 1,
                'diaChiGiaoHang' => $address,
                'toWardCode' => $request->input('to_ward_code'),
                'toDistrictId' => $request->input('to_district_id') ? (int)$request->input('to_district_id') : null,
                'ghiChu' => $note,
            ]);

            foreach ($cartItems as $ci) {
                $sp = $ci->sanpham;
                $price = $sp ? (float)$sp->giaBan : 0;
                ChiTietDonHang::create([
                    'donHangId' => $donHang->id,
                    'sanPhamId' => $ci->sanPhamId,
                    'soLuong' => $ci->soLuong,
                    'donGia' => $price,
                    'thanhTien' => $price * $ci->soLuong,
                ]);

                $stockItem = TonKhoChiNhanh::where('cuaHangId', $storeId)
                    ->where('sanPhamId', $ci->sanPhamId)
                    ->first();
                if ($stockItem && $stockItem->soLuongTon >= $ci->soLuong) {
                    $stockItem->decrement('soLuongTon', $ci->soLuong);
                }
            }

            // Clear cart items
            ChiTietGioHang::where('gioHangId', $cart->id)->delete();

            // Handle payment receipt file if uploaded in checkout request
            if ($request->hasFile('receipt') || $request->hasFile('bill') || $request->hasFile('minhChung') || $request->hasFile('image')) {
                $file = $request->file('receipt') ?? $request->file('bill') ?? $request->file('minhChung') ?? $request->file('image');
                try {
                    $uploadService = new \App\Services\UploadService();
                    $receiptUrl = $uploadService->uploadImage($file, 'receipts');
                    $donHang->minhChungThanhToan = $receiptUrl;
                    $donHang->trangThaiThanhToan = 1;
                    $donHang->save();
                } catch (\Exception $uploadErr) {
                    \Illuminate\Support\Facades\Log::warning('Lỗi lưu receipt khi checkout: ' . $uploadErr->getMessage());
                }
            }

            DB::commit();

            // Dispatch Order Confirmation Email to Customer
            $userEmail = $user->email ?? ($nguoidung->taikhoan ? $nguoidung->taikhoan->email : null);
            if (!empty($userEmail)) {
                try {
                    \App\Jobs\SendOrderEmailJob::dispatch($userEmail, $donHang->maDonHang, $donHang->tongThanhToan, $nguoidung->hoTen ?? 'Khách hàng');
                } catch (\Exception $emailError) {
                    \Illuminate\Support\Facades\Log::warning('Không thể khởi chạy job gửi email đơn hàng: ' . $emailError->getMessage());
                }
            }

            return response()->json([
                'message' => 'Đặt hàng thành công!',
                'data' => $donHang->load('chitiet')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi xử lý đơn hàng: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Dynamically route order to the nearest active branch that has sufficient stock for ALL items
     */
    private function findBestFulfillingStore($cartItems, $addressString)
    {
        $stores = \App\Models\CuaHang::where('trangThai', 1)->get();
        if ($stores->isEmpty()) {
            return 1;
        }

        $eligibleStores = [];
        foreach ($stores as $store) {
            $hasAllStock = true;
            foreach ($cartItems as $ci) {
                $stockItem = TonKhoChiNhanh::where('cuaHangId', $store->id)
                    ->where('sanPhamId', $ci->sanPhamId)
                    ->first();
                if (!$stockItem || $stockItem->soLuongTon < $ci->soLuong) {
                    $hasAllStock = false;
                    break;
                }
            }
            if ($hasAllStock) {
                $eligibleStores[] = $store;
            }
        }

        $candidatePool = count($eligibleStores) > 0 ? $eligibleStores : $stores;

        $bestStore = null;
        $bestScore = -1;
        $addrLower = mb_strtolower($addressString ?? '', 'UTF-8');

        foreach ($candidatePool as $store) {
            $score = 0;
            $storeAddr = mb_strtolower(($store->diaChiChiTiet ?? '') . ' ' . ($store->tenCuaHang ?? ''), 'UTF-8');

            $parts = explode(',', $addrLower);
            foreach ($parts as $part) {
                $partTrim = trim($part);
                if (mb_strlen($partTrim, 'UTF-8') > 3 && str_contains($storeAddr, $partTrim)) {
                    $score += 10;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStore = $store;
            }
        }

        return $bestStore ? $bestStore->id : 1;
    }

    /**
     * Upload Payment Proof Receipt (Bill VietQR / Momo) for Customer Order to MinIO
     */
    public function uploadPaymentReceipt(Request $request, $id)
    {
        $donHang = DonHang::where('id', $id)->orWhere('maDonHang', $id)->first();
        if (!$donHang) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);
        }

        $receiptUrl = null;
        if ($request->hasFile('receipt') || $request->hasFile('bill') || $request->hasFile('minhChung') || $request->hasFile('image')) {
            $file = $request->file('receipt') ?? $request->file('bill') ?? $request->file('minhChung') ?? $request->file('image');
            try {
                $uploadService = new \App\Services\UploadService();
                $receiptUrl = $uploadService->uploadImage($file, 'receipts');
            } catch (\Exception $e) {
                $filename = time() . '_receipt_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file->getClientOriginalName());
                $file->move(public_path('assets/receipts'), $filename);
                $receiptUrl = '/assets/receipts/' . $filename;
            }
        } else if ($request->input('minhChungThanhToan') || $request->input('receipt_url')) {
            $receiptUrl = $request->input('minhChungThanhToan') ?? $request->input('receipt_url');
        }

        if ($receiptUrl) {
            $donHang->minhChungThanhToan = $receiptUrl;
            $donHang->trangThaiThanhToan = 1; // Mark as paid / pending verification
            $donHang->save();

            return response()->json([
                'message' => 'Tải ảnh hóa đơn / ủy nhiệm chi thành công! Hệ thống đã ghi nhận thanh toán.',
                'data' => $donHang
            ]);
        }

        return response()->json(['error' => 'Vui lòng đính kèm file hình ảnh hóa đơn chuyển khoản'], 400);
    }

    public function checkoutOnline(Request $request)
    {
        return $this->checkout($request);
    }

    // Alias methods for compatibility
    public function add(Request $request)
    {
        return $this->addToCart($request);
    }

    public function updateQuantity(Request $request, $id = null)
    {
        return $this->updateCart($request, $id);
    }

    public function remove($id)
    {
        return $this->removeFromCart($id);
    }
}
