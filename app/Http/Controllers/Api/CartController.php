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

    private function resolveProductId($rawSpId)
    {
        if (empty($rawSpId)) {
            return null;
        }

        if (is_numeric($rawSpId)) {
            $id = (int) $rawSpId;
            if (SanPham::where('id', $id)->exists()) {
                return $id;
            }
        }

        if (is_string($rawSpId)) {
            $cleanId = (int) preg_replace('/\D/', '', $rawSpId);
            if ($cleanId > 0 && SanPham::where('id', $cleanId)->exists()) {
                return $cleanId;
            }

            $sp = SanPham::where('id', $rawSpId)->orWhere('slug', $rawSpId)->first();
            if ($sp) {
                return $sp->id;
            }
        }

        return null;
    }

    public function addToCart(Request $request)
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        $rawSpId = $request->input('sanPhamId') 
            ?? $request->input('san_pham_id') 
            ?? $request->input('masp') 
            ?? $request->input('maSP') 
            ?? $request->input('MASP') 
            ?? $request->input('product_id') 
            ?? $request->input('productId') 
            ?? $request->input('id');

        $sanPhamId = $this->resolveProductId($rawSpId);

        $request->merge([
            'sanPhamId' => $sanPhamId,
            'soLuong' => $request->input('soLuong') ?? $request->input('quantity') ?? $request->input('soluong') ?? 1,
        ]);

        $request->validate([
            'sanPhamId' => 'required|exists:sanPham,id',
            'soLuong' => 'required|integer|min:1',
        ], [
            'sanPhamId.required' => 'Mã sản phẩm không được để trống.',
            'sanPhamId.exists' => 'Sản phẩm này không tồn tại trong hệ thống.',
            'soLuong.required' => 'Số lượng sản phẩm không hợp lệ.',
            'soLuong.min' => 'Số lượng sản phẩm tối thiểu là 1.',
        ]);

        $item = ChiTietGioHang::where('gioHangId', $cart->id)
            ->where('sanPhamId', $request->sanPhamId)
            ->first();

        $product = SanPham::find($request->sanPhamId);
        $stock = $product ? (int) ($product->soLuongTon ?? 0) : 0;
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

    public function updateCart(Request $request, $id = null)
    {
        $cart = $this->getCart();
        if (!$cart) {
            return response()->json(['error' => 'Chưa xác thực người dùng'], 401);
        }

        $rawSpId = $id 
            ?? $request->input('id') 
            ?? $request->input('sanPhamId') 
            ?? $request->input('san_pham_id') 
            ?? $request->input('masp') 
            ?? $request->input('maSP') 
            ?? $request->input('MASP') 
            ?? $request->input('product_id');

        $resolvedId = $this->resolveProductId($rawSpId) ?? $rawSpId;

        $request->merge([
            'soLuong' => $request->input('soLuong') ?? $request->input('quantity') ?? $request->input('soluong') ?? 1,
        ]);

        $request->validate([
            'soLuong' => 'required|integer|min:1',
        ]);

        $item = ChiTietGioHang::where('gioHangId', $cart->id)
            ->where(function($q) use ($rawSpId, $resolvedId) {
                $q->where('id', $rawSpId)
                  ->orWhere('sanPhamId', $rawSpId)
                  ->orWhere('sanPhamId', $resolvedId);
            })->first();

        if (!$item) {
            return response()->json(['error' => 'Không tìm thấy món trong giỏ'], 404);
        }

        $product = SanPham::find($item->sanPhamId);
        $stock = $product ? (int) ($product->soLuongTon ?? 0) : 0;
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

        $resolvedId = $this->resolveProductId($id) ?? $id;

        ChiTietGioHang::where('gioHangId', $cart->id)
            ->where(function($q) use ($id, $resolvedId) {
                $q->where('id', $id)
                  ->orWhere('sanPhamId', $id)
                  ->orWhere('sanPhamId', $resolvedId);
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

        // 1. Resolve Cart items (Either direct payload from Mobile/POS or DB Cart)
        $cart = GioHang::firstOrCreate(['nguoidungId' => $nguoidung->id]);
        $cartItems = ChiTietGioHang::with('sanpham')->where('gioHangId', $cart->id)->get();

        $resolvedItems = [];
        // Ưu tiên danh sách items truyền trực tiếp từ client (Mobile App hoặc POS)
        $rawPayloadItems = $request->input('CHI_TIET') ?? $request->input('items') ?? $request->input('cartItems') ?? [];
        if (is_string($rawPayloadItems)) {
            $rawPayloadItems = json_decode($rawPayloadItems, true) ?? [];
        }

        if (!empty($rawPayloadItems) && is_array($rawPayloadItems)) {
            foreach ($rawPayloadItems as $it) {
                $rawId = $it['sanPhamId'] ?? $it['MASP'] ?? $it['id'] ?? $it['masp'] ?? null;
                $spId = $this->resolveProductId($rawId);
                $sp = $spId ? SanPham::find($spId) : null;
                if ($sp) {
                    $qty = max(1, (int)($it['SO_LUONG'] ?? $it['soLuong'] ?? $it['quantity'] ?? 1));
                    $resolvedItems[] = [
                        'sanPhamId' => $sp->id,
                        'soLuong' => $qty,
                        'donGia' => (float)$sp->giaBan,
                        'sp' => $sp
                    ];
                }
            }
        }

        // Nếu payload rỗng thì lấy từ giỏ hàng trong DB
        if (empty($resolvedItems) && $cartItems->isNotEmpty()) {
            foreach ($cartItems as $ci) {
                $sp = $ci->sanpham;
                if ($sp) {
                    $resolvedItems[] = [
                        'sanPhamId' => $sp->id,
                        'soLuong' => (int)$ci->soLuong,
                        'donGia' => (float)$sp->giaBan,
                        'sp' => $sp
                    ];
                }
            }
        }

        if (empty($resolvedItems)) {
            return response()->json(['error' => 'Giỏ hàng của bạn đang trống!'], 400);
        }

        // 2. Resolve Payment Method
        $paymentMethodStr = strtolower(trim((string)($request->input('paymentMethod') ?? $request->input('phuongThucThanhToan') ?? $request->input('HINHTHUC_THANHTOAN') ?? 'cod')));
        $pmCode = 0; // 0: COD, 1: VIETQR/CK, 2: MOMO
        if (in_array($paymentMethodStr, ['vietqr', 'bank', 'ck', 'chuyen_khoan', '1'])) {
            $pmCode = 1;
        } else if (in_array($paymentMethodStr, ['momo', 'vnpay', '2'])) {
            $pmCode = 2;
        }

        $shippingFee = (float)($request->input('shippingFee') ?? $request->input('phiVanChuyen') ?? 30000);
        $address = $request->input('address') ?? $request->input('diaChiGiaoHang') ?? $request->input('DIACHI') ?? 'Địa chỉ mặc định';
        $note = $request->input('note') ?? $request->input('ghiChu') ?? $request->input('GHICHU') ?? '';
        $maDonHang = $request->input('mahd') ?? $request->input('MAHD') ?? ('HD' . date('YmdHis') . rand(100, 999));
        while (DonHang::where('maDonHang', $maDonHang)->exists()) {
            $maDonHang = 'HD' . date('YmdHis') . rand(1000, 9999);
        }

        $receiverName = $request->input('HOTEN') ?? $request->input('receiverName') ?? $request->input('hoTenNguoiNhan') ?? $nguoidung->hoTen;
        $receiverPhone = $request->input('SDT') ?? $request->input('receiverPhone') ?? $request->input('soDienThoai') ?? $nguoidung->soDienThoai;

        // Auto-save or update default customer address in DiaChi table
        if ($address && $address !== 'Địa chỉ mặc định') {
            $existingAddrCount = \App\Models\DiaChi::where('nguoidungId', $nguoidung->id)->count();
            if ($existingAddrCount === 0 || $request->boolean('saveAddress') || $request->boolean('isDefault')) {
                try {
                    $shouldBeDefault = $existingAddrCount === 0 || $request->boolean('isDefault');
                    if ($shouldBeDefault) {
                        \App\Models\DiaChi::where('nguoidungId', $nguoidung->id)->update(['isDefault' => 0]);
                    }

                    \App\Models\DiaChi::updateOrCreate(
                        [
                            'nguoidungId' => $nguoidung->id,
                            'diaChiChiTiet' => trim($address)
                        ],
                        [
                            'hoTenNguoiNhan' => $receiverName,
                            'soDienThoai' => $receiverPhone,
                            'isDefault' => $shouldBeDefault ? 1 : 0,
                            'maTinh' => (int)($request->input('to_province_id') ?? $request->input('maTinh') ?? 0),
                            'maHuyen' => (int)($request->input('to_district_id') ?? $request->input('maHuyen') ?? 0),
                            'maXa' => (string)($request->input('to_ward_code') ?? $request->input('maXa') ?? ''),
                        ]
                    );
                } catch (\Exception $eAddr) {
                    \Illuminate\Support\Facades\Log::warning('Không thể tự động lưu địa chỉ: ' . $eAddr->getMessage());
                }
            }
        }

        $subtotal = 0;
        foreach ($resolvedItems as $item) {
            $subtotal += $item['donGia'] * $item['soLuong'];
        }

        $voucherDiscount = 0;
        $voucherCode = strtoupper(trim($request->input('voucher_code') ?? $request->input('maVoucher') ?? $request->input('MA_VOUCHER') ?? ''));
        if ($voucherCode) {
            $voucherObj = \App\Models\Voucher::where('MA_VOUCHER', $voucherCode)->where('TRANG_THAI', 1)->first();
            if ($voucherObj) {
                if ($voucherObj->LOAI_GIAM === 'PHAN_TRAM') {
                    $voucherDiscount = ($subtotal * (float)$voucherObj->GIA_TRI) / 100;
                    if ($voucherObj->GIAM_TOI_DA && (float)$voucherObj->GIAM_TOI_DA > 0) {
                        $voucherDiscount = min($voucherDiscount, (float)$voucherObj->GIAM_TOI_DA);
                    }
                } else {
                    $voucherDiscount = min((float)$voucherObj->GIA_TRI, $subtotal);
                }
                $voucherDiscount = round($voucherDiscount);
                $voucherObj->increment('SO_LUONG_DA_DUNG');
            }
        }

        $totalPayment = max(0, ($subtotal + $shippingFee) - $voucherDiscount);
        
        // Dynamically find nearest store with sufficient stock for ALL cart items
        $storeId = $request->input('cuaHangId') ?? $this->findBestFulfillingStore($resolvedItems, $address);
        if (!$storeId) $storeId = 1;

        // Xử lý upload ảnh minh chứng thanh toán (nếu có từ Mobile/Website)
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
        } else if ($request->input('minhChungThanhToan') || $request->input('receipt_url') || $request->input('receiptUri')) {
            $receiptUrl = $request->input('minhChungThanhToan') ?? $request->input('receipt_url') ?? $request->input('receiptUri');
        }

        DB::beginTransaction();
        try {
            $orderData = [
                'maDonHang' => $maDonHang,
                'nguoidungId' => $nguoidung->id,
                'cuaHangId' => $storeId,
                'voucherId' => null,
                'maVoucher' => $voucherCode ?: null,
                'loaiDonHang' => 0, // 0: Online
                'tongTienHang' => $subtotal,
                'soTienGiam' => $voucherDiscount,
                'phiVanChuyen' => $shippingFee,
                'tongThanhToan' => $totalPayment,
                'phuongThucThanhToan' => $pmCode,
                'trangThaiThanhToan' => ($pmCode == 0 ? 0 : ($receiptUrl ? 1 : ($pmCode == 1 ? 0 : 1))),
                'trangThaiDonHang' => 1, // 1: Chờ xác nhận
                'diaChiGiaoHang' => $address,
                'ghiChu' => $note,
            ];

            if ($receiptUrl) {
                $orderData['minhChungThanhToan'] = $receiptUrl;
            }

            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('donHang', 'toWardCode')) {
                    $orderData['toWardCode'] = $request->input('to_ward_code');
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('donHang', 'toDistrictId')) {
                    $orderData['toDistrictId'] = $request->input('to_district_id') ? (int)$request->input('to_district_id') : null;
                }
            } catch (\Throwable $eCol) {
                // Ignore schema check error
            }

            $donHang = DonHang::create($orderData);

            if ($voucherCode) {
                try {
                    DB::table('voucher_khachhang')
                        ->where('nguoidungId', $nguoidung->id)
                        ->where('maVoucher', $voucherCode)
                        ->update([
                            'trangThai' => 1,
                            'ngaySuDung' => now(),
                            'maDonHang' => $maDonHang
                        ]);
                } catch (\Throwable $eVo) {
                    \Illuminate\Support\Facades\Log::warning('Lỗi cập nhật voucher_khachhang: ' . $eVo->getMessage());
                }
            }

            foreach ($resolvedItems as $item) {
                ChiTietDonHang::create([
                    'donHangId' => $donHang->id,
                    'sanPhamId' => $item['sanPhamId'],
                    'soLuong' => $item['soLuong'],
                    'donGia' => $item['donGia'],
                    'thanhTien' => $item['donGia'] * $item['soLuong'],
                ]);

                try {
                    $stockItem = TonKhoChiNhanh::where('cuaHangId', $storeId)
                        ->where('sanPhamId', $item['sanPhamId'])
                        ->first();
                    if ($stockItem && $stockItem->soLuongTon >= $item['soLuong']) {
                        $stockItem->decrement('soLuongTon', $item['soLuong']);
                    }
                } catch (\Throwable $eStock) {
                    \Illuminate\Support\Facades\Log::warning('Lỗi trừ kho: ' . $eStock->getMessage());
                }
            }

            // Clear DB cart items if any
            if ($cart) {
                try {
                    ChiTietGioHang::where('gioHangId', $cart->id)->delete();
                } catch (\Throwable $eCartDel) {}
            }

            // Ghi nhận lịch sử trạng thái ban đầu của đơn hàng
            try {
                \App\Models\LichSuTrangThaiDonHang::create([
                    'donHangId' => $donHang->id,
                    'trangThaiCu' => 0,
                    'trangThaiMoi' => 1,
                    'ghnStatusCode' => $pmCode == 0 ? 'created_cod' : ($pmCode == 1 ? 'paid_vietqr' : 'paid_momo'),
                    'moTaChiTiet' => $pmCode == 0 
                        ? 'Khách đặt đơn hàng Online (COD - Chưa thanh toán)' 
                        : ($pmCode == 1 ? 'Khách đặt đơn hàng chuyển khoản VietQR' : 'Khách đặt đơn hàng thanh toán Ví MoMo'),
                    'nguonCapNhat' => 2, // Khách hàng / Hệ thống
                ]);
            } catch (\Throwable $eHist) {
                \Illuminate\Support\Facades\Log::warning('Lỗi ghi lịch sử đơn hàng: ' . $eHist->getMessage());
            }

            DB::commit();

            // Dispatch Order Confirmation Email to Customer
            $userEmail = $user->email ?? ($nguoidung->taikhoan ? $nguoidung->taikhoan->email : null);
            if (!empty($userEmail)) {
                try {
                    \App\Jobs\SendOrderEmailJob::dispatch($userEmail, $donHang->maDonHang, $donHang->tongThanhToan, $nguoidung->hoTen ?? 'Khách hàng');
                } catch (\Throwable $emailError) {
                    \Illuminate\Support\Facades\Log::warning('Không thể khởi chạy job gửi email đơn hàng: ' . $emailError->getMessage());
                }
            }

            return response()->json([
                'message' => 'Đặt hàng thành công!',
                'data' => $donHang->load('chitiet')
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Lỗi checkout: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => 'Lỗi xử lý đơn hàng: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Dynamically route order to the nearest active branch that has sufficient stock for ALL items
     */
    private function findBestFulfillingStore($items, $addressString)
    {
        $stores = \App\Models\CuaHang::where('trangThai', 1)->get();
        if ($stores->isEmpty()) {
            return 1;
        }

        $eligibleStores = [];
        foreach ($stores as $store) {
            $hasAllStock = true;
            foreach ($items as $ci) {
                $spId = is_array($ci) ? ($ci['sanPhamId'] ?? 0) : ($ci->sanPhamId ?? 0);
                $qty = is_array($ci) ? ($ci['soLuong'] ?? 1) : ($ci->soLuong ?? 1);
                if (!$spId) continue;

                $stockItem = TonKhoChiNhanh::where('cuaHangId', $store->id)
                    ->where('sanPhamId', $spId)
                    ->first();
                if (!$stockItem || $stockItem->soLuongTon < $qty) {
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

        return $bestStore ? $bestStore->id : ($stores->first()->id ?? 1);
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
