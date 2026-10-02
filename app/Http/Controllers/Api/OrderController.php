<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;
use App\Models\LichSuTrangThaiDonHang;
use App\Models\SanPham;
use App\Models\KhuyenMai;
use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;

class OrderController extends Controller
{
    private static array $statusMap = [
        0 => 'Đã hủy',
        1 => 'Chờ xác nhận',
        2 => 'Đang chuẩn bị',
        3 => 'Đang giao',
        4 => 'Đã hoàn thành',
        5 => 'Đã hoàn thành',
        6 => 'Đã hủy',
        7 => 'Chờ lấy hàng',
    ];

    private static array $stringToStatusMap = [
        'Đã hủy' => 0,
        'Chờ xác nhận' => 1,
        'Chờ xử lý' => 1,
        'Đang chuẩn bị' => 2,
        'Chờ lấy hàng' => 7,
        'Đang giao' => 3,
        'Đã hoàn thành' => 4,
        'DA_HUY' => 0,
        'CHO_XAC_NHAN' => 1,
        'DANG_CHE_BIEN' => 2,
        'CHO_LAY_HANG' => 7,
        'DANG_GIAO' => 3,
        'HOAN_THANH' => 4,
    ];

    /**
     * Customer & Admin: View order list / Sales invoices
     */
    public function index(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $query = DonHang::with(['chitiet.sanpham', 'cuahang', 'nguoidung', 'voucher', 'timelineGHN']);

        // Customer views only own orders
        if ($user->vaiTroId == 6) { // CUSTOMER
            $nguoidung = $user->nguoidung;
            $query->where('nguoidungId', $nguoidung ? $nguoidung->id : 0);
        }

        // Branch Manager views only own branch orders
        if ($user->vaiTroId == 2) {
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
            if ($staffProfile && $staffProfile->cuaHangId) {
                $query->where('cuaHangId', $staffProfile->cuaHangId);
            }
        }

        // Filter by numeric status
        if ($request->filled('trangThaiDonHang')) {
            $stVal = (int) $request->input('trangThaiDonHang');
            if ($stVal === 0) {
                $query->whereIn('trangThaiDonHang', [0, 6]);
            } else {
                $query->where('trangThaiDonHang', $stVal);
            }
        }

        // Filter by text status (e.g. 'Chờ xử lý', 'Đang giao', 'Đã hoàn thành', 'Đã hủy')
        $statusText = $request->input('trangThai');
        if (!empty($statusText)) {
            if (isset(self::$stringToStatusMap[$statusText])) {
                $targetVal = self::$stringToStatusMap[$statusText];
                if ($targetVal == 4) {
                    $query->whereIn('trangThaiDonHang', [4, 5]);
                } elseif ($targetVal == 0) {
                    $query->whereIn('trangThaiDonHang', [0, 6]);
                } else {
                    $query->where('trangThaiDonHang', $targetVal);
                }
            }
        }

        // Search query (ma_don_hang, customer name, phone, or customer code)
        $search = $request->input('search') ?? $request->input('searchQuery') ?? $request->input('keyword');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('maDonHang', 'like', "%{$search}%")
                  ->orWhereHas('nguoidung', function ($q2) use ($search) {
                      $q2->where('hoTen', 'like', "%{$search}%")
                         ->orWhere('soDienThoai', 'like', "%{$search}%")
                         ->orWhere('id', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) ($request->input('size') ?? $request->input('per_page') ?? 100);
        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        $mappedContent = collect($paginator->items())->map(function ($order) {
            $khCode = $order->nguoidung 
                ? ('KH' . str_pad($order->nguoidung->id, 7, '0', STR_PAD_LEFT)) 
                : 'Khách lẻ';

            $khName = $order->nguoidung ? $order->nguoidung->hoTen : 'Khách Vãng Lai';
            $khSdt = $order->nguoidung ? $order->nguoidung->soDienThoai : '';
            $khEmail = ($order->nguoidung && $order->nguoidung->taikhoan) ? $order->nguoidung->taikhoan->email : '';

            $stTitle = self::$statusMap[$order->trangThaiDonHang] ?? 'Chờ xử lý';

            return [
                'id' => $order->id,
                'maHD' => $order->maDonHang,
                'MAHD' => $order->maDonHang,
                'MAHDBAN' => $order->maDonHang,
                'tenCuaHang' => $order->cuahang ? ($order->cuahang->tenCuaHang ?? $order->cuahang->ten_cuahang ?? 'DolaBakery - Chi nhánh') : 'DolaBakery - Cửa hàng chính',
                'cuaHangId' => $order->cuaHangId,
                'maKH' => $khCode,
                'tenKH' => $khName,
                'HOTEN_KH' => $khName,
                'TENKH' => $khName,
                'sdtKH' => $khSdt,
                'SDT' => $khSdt,
                'EMAIL' => $khEmail,
                'ngayLap' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
                'CREATED_AT' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
                'NGAYTAO' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
                'tongTien' => (float) $order->tongThanhToan,
                'TONGTIEN' => (float) $order->tongThanhToan,
                'TONG_TIEN' => (float) $order->tongThanhToan,
                'phiVanChuyen' => (float) $order->phiVanChuyen,
                'donViVanChuyen' => $order->loaiDonHang == 1 ? 'Mua tại cửa hàng (POS)' : ($order->don_vi_van_chuyen ?? 'GHN Express'),
                'maVanDon' => $order->maVanDonGhn ?? '',
                'maVanDonGhn' => $order->maVanDonGhn ?? '',
                'MAVANDON' => $order->maVanDonGhn ?? '',
                'ghiChu' => $order->ghiChu ?? '',
                'diaChiGiao' => $order->diaChiGiaoHang ?? '',
                'DIACHI_GIAO' => $order->diaChiGiaoHang ?? '',
                'minhChungThanhToan' => $order->minhChungThanhToan ?? null,
                'trangThai' => $stTitle,
                'TRANGTHAI' => $stTitle,
                'TRANG_THAI' => $stTitle,
                'trangThaiDonHang' => $order->trangThaiDonHang,
                'trangThaiThanhToan' => $order->trangThaiThanhToan,
                'phuongThucTT' => $order->phuongThucThanhToan == 1 ? 'Chuyển khoản / VietQR' : ($order->phuongThucThanhToan == 2 ? 'MoMo' : 'COD (Tiền mặt)'),
                'PHUONGTHUCTHANHTOAN' => $order->phuongThucThanhToan == 1 ? 'Chuyển khoản / VietQR' : ($order->phuongThucThanhToan == 2 ? 'MoMo' : 'COD (Tiền mặt)'),
                'loaiDonHang' => $order->loaiDonHang,
                'khach_hang' => [
                    'HOTEN' => $khName,
                    'SDT' => $khSdt,
                    'EMAIL' => $khEmail,
                    'user' => [
                        'EMAIL' => $khEmail
                    ]
                ]
            ];
        });

        return response()->json([
            'content' => $mappedContent,
            'data' => $mappedContent,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * Get single invoice details
     */
    public function show($id)
    {
        $order = DonHang::with(['chitiet.sanpham', 'cuahang', 'nguoidung', 'voucher'])
            ->where('maDonHang', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy hóa đơn'], 404);
        }

        $khCode = $order->nguoidung 
            ? ('KH' . str_pad($order->nguoidung->id, 7, '0', STR_PAD_LEFT)) 
            : 'Khách lẻ';

        $khName = $order->nguoidung ? $order->nguoidung->hoTen : 'Khách Vãng Lai';
        $khSdt = $order->nguoidung ? $order->nguoidung->soDienThoai : '';
        $khEmail = ($order->nguoidung && $order->nguoidung->taikhoan) ? $order->nguoidung->taikhoan->email : '';
        $stTitle = self::$statusMap[$order->trangThaiDonHang] ?? 'Chờ xử lý';

        return response()->json([
            'id' => $order->id,
            'maHD' => $order->maDonHang,
            'MAHD' => $order->maDonHang,
            'tenCuaHang' => $order->cuahang ? $order->cuahang->ten_cuahang : 'Cửa hàng chính',
            'maKH' => $khCode,
            'tenKH' => $khName,
            'HOTEN_KH' => $khName,
            'TENKH' => $khName,
            'sdtKH' => $khSdt,
            'SDT' => $khSdt,
            'EMAIL' => $khEmail,
            'ngayLap' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
            'CREATED_AT' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
            'tongTien' => (float) $order->tongThanhToan,
            'TONGTIEN' => (float) $order->tongThanhToan,
            'phiVanChuyen' => (float) $order->phiVanChuyen,
            'donViVanChuyen' => $order->loaiDonHang == 1 ? 'Mua tại cửa hàng (POS)' : ($order->don_vi_van_chuyen ?? 'GHN Express'),
            'maVanDon' => $order->maVanDonGhn ?? '',
            'maVanDonGhn' => $order->maVanDonGhn ?? '',
            'MAVANDON' => $order->maVanDonGhn ?? '',
            'ghiChu' => $order->ghiChu ?? '',
            'diaChiGiao' => $order->diaChiGiaoHang ?? '',
            'DIACHI_GIAO' => $order->diaChiGiaoHang ?? '',
            'minhChungThanhToan' => $order->minhChungThanhToan ?? null,
            'trangThai' => $stTitle,
            'TRANGTHAI' => $stTitle,
            'TRANG_THAI' => $stTitle,
            'trangThaiDonHang' => $order->trangThaiDonHang,
            'trangThaiThanhToan' => $order->trangThaiThanhToan,
            'phuongThucTT' => $order->phuongThucThanhToan == 1 ? 'Chuyển khoản / VietQR' : ($order->phuongThucThanhToan == 2 ? 'MoMo' : 'COD (Tiền mặt)'),
            'PHUONGTHUCTHANHTOAN' => $order->phuongThucThanhToan == 1 ? 'Chuyển khoản / VietQR' : ($order->phuongThucThanhToan == 2 ? 'MoMo' : 'COD (Tiền mặt)'),
            'loaiDonHang' => $order->loaiDonHang,
            'khach_hang' => [
                'HOTEN' => $khName,
                'SDT' => $khSdt,
                'EMAIL' => $khEmail,
                'user' => [
                    'EMAIL' => $khEmail
                ]
            ]
        ]);
    }

    /**
     * Get invoice line items
     */
    public function getDetails($id)
    {
        $order = DonHang::with(['chitiet.sanpham'])
            ->where('maDonHang', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$order) {
            return response()->json([], 200);
        }

        $items = $order->chitiet->map(function ($item) {
            $sp = $item->sanpham;
            $name = $sp ? $sp->tenSanPham : ('Sản phẩm #' . $item->sanPhamId);
            $masp = $sp ? ($sp->ma_san_pham ?? ('SP' . str_pad($sp->id, 5, '0', STR_PAD_LEFT))) : ('SP' . $item->sanPhamId);
            $sl = (int) $item->soLuong;
            $gia = (float) $item->donGia;
            $tt = (float) $item->thanhTien;

            return [
                'maSP' => $masp,
                'MASP' => $masp,
                'tenSP' => $name,
                'TENSP' => $name,
                'soLuong' => $sl,
                'SOLUONG' => $sl,
                'SO_LUONG' => $sl,
                'donGia' => $gia,
                'DONGIA' => $gia,
                'DON_GIA' => $gia,
                'thanhTien' => $tt,
                'THANHTIEN' => $tt,
                'san_pham' => [
                    'TENSP' => $name,
                    'tenSanPham' => $name,
                    'MASP' => $masp
                ]
            ];
        });

        return response()->json($items);
    }

    /**
     * Update invoice status
     */
    public function updateStatus(Request $request, $id)
    {
        $order = DonHang::where('maDonHang', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy hóa đơn'], 404);
        }

        $statusText = $request->input('TRANGTHAI') ?? $request->input('TRANG_THAI') ?? $request->input('trangThai') ?? $request->input('trangThaiDonHang');

        $statusMap = [
            'CHO_XAC_NHAN' => 1,
            'DANG_CHE_BIEN' => 2,
            'CHO_LAY_HANG' => 7,
            'DANG_GIAO' => 3,
            'HOAN_THANH' => 4,
            'DA_HUY' => 0,
            'Chờ xác nhận' => 1,
            'Chờ xử lý' => 1,
            'Đang chuẩn bị' => 2,
            'Chờ lấy hàng' => 7,
            'Đang giao' => 3,
            'Đã hoàn thành' => 4,
            'Đã hủy' => 0,
        ];

        if (isset($statusMap[$statusText])) {
            $order->trangThaiDonHang = $statusMap[$statusText];
        } elseif (isset(self::$stringToStatusMap[$statusText])) {
            $order->trangThaiDonHang = self::$stringToStatusMap[$statusText];
        } elseif (is_numeric($statusText)) {
            $order->trangThaiDonHang = (int) $statusText;
        }

        if (in_array($order->trangThaiDonHang, [4, 5])) {
            $order->trangThaiThanhToan = 1;
        }

        // Tự động khởi tạo đơn GHN Express khi chuẩn bị xong bánh (chuyển sang 'Chờ lấy hàng' 7 hoặc 'Đang giao' 3)
        $ghnMsg = '';
        if (in_array($order->trangThaiDonHang, [7, 3]) && empty($order->maVanDonGhn)) {
            // Ưu tiên ward/district từ request, nếu không có thì dùng giá trị đã lưu khi checkout
            $toWardCode = $request->input('to_ward_code') ?? $request->input('ward_code') ?? $order->toWardCode;
            $toDistrictId = $request->input('to_district_id') ?? $request->input('district_id') ?? $order->toDistrictId;

            // Gọi đồng bộ tạo đơn GHN (không dispatch Job để tránh tạo đơn trùng)
            $ghnRes = GhnController::createShippingOrderForDonHang($order, $toWardCode, $toDistrictId);
            if ($ghnRes['success']) {
                $ghnMsg = " " . $ghnRes['message'];
            } else {
                $ghnMsg = " (Lưu ý: " . ($ghnRes['message'] ?? 'Không thể tạo đơn GHN') . ")";
            }
        }

        $order->save();

        // Refresh để trả về dữ liệu mới nhất (bao gồm maVanDonGhn vừa được GHN tạo)
        $order->refresh();

        return response()->json(['message' => 'Cập nhật trạng thái thành công!' . $ghnMsg, 'data' => $order]);
    }

    /**
     * Update order line items and total amounts (Staff/Admin order editing)
     */
    public function updateOrderItems(Request $request, $id)
    {
        $order = DonHang::where('maDonHang', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy hóa đơn'], 404);
        }

        $items = $request->input('items', []);
        if (!is_array($items) || count($items) === 0) {
            return response()->json(['error' => 'Danh sách sản phẩm chỉnh sửa không hợp lệ'], 400);
        }

        DB::beginTransaction();
        try {
            // First validate product existence and inventory for all items
            foreach ($items as $item) {
                $rawSpId = $item['sanPhamId'] ?? $item['sanpham_id'] ?? $item['id'] ?? $item['MASP'] ?? $item['maSP'] ?? null;
                if (!$rawSpId) {
                    continue;
                }

                $cleanId = (int) preg_replace('/\D/', '', (string) $rawSpId);
                $sp = SanPham::where('id', $rawSpId)
                    ->orWhere('id', $cleanId)
                    ->first();

                if (!$sp) {
                    DB::rollBack();
                    return response()->json(['error' => "Sản phẩm với mã ID '{$rawSpId}' không tồn tại trong hệ thống."], 400);
                }

                $requestedQty = max(1, (int)($item['soLuong'] ?? $item['SOLUONG'] ?? $item['SO_LUONG'] ?? 1));

                // Check branch stock if order is linked to a store, or total stock
                $cuaHangId = $order->cuaHangId;
                if ($cuaHangId) {
                    $tonKhoRec = TonKhoChiNhanh::where('cuaHangId', $cuaHangId)
                        ->where('sanPhamId', $sp->id)
                        ->first();
                    $availableStock = $tonKhoRec ? (int)$tonKhoRec->soLuongTon : 0;
                } else {
                    $availableStock = (int) $sp->soLuongTon;
                }

                if ($requestedQty > $availableStock) {
                    DB::rollBack();
                    return response()->json([
                        'error' => "Sản phẩm '{$sp->tenSanPham}' không đủ số lượng tồn kho! (Số lượng còn lại trong kho: {$availableStock}, Yêu cầu trong đơn: {$requestedQty})"
                    ], 400);
                }
            }

            // Delete existing line items
            ChiTietDonHang::where('donHangId', $order->id)->delete();

            $subtotal = 0;
            foreach ($items as $item) {
                $rawSpId = $item['sanPhamId'] ?? $item['sanpham_id'] ?? $item['id'] ?? $item['MASP'] ?? $item['maSP'] ?? null;
                $cleanId = (int) preg_replace('/\D/', '', (string) $rawSpId);
                $sp = SanPham::where('id', $rawSpId)
                    ->orWhere('id', $cleanId)
                    ->first();

                if (!$sp) {
                    continue;
                }

                $qty = max(1, (int)($item['soLuong'] ?? $item['SOLUONG'] ?? $item['SO_LUONG'] ?? 1));
                $unitPrice = (float)($item['donGia'] ?? $item['DONGIA'] ?? $sp->giaBan ?? 0);
                $lineTotal = $qty * $unitPrice;

                ChiTietDonHang::create([
                    'donHangId' => $order->id,
                    'sanPhamId' => $sp->id,
                    'soLuong' => $qty,
                    'donGia' => $unitPrice,
                    'thanhTien' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $shippingFee = (float)($request->input('phiVanChuyen') ?? $order->phiVanChuyen ?? 0);
            $discount = (float)($request->input('soTienGiam') ?? $order->soTienGiam ?? 0);
            $totalPayable = max(0, ($subtotal + $shippingFee) - $discount);

            $order->tongTienHang = $subtotal;
            $order->phiVanChuyen = $shippingFee;
            $order->soTienGiam = $discount;
            $order->tongThanhToan = $totalPayable;
            $order->save();

            DB::commit();

            return response()->json([
                'message' => 'Cập nhật nội dung đơn hàng thành công!',
                'data' => $order->fresh()->load('chitiet.sanpham')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi khi cập nhật đơn hàng: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Customer: Get list of my own orders
     */
    public function myOrders(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nguoidung = $user->nguoidung;
        $nguoidungId = $nguoidung ? $nguoidung->id : 0;

        $orders = DonHang::with(['chitiet.sanpham', 'cuahang'])
            ->where('nguoidungId', $nguoidungId)
            ->orderBy('id', 'desc')
            ->get();

        $content = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'maHD' => $order->maDonHang,
                'tenCuaHang' => $order->cuahang ? $order->cuahang->tenCuaHang : 'Dola Bakery',
                'ngayLap' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
                'tongTien' => (float) $order->tongThanhToan,
                'phiVanChuyen' => (float) $order->phiVanChuyen,
                'donViVanChuyen' => $order->loaiDonHang == 1 ? 'Mua tại cửa hàng (POS)' : 'GHN Express',
                'maVanDon' => $order->maVanDonGhn ?? '',
                'maVanDonGhn' => $order->maVanDonGhn ?? '',
                'MAVANDON' => $order->maVanDonGhn ?? '',
                'ghiChu' => $order->ghiChu ?? '',
                'trangThai' => self::$statusMap[$order->trangThaiDonHang] ?? 'Chờ xử lý',
                'trangThaiDonHang' => $order->trangThaiDonHang,
                'trangThaiThanhToan' => $order->trangThaiThanhToan,
                'phuongThucTT' => $order->phuongThucThanhToan == 1 ? 'Chuyển khoản VietQR' : ($order->phuongThucThanhToan == 2 ? 'MoMo' : 'Tiền mặt khi nhận hàng (COD)'),
                'loaiDonHang' => $order->loaiDonHang == 1 ? 'Bán tại quầy' : 'Trực tuyến',
                'minhChungThanhToan' => $order->minhChungThanhToan,
            ];
        });

        return response()->json([
            'content' => $content,
            'data' => $content
        ]);
    }

    /**
     * Customer: Get my order details
     */
    public function getMyOrderDetails($id)
    {
        return $this->getDetails($id);
    }

    /**
     * Customer: Cancel order
     */
    public function customerCancelOrder($id)
    {
        $order = DonHang::where('maDonHang', $id)->orWhere('id', $id)->first();
        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);
        }

        if (in_array($order->trangThaiDonHang, [3, 4, 5])) {
            return response()->json(['error' => 'Đơn hàng đang giao hoặc đã hoàn thành, không thể hủy!'], 400);
        }

        $order->trangThaiDonHang = 0; // 0: DA_HUY
        $order->save();

        return response()->json(['message' => 'Đã hủy đơn hàng thành công']);
    }

    /**
     * Customer: Checkout Online (Thông qua Stored Procedure sp_checkout_online)
     */
    public function checkoutOnline(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nguoidung = $user->nguoidung;
        if (!$nguoidung) {
            return response()->json(['error' => 'Không tìm thấy hồ sơ người dùng'], 404);
        }

        $request->validate([
            'cuaHangId' => 'required|exists:cuahang,id',
            'items' => 'required|array|min:1',
            'items.*.sanpham_id' => 'required|exists:sanPham,id',
            'items.*.so_luong' => 'required|integer|min:1',
            'diaChiGiaoHang' => 'required|string|max:255',
            'phuongThucThanhToan' => 'required|integer|in:0,1,2',
            'ma_voucher' => 'nullable|string',
        ]);

        try {
            DB::statement('CALL sp_checkout_online(?, ?, ?, ?, ?, ?, ?, @donhang_id, @ma_don_hang, @error)', [
                $nguoidung->id,
                $request->cuaHangId,
                $request->diaChiGiaoHang,
                $request->phuongThucThanhToan,
                $request->ma_voucher ?? null,
                $request->ghiChu ?? null,
                json_encode($request->items),
            ]);

            $res = DB::select('SELECT @donhang_id AS donhang_id, @ma_don_hang AS ma_don_hang, @error AS error')[0];

            if ($res->error) {
                return response()->json(['error' => 'Đặt hàng thất bại: ' . $res->error], 500);
            }

            $donHang = DonHang::find($res->donHangId);
            return response()->json(['message' => 'Đặt hàng trực tuyến thành công', 'data' => $donHang->load('chitiet')], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Đặt hàng thất bại: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Customer: Cancel order if not yet shipped
     */
    public function cancelOrder($id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        DB::statement('CALL sp_cancel_order(?, @error)', [$id]);

        $res = DB::select('SELECT @error AS error')[0];

        if ($res->error) {
            return response()->json(['error' => $res->error], 400);
        }

        return response()->json(['message' => 'Đã hủy đơn hàng thành công']);
    }

    /**
     * GHN Webhook Handler
     */
    public function handleGHNWebhook(Request $request)
    {
        $maVanDon = $request->OrderCode ?? $request->ma_van_don;
        $ghnStatusCode = $request->Status ?? $request->ghnStatusCode;

        if (!$maVanDon) {
            return response()->json(['error' => 'Mã vận đơn không hợp lệ'], 400);
        }

        DB::statement('CALL sp_handle_ghn_webhook(?, ?, @donhang_id, @error)', [
            $maVanDon,
            $ghnStatusCode,
        ]);

        $res = DB::select('SELECT @donhang_id AS donhang_id, @error AS error')[0];

        if ($res->error) {
            return response()->json(['error' => $res->error], 404);
        }

        return response()->json(['success' => true, 'message' => 'Cập nhật Webhook GHN thành công']);
    }

    /**
     * POS Checkout Proxy Method
     */
    public function storePOS(Request $request)
    {
        return (new PosShiftController())->posCheckout($request);
    }

    /**
     * Xác nhận kiểm định hàng hoàn (Dành cho nhân viên kiểm tra bánh bị hoàn trả)
     */
    public function confirmReturnInspection(Request $request, $id)
    {
        $order = DonHang::where('maDonHang', $id)
            ->orWhere('id', $id)
            ->orWhere('maVanDonGhn', $id)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);
        }

        $restock = filter_var($request->input('restock', true), FILTER_VALIDATE_BOOLEAN);
        $reason = $request->input('reason', 'Nhân viên đã kiểm tra chất lượng hàng hoàn');

        $oldStatus = $order->trangThaiDonHang;
        $order->trangThaiDonHang = 6; // HOAN_HANG (Đã hoàn tất kiểm định hoàn)

        // Nếu bánh còn dùng được -> cộng lại vào tồn kho
        if ($restock) {
            foreach ($order->chitiet as $item) {
                $tonKho = \App\Models\TonKhoChiNhanh::where('cuaHangId', $order->cuaHangId)
                    ->where('sanPhamId', $item->sanPhamId)
                    ->first();
                if ($tonKho) {
                    $tonKho->increment('soLuongTon', $item->soLuong);
                }
            }
            $desc = "Xác nhận hàng hoàn: Bánh còn dùng được -> Đã cộng trả lại kho. Ghi chú: {$reason}";
        } else {
            $desc = "Xác nhận hàng hoàn: Bánh bị hỏng/tiêu hủy -> KHÔNG nhập lại kho. Ghi chú: {$reason}";
        }

        // Nếu đơn đã từng thanh toán trực tuyến -> chuyển sang trạng thái Hoàn tiền (2)
        if ($order->trangThaiThanhToan == 1) {
            $order->trangThaiThanhToan = 2; // HOAN_TIEN
        }

        $order->save();

        \App\Models\LichSuTrangThaiDonHang::create([
            'donHangId' => $order->id,
            'trangThaiCu' => $oldStatus,
            'trangThaiMoi' => 6,
            'ghnStatusCode' => $order->ghnStatusCode ?? 'returned_inspected',
            'moTaChiTiet' => $desc,
            'nguonCapNhat' => 1, // Nhân viên
        ]);

        return response()->json([
            'success' => true,
            'message' => $desc,
            'data' => $order->refresh()
        ]);
    }
}
