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
                    $unitPrice = (float)($sp->giaSauGiam ?? $sp->giaBan);
                    $resolvedItems[] = [
                        'sanPhamId' => $sp->id,
                        'soLuong' => $qty,
                        'donGia' => $unitPrice,
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
                    $unitPrice = (float)($sp->giaSauGiam ?? $sp->giaBan);
                    $resolvedItems[] = [
                        'sanPhamId' => $sp->id,
                        'soLuong' => (int)$ci->soLuong,
                        'donGia' => $unitPrice,
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

        // Tự động cập nhật số điện thoại vào hồ sơ người dùng nếu hồ sơ chưa có số điện thoại
        if (!empty($receiverPhone) && (empty($nguoidung->soDienThoai) || $nguoidung->soDienThoai === '0900000000')) {
            try {
                $nguoidung->soDienThoai = $receiverPhone;
                $nguoidung->save();
            } catch (\Throwable $ePhone) {}
        }

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
            \App\Http\Controllers\Api\VoucherController::ensureVoucherSchema();
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
        $defaultStoreId = \App\Models\CuaHang::where('trangThai', 1)->value('id') ?? \App\Models\CuaHang::value('id') ?? 1;
        $storeId = $request->input('cuaHangId') ?? $this->findBestFulfillingStore($resolvedItems, $address);
        if (!$storeId || !\App\Models\CuaHang::where('id', $storeId)->exists()) {
            $storeId = $defaultStoreId;
        }

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
                'trangThaiThanhToan' => ($pmCode == 0 ? 0 : 1),
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
                    \App\Services\InventoryLedgerService::record(
                        (int)$storeId,
                        (int)$item['sanPhamId'],
                        1, // 1: Bán hàng Online
                        -(int)$item['soLuong'],
                        $donHang->maDonHang ?? ('HD' . $donHang->id),
                        $stockItem ? (int)$stockItem->fresh()->soLuongTon : null
                    );
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

            // Dispatch Order Confirmation Email to Customer synchronously
            $userEmail = $request->input('email') 
                ?? $request->input('EMAIL') 
                ?? $user->email 
                ?? ($nguoidung->taikhoan ? $nguoidung->taikhoan->email : null) 
                ?? ($nguoidung->email ?? null);
            if (!empty($userEmail)) {
                try {
                    \App\Jobs\SendOrderEmailJob::dispatchSync(
                        $userEmail, 
                        $donHang->maDonHang, 
                        $donHang->tongThanhToan, 
                        $receiverName ?? $nguoidung->hoTen ?? 'Khách hàng'
                    );
                } catch (\Throwable $emailError) {
                    \Illuminate\Support\Facades\Log::warning('Không thể gửi email xác nhận đơn hàng: ' . $emailError->getMessage());
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
     * Dynamically route order to the nearest active branch that has sufficient stock for ALL items.
     */
    private function findBestFulfillingStore($items, $addressString)
    {
        $stores = \App\Models\CuaHang::where('trangThai', 1)->get();
        if ($stores->isEmpty()) {
            return 1;
        }

        // Aggregate required quantities per product ID
        $requiredByProduct = [];
        foreach ($items as $ci) {
            $spId = (int) (is_array($ci) ? ($ci['sanPhamId'] ?? 0) : ($ci->sanPhamId ?? 0));
            $qty = (int) (is_array($ci) ? ($ci['soLuong'] ?? 1) : ($ci->soLuong ?? 1));
            if ($spId > 0 && $qty > 0) {
                $requiredByProduct[$spId] = ($requiredByProduct[$spId] ?? 0) + $qty;
            }
        }

        $productIds = array_keys($requiredByProduct);
        $stockMap = [];
        if (!empty($productIds)) {
            $stockRows = TonKhoChiNhanh::whereIn('cuaHangId', $stores->pluck('id'))
                ->whereIn('sanPhamId', $productIds)
                ->get(['cuaHangId', 'sanPhamId', 'soLuongTon']);

            foreach ($stockRows as $row) {
                $stockMap[(int)$row->cuaHangId][(int)$row->sanPhamId] = (int)$row->soLuongTon;
            }
        }

        $eligibleStores = [];
        $storeMetrics = [];

        foreach ($stores as $store) {
            $sid = (int) $store->id;
            $hasAllStock = true;
            $fulfillableQty = 0;
            $totalAvailableForOrder = 0;

            foreach ($requiredByProduct as $spId => $reqQty) {
                $avail = $stockMap[$sid][$spId] ?? 0;
                $totalAvailableForOrder += $avail;
                $fulfillableQty += min($avail, $reqQty);
                if ($avail < $reqQty) {
                    $hasAllStock = false;
                }
            }

            $storeMetrics[$sid] = [
                'hasAllStock' => $hasAllStock,
                'fulfillableQty' => $fulfillableQty,
                'totalAvailable' => $totalAvailableForOrder,
            ];

            if ($hasAllStock) {
                $eligibleStores[] = $store;
            }
        }

        // Priority 1: Only branches that have sufficient stock for ALL items in the order.
        // Fallback (if no single branch has 100% stock): branches that can fulfill the highest quantity.
        if (count($eligibleStores) > 0) {
            $candidatePool = $eligibleStores;
        } else {
            $maxFulfillable = max(array_column($storeMetrics, 'fulfillableQty'));
            $candidatePool = $stores->filter(function ($s) use ($storeMetrics, $maxFulfillable) {
                return ($storeMetrics[(int)$s->id]['fulfillableQty'] ?? 0) === $maxFulfillable;
            })->values()->all();
        }

        $normCustomerAddr = $this->normalizeVietnameseAddress((string) ($addressString ?? ''));
        $customerCoords = $this->resolveLocationCoords($normCustomerAddr);

        $bestStore = null;
        $bestDistance = PHP_FLOAT_MAX;
        $bestStockTieBreaker = -1;

        foreach ($candidatePool as $store) {
            $sid = (int) $store->id;
            $normStoreAddr = $this->normalizeVietnameseAddress(
                trim(($store->diaChiChiTiet ?? '') . ' ' . ($store->tenCuaHang ?? ''))
            );

            $distanceKm = $this->estimateStoreDistanceKm($normCustomerAddr, $customerCoords, $store, $normStoreAddr);
            $stockTie = $storeMetrics[$sid]['totalAvailable'] ?? 0;

            if (
                $distanceKm < $bestDistance - 0.001 ||
                (abs($distanceKm - $bestDistance) <= 0.001 && $stockTie > $bestStockTieBreaker)
            ) {
                $bestDistance = $distanceKm;
                $bestStockTieBreaker = $stockTie;
                $bestStore = $store;
            }
        }

        return $bestStore ? (int) $bestStore->id : (int) ($stores->first()->id ?? 1);
    }

    private function normalizeVietnameseAddress(string $text): string
    {
        $str = mb_strtolower(trim($text), 'UTF-8');
        $accents = [
            'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
            'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
            'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
            'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
            'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
            'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
            'đ'=>'d',
        ];
        $str = strtr($str, $accents);
        $str = preg_replace('/\b(tp\.?\s*hcm|tphcm|hcm|tp\.?\s*ho chi minh)\b/u', 'ho chi minh', $str);
        $str = preg_replace('/\bq\.?\s*(\d+)\b/u', 'quan $1', $str);
        $str = preg_replace('/\bp\.?\s*(\d+)\b/u', 'phuong $1', $str);
        $str = preg_replace('/\s+/u', ' ', $str);
        return trim($str);
    }

    private function resolveLocationCoords(string $normAddr): ?array
    {
        if ($normAddr === '') {
            return null;
        }

        // Ordered from most specific (streets/districts) to broader regions (provinces)
        $knownLocations = [
            // Branch exact addresses / unique streets
            'tran thanh mai'  => [10.7445, 106.5978],
            '282 nguyen trai' => [10.7540, 106.6675],
            '25 le loi'       => [10.3864, 105.4352],

            // Ho Chi Minh City districts
            'binh tan'       => [10.7652, 106.6038],
            'binh chanh'     => [10.6874, 106.5939],
            'tan phu'        => [10.7901, 106.6282],
            'tan binh'       => [10.8015, 106.6526],
            'quan 5'         => [10.7540, 106.6634],
            'quan 6'         => [10.7481, 106.6352],
            'quan 8'         => [10.7241, 106.6286],
            'quan 10'        => [10.7746, 106.6679],
            'quan 11'        => [10.7629, 106.6501],
            'quan 1'         => [10.7757, 106.7004],
            'quan 3'         => [10.7844, 106.6844],
            'quan 4'         => [10.7578, 106.7013],
            'quan 7'         => [10.7340, 106.7218],
            'quan 12'        => [10.8672, 106.6413],
            'phu nhuan'      => [10.7992, 106.6803],
            'binh thanh'     => [10.8106, 106.7091],
            'go vap'         => [10.8387, 106.6653],
            'thu duc'        => [10.8494, 106.7537],
            'nha be'         => [10.6953, 106.7049],
            'hoc mon'        => [10.8894, 106.5953],
            'cu chi'         => [11.0067, 106.5132],
            'can gio'        => [10.4112, 106.9547],

            // An Giang & Mekong Delta
            'long xuyen'     => [10.3864, 105.4352],
            'thoai son'      => [10.2603, 105.2636],
            'nui sap'        => [10.2603, 105.2636],
            'chau doc'       => [10.7000, 105.1119],
            'chau thanh'     => [10.4430, 105.3310],
            'chau phu'       => [10.5620, 105.2280],
            'cho moi'        => [10.5425, 105.4050],
            'phu tan'        => [10.6650, 105.3360],
            'tan chau'       => [10.8000, 105.2380],
            'tinh bien'      => [10.6010, 105.0080],
            'tri ton'        => [10.4220, 104.9960],
            'an phu'         => [10.8140, 105.0870],
            'an giang'       => [10.3864, 105.4352],
            'can tho'        => [10.0452, 105.7469],
            'dong thap'      => [10.4938, 105.6882],
            'kien giang'     => [10.0125, 105.0809],
            'long an'        => [10.5359, 106.4137],
            'tien giang'     => [10.4493, 106.3421],
            'vinh long'      => [10.2396, 105.9572],
            'ben tre'        => [10.2434, 106.3756],
            'tra vinh'       => [9.9347, 106.3453],
            'soc trang'      => [9.6025, 105.9739],
            'bac lieu'       => [9.2940, 105.7216],
            'ca mau'         => [9.1768, 105.1524],
            'hau giang'      => [9.7579, 105.6413],

            // Southeast & other major provinces
            'ho chi minh'    => [10.7769, 106.7009],
            'binh duong'     => [10.9804, 106.6519],
            'dong nai'       => [10.9453, 106.8243],
            'ba ria'         => [10.4114, 107.1362],
            'vung tau'       => [10.3460, 107.0843],
            'tay ninh'       => [11.3352, 106.1099],
            'binh phuoc'     => [11.7512, 106.7235],
            'da nang'        => [16.0544, 108.2022],
            'ha noi'         => [21.0285, 105.8542],
        ];

        foreach ($knownLocations as $keyword => $coords) {
            if (str_contains($normAddr, $keyword)) {
                return $coords;
            }
        }

        return null;
    }

    private function estimateStoreDistanceKm(string $normCustomerAddr, ?array $customerCoords, $store, string $normStoreAddr): float
    {
        // 1. Direct street/address match -> 0 km
        $parts = array_filter(array_map('trim', explode(',', $normCustomerAddr)));
        foreach ($parts as $part) {
            if (strlen($part) >= 6 && str_contains($normStoreAddr, $part)) {
                return 0.0;
            }
        }

        // 2. Haversine distance using coordinates (DB coords if available, otherwise resolved from address)
        $storeLat = $store->viDo ?? $store->latitude ?? null;
        $storeLng = $store->kinhDo ?? $store->longitude ?? null;
        $storeCoords = ($storeLat !== null && $storeLng !== null)
            ? [(float)$storeLat, (float)$storeLng]
            : $this->resolveLocationCoords($normStoreAddr);

        if ($customerCoords && $storeCoords) {
            return $this->haversineKm(
                $customerCoords[0],
                $customerCoords[1],
                $storeCoords[0],
                $storeCoords[1]
            );
        }

        // 3. Fallback token overlap score (lower distance = better match)
        $overlap = 0;
        foreach ($parts as $part) {
            if (strlen($part) >= 3 && str_contains($normStoreAddr, $part)) {
                $overlap++;
            }
        }

        return $overlap > 0 ? max(1.0, 100.0 - ($overlap * 25.0)) : 9999.0;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
