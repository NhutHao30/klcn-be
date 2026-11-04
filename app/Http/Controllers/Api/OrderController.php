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
        6 => 'Đã hoàn hàng',
        7 => 'Chờ lấy hàng',
        8 => 'Yêu cầu hoàn trả',
    ];

    private static array $stringToStatusMap = [
        'Đã hủy' => 0,
        'Chờ xác nhận' => 1,
        'Chờ xử lý' => 1,
        'Đang chuẩn bị' => 2,
        'Chờ lấy hàng' => 7,
        'Đang giao' => 3,
        'Đã hoàn thành' => 4,
        'Đã hoàn hàng' => 6,
        'Yêu cầu hoàn trả' => 8,
        'Yêu cầu trả hàng' => 8,
        'DA_HUY' => 0,
        'CHO_XAC_NHAN' => 1,
        'DANG_CHE_BIEN' => 2,
        'CHO_LAY_HANG' => 7,
        'DANG_GIAO' => 3,
        'HOAN_THANH' => 4,
        'HOAN_HANG' => 6,
        'YEU_CAU_HOAN_HANG' => 8,
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
            $khSdt = $order->nguoidung ? ($order->nguoidung->soDienThoai ?? '') : '';
            if (empty($khSdt) && $order->nguoidungId) {
                $defaultDiaChi = \App\Models\DiaChi::where('nguoidungId', $order->nguoidungId)
                    ->whereNotNull('soDienThoai')
                    ->where('soDienThoai', '!=', '')
                    ->orderBy('isDefault', 'desc')
                    ->first();
                if ($defaultDiaChi) {
                    $khSdt = $defaultDiaChi->soDienThoai;
                }
            }
            $khEmail = ($order->nguoidung && $order->nguoidung->taikhoan) ? $order->nguoidung->taikhoan->email : '';

            $staff = $order->nhanVienId ? \Illuminate\Support\Facades\DB::table('nguoidung')->where('id', $order->nhanVienId)->first() : null;
            $staffName = $staff ? $staff->hoTen : ($order->loaiDonHang == 0 ? 'Hệ thống (Đặt online)' : 'Nhân viên thu ngân');

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
                'hoTenKH' => $khName,
                'HOTEN_KH' => $khName,
                'TENKH' => $khName,
                'sdtKH' => $khSdt,
                'SDT' => $khSdt,
                'soDienThoai' => $khSdt,
                'nhanVienLap' => $staffName,
                'tenNhanVien' => $staffName,
                'NHANVIEN' => $staffName,
                'SDT' => $khSdt,
                'EMAIL' => $khEmail,
                'ngayLap' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
                'CREATED_AT' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
                'NGAYTAO' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
                'tongTien' => (float) $order->tongThanhToan,
                'TONGTIEN' => (float) $order->tongThanhToan,
                'TONG_TIEN' => (float) $order->tongThanhToan,
                'tongTienHang' => (float) ($order->tongTienHang ?: ($order->tongThanhToan - $order->phiVanChuyen + $order->soTienGiam)),
                'soTienGiam' => (float) $order->soTienGiam,
                'phiVanChuyen' => (float) $order->phiVanChuyen,
                'donViVanChuyen' => $order->loaiDonHang == 1 ? 'Mua tại cửa hàng (POS)' : ($order->don_vi_van_chuyen ?? 'GHN Express'),
                'maVanDon' => $order->maVanDonGhn ?? '',
                'maVanDonGhn' => $order->maVanDonGhn ?? '',
                'MAVANDON' => $order->maVanDonGhn ?? '',
                'ghiChu' => $order->ghiChu ?? '',
                'diaChiGiao' => $order->diaChiGiaoHang ?? '',
                'DIACHI_GIAO' => $order->diaChiGiaoHang ?? '',
                'lyDoHuy' => $order->lyDoHuy ?? '',
                'ghiChuHuy' => $order->ghiChuHuy ?? '',
                'nganHangHoanTien' => $order->nganHangHoanTien ?? '',
                'soTaiKhoanHoanTien' => $order->soTaiKhoanHoanTien ?? '',
                'tenNguoiNhanHoanTien' => $order->tenNguoiNhanHoanTien ?? '',
                'soTienHoan' => $order->soTienHoan !== null ? (float)$order->soTienHoan : null,
                'maGiaoDichHoanTien' => $order->maGiaoDichHoanTien ?? '',
                'trangThaiHoanTien' => $order->trangThaiHoanTien ?? '',
                'ngayHoanTien' => $order->ngayHoanTien ? \Carbon\Carbon::parse($order->ngayHoanTien)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : '',
                'ghiChuHoanTien' => $order->ghiChuHoanTien ?? '',
                'minhChungHoanTien' => $order->minhChungHoanTien ?? null,
                'nhanVienHoanTienId' => $order->nhanVienHoanTienId ?? null,
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
        $khSdt = $order->nguoidung ? ($order->nguoidung->soDienThoai ?? '') : '';
        if (empty($khSdt) && $order->nguoidungId) {
            $defaultDiaChi = \App\Models\DiaChi::where('nguoidungId', $order->nguoidungId)
                ->whereNotNull('soDienThoai')
                ->where('soDienThoai', '!=', '')
                ->orderBy('isDefault', 'desc')
                ->first();
            if ($defaultDiaChi) {
                $khSdt = $defaultDiaChi->soDienThoai;
            }
        }
        $khEmail = ($order->nguoidung && $order->nguoidung->taikhoan) ? $order->nguoidung->taikhoan->email : '';

        $staff = $order->nhanVienId ? \Illuminate\Support\Facades\DB::table('nguoidung')->where('id', $order->nhanVienId)->first() : null;
        $staffName = $staff ? $staff->hoTen : ($order->loaiDonHang == 0 ? 'Hệ thống (Đặt online)' : 'Nhân viên thu ngân');

        $staffRefund = $order->nhanVienHoanTienId ? \Illuminate\Support\Facades\DB::table('nguoidung')->where('id', $order->nhanVienHoanTienId)->first() : null;
        $staffRefundName = $staffRefund ? $staffRefund->hoTen : '';

        $stTitle = self::$statusMap[$order->trangThaiDonHang] ?? 'Chờ xử lý';

        return response()->json([
            'id' => $order->id,
            'maHD' => $order->maDonHang,
            'MAHD' => $order->maDonHang,
            'tenCuaHang' => $order->cuahang ? $order->cuahang->ten_cuahang : 'Cửa hàng chính',
            'maKH' => $khCode,
            'tenKH' => $khName,
            'hoTenKH' => $khName,
            'HOTEN_KH' => $khName,
            'TENKH' => $khName,
            'sdtKH' => $khSdt,
            'SDT' => $khSdt,
            'soDienThoai' => $khSdt,
            'nhanVienLap' => $staffName,
            'tenNhanVien' => $staffName,
            'NHANVIEN' => $staffName,
            'SDT' => $khSdt,
            'EMAIL' => $khEmail,
            'ngayLap' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
            'CREATED_AT' => $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : '',
            'tongTien' => (float) $order->tongThanhToan,
            'TONGTIEN' => (float) $order->tongThanhToan,
            'tongTienHang' => (float) ($order->tongTienHang ?: ($order->tongThanhToan - $order->phiVanChuyen + $order->soTienGiam)),
            'soTienGiam' => (float) $order->soTienGiam,
            'phiVanChuyen' => (float) $order->phiVanChuyen,
            'donViVanChuyen' => $order->loaiDonHang == 1 ? 'Mua tại cửa hàng (POS)' : ($order->don_vi_van_chuyen ?? 'GHN Express'),
            'maVanDon' => $order->maVanDonGhn ?? '',
            'maVanDonGhn' => $order->maVanDonGhn ?? '',
            'MAVANDON' => $order->maVanDonGhn ?? '',
            'ghiChu' => $order->ghiChu ?? '',
            'diaChiGiao' => $order->diaChiGiaoHang ?? '',
            'DIACHI_GIAO' => $order->diaChiGiaoHang ?? '',
            'lyDoHuy' => $order->lyDoHuy ?? '',
            'ghiChuHuy' => $order->ghiChuHuy ?? '',
            'nganHangHoanTien' => $order->nganHangHoanTien ?? '',
            'soTaiKhoanHoanTien' => $order->soTaiKhoanHoanTien ?? '',
            'tenNguoiNhanHoanTien' => $order->tenNguoiNhanHoanTien ?? '',
            'soTienHoan' => $order->soTienHoan !== null ? (float)$order->soTienHoan : null,
            'maGiaoDichHoanTien' => $order->maGiaoDichHoanTien ?? '',
            'trangThaiHoanTien' => $order->trangThaiHoanTien ?? '',
            'ngayHoanTien' => $order->ngayHoanTien ? \Carbon\Carbon::parse($order->ngayHoanTien)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : '',
            'ghiChuHoanTien' => $order->ghiChuHoanTien ?? '',
            'minhChungHoanTien' => $order->minhChungHoanTien ?? null,
            'nhanVienHoanTienId' => $order->nhanVienHoanTienId ?? null,
            'tenNhanVienHoan' => $staffRefundName,
            'minhChungThanhToan' => $order->minhChungThanhToan ?? null,
            'lyDoHoanTra' => $order->lyDoHoanTra ?? '',
            'ghiChuHoanTra' => $order->ghiChuHoanTra ?? '',
            'hinhAnhHoanTra' => $order->hinhAnhHoanTra ?? null,
            'ngayYeuCauHoanTra' => $order->ngayYeuCauHoanTra ? \Carbon\Carbon::parse($order->ngayYeuCauHoanTra)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : '',
            'trangThaiHoanHang' => $order->trangThaiHoanHang ?? '',
            'lyDoTuChoiHoanTra' => $order->lyDoTuChoiHoanTra ?? '',
            'isReturnable' => in_array((int)$order->trangThaiDonHang, [4, 5]) && empty($order->trangThaiHoanHang) && (!empty($order->updatedAt) ? now()->diffInDays(\Carbon\Carbon::parse($order->updatedAt)) <= 3 : true),
            'isCancellable' => in_array((int)$order->trangThaiDonHang, [1, 2, 7]) && (int)$order->trangThaiThanhToan === 0,
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

        // Lấy thông tin các đánh giá đã có của đơn hàng này
        $reviews = \App\Models\DanhGiaSanPham::where('donHangId', $order->id)->get()->keyBy('sanPhamId');

        $items = $order->chitiet->map(function ($item) use ($reviews) {
            $sp = $item->sanpham;
            $name = $sp ? $sp->tenSanPham : ('Sản phẩm #' . $item->sanPhamId);
            $masp = $sp ? ($sp->ma_san_pham ?? ('SP' . str_pad($sp->id, 5, '0', STR_PAD_LEFT))) : ('SP' . $item->sanPhamId);
            $sl = (int) $item->soLuong;
            $gia = (float) $item->donGia;
            $tt = (float) $item->thanhTien;

            $rev = $reviews[$item->sanPhamId] ?? null;

            return [
                'id' => $item->sanPhamId,
                'sanPhamId' => $item->sanPhamId,
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
                'daDanhGia' => $rev !== null,
                'soSao' => $rev ? (int)$rev->soSao : null,
                'noiDung' => $rev ? $rev->noiDung : null,
                'hinhAnhDanhGia' => $rev ? $rev->hinhAnhKemTheo : null,
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
        try {
            $order = DonHang::where('maDonHang', $id)
                ->orWhere('id', $id)
                ->first();

            if (!$order) {
                return response()->json(['error' => 'Không tìm thấy hóa đơn'], 404);
            }

            $currentStatus = (int) $order->trangThaiDonHang;
            if (in_array($currentStatus, [0, 4, 5], true)) {
                $statusName = $currentStatus === 0 ? 'Đã hủy' : 'Đã hoàn thành';
                return response()->json([
                    'success' => false,
                    'error' => "Đơn hàng đã ở trạng thái [{$statusName}], không thể cập nhật thêm trạng thái khác!",
                    'message' => "Đơn hàng đã ở trạng thái [{$statusName}], không thể cập nhật thêm trạng thái khác!",
                ], 400);
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

            // Lưu trực tiếp các trường hợp lệ vào donHang
            $order->save();

            // Tự động đồng bộ điểm tích lũy & hạng thành viên cho khách hàng khi đơn hàng hoàn tất
            if (in_array((int)$order->trangThaiDonHang, [4, 5], true) && $order->nguoidung) {
                try {
                    $order->nguoidung->syncLoyaltyPoints();
                } catch (\Throwable $eLoyalty) {}
            }

            // Refresh để trả về dữ liệu mới nhất (bao gồm maVanDonGhn vừa được GHN tạo)
            $order->refresh();

            return response()->json(['message' => 'Cập nhật trạng thái thành công!' . $ghnMsg, 'data' => $order]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi updateStatus đơn hàng: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['error' => 'Lỗi khi cập nhật trạng thái đơn hàng: ' . $e->getMessage()], 400);
        }
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

        $currentStatus = (int) $order->trangThaiDonHang;
        if (in_array($currentStatus, [0, 4, 5], true)) {
            $statusName = $currentStatus === 0 ? 'Đã hủy' : 'Đã hoàn thành';
            return response()->json([
                'success' => false,
                'error' => "Đơn hàng đã ở trạng thái [{$statusName}], không thể cập nhật thêm trạng thái khác!",
                'message' => "Đơn hàng đã ở trạng thái [{$statusName}], không thể cập nhật thêm trạng thái khác!",
            ], 400);
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

        $orders = DonHang::with(['chitiet.sanpham', 'cuahang', 'nguoidung.taikhoan'])
            ->where('nguoidungId', $nguoidungId)
            ->orderBy('id', 'desc')
            ->get();

        $content = $orders->map(function ($order) {
            $items = $order->chitiet->map(function ($ct) {
                $sp = $ct->sanpham;
                $spName = $sp ? ($sp->tenSanPham ?? $sp->TENSP ?? 'Sản phẩm') : 'Sản phẩm';
                $spCode = $sp ? ($sp->MASP ?? 'SP' . $ct->sanPhamId) : ('SP' . $ct->sanPhamId);
                $img = $sp ? ($sp->hinhAnh ?? $sp->HINHANH ?? null) : null;
                return [
                    'id' => $ct->id,
                    'sanPhamId' => $ct->sanPhamId,
                    'MASP' => $spCode,
                    'maSP' => $spCode,
                    'tenSP' => $spName,
                    'TENSP' => $spName,
                    'name' => $spName,
                    'soLuong' => (int) $ct->soLuong,
                    'SO_LUONG' => (int) $ct->soLuong,
                    'quantity' => (int) $ct->soLuong,
                    'donGia' => (float) $ct->donGia,
                    'DONGIA' => (float) $ct->donGia,
                    'price' => (float) $ct->donGia,
                    'thanhTien' => (float) $ct->thanhTien,
                    'image' => $img,
                    'hinhAnh' => $img,
                    'sanpham' => [
                        'TENSP' => $spName,
                        'tenSanPham' => $spName,
                        'hinhAnh' => $img,
                    ]
                ];
            });

            $stTitle = self::$statusMap[$order->trangThaiDonHang] ?? 'Chờ xử lý';
            $ngayTao = $order->createdAt ? $order->createdAt->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') : '';

            $khName = $order->nguoidung ? $order->nguoidung->hoTen : 'Khách hàng';
            $khSdt = $order->nguoidung ? ($order->nguoidung->soDienThoai ?? '') : '';
            if (empty($khSdt) && $order->nguoidungId) {
                $defaultDiaChi = \App\Models\DiaChi::where('nguoidungId', $order->nguoidungId)
                    ->whereNotNull('soDienThoai')
                    ->where('soDienThoai', '!=', '')
                    ->orderBy('isDefault', 'desc')
                    ->first();
                if ($defaultDiaChi) {
                    $khSdt = $defaultDiaChi->soDienThoai;
                }
            }
            $khEmail = ($order->nguoidung && $order->nguoidung->taikhoan) ? $order->nguoidung->taikhoan->email : '';

            return [
                'id' => $order->id,
                'maHD' => $order->maDonHang,
                'MAHD' => $order->maDonHang,
                'tenCuaHang' => $order->cuahang ? ($order->cuahang->tenCuaHang ?? $order->cuahang->ten_cuahang ?? 'Dola Bakery') : 'Dola Bakery',
                'ngayLap' => $ngayTao,
                'created_at' => $order->createdAt ? $order->createdAt->toISOString() : '',
                'tongTienHang' => (float) ($order->tongTienHang ?: ($order->tongThanhToan - $order->phiVanChuyen + $order->soTienGiam)),
                'phiVanChuyen' => (float) $order->phiVanChuyen,
                'soTienGiam' => (float) $order->soTienGiam,
                'tongTien' => (float) $order->tongThanhToan,
                'TONG_TIEN' => (float) $order->tongThanhToan,
                'total' => (float) $order->tongThanhToan,
                'donViVanChuyen' => $order->loaiDonHang == 1 ? 'Mua tại cửa hàng (POS)' : 'Giao hàng tiêu chuẩn',
                'maVanDon' => $order->maVanDonGhn ?? '',
                'maVanDonGhn' => $order->maVanDonGhn ?? '',
                'MAVANDON' => $order->maVanDonGhn ?? '',
                'ghiChu' => $order->ghiChu ?? 'Không có',
                'diaChiGiao' => $order->diaChiGiaoHang ?? '',
                'diaChiGiaoHang' => $order->diaChiGiaoHang ?? '',
                'DIACHI_GIAO' => $order->diaChiGiaoHang ?? '',
                'lyDoHuy' => $order->lyDoHuy ?? '',
                'ghiChuHuy' => $order->ghiChuHuy ?? '',
                'nganHangHoanTien' => $order->nganHangHoanTien ?? '',
                'soTaiKhoanHoanTien' => $order->soTaiKhoanHoanTien ?? '',
                'tenNguoiNhanHoanTien' => $order->tenNguoiNhanHoanTien ?? '',
                'soTienHoan' => $order->soTienHoan !== null ? (float)$order->soTienHoan : null,
                'maGiaoDichHoanTien' => $order->maGiaoDichHoanTien ?? '',
                'trangThaiHoanTien' => $order->trangThaiHoanTien ?? '',
                'ngayHoanTien' => $order->ngayHoanTien ? \Carbon\Carbon::parse($order->ngayHoanTien)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : '',
                'ghiChuHoanTien' => $order->ghiChuHoanTien ?? '',
                'minhChungHoanTien' => $order->minhChungHoanTien ?? null,
                'lyDoHoanTra' => $order->lyDoHoanTra ?? '',
                'ghiChuHoanTra' => $order->ghiChuHoanTra ?? '',
                'hinhAnhHoanTra' => $order->hinhAnhHoanTra ?? null,
                'ngayYeuCauHoanTra' => $order->ngayYeuCauHoanTra ? \Carbon\Carbon::parse($order->ngayYeuCauHoanTra)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : '',
                'trangThaiHoanHang' => $order->trangThaiHoanHang ?? '',
                'lyDoTuChoiHoanTra' => $order->lyDoTuChoiHoanTra ?? '',
                'isReturnable' => in_array((int)$order->trangThaiDonHang, [4, 5]) && empty($order->trangThaiHoanHang) && (!empty($order->updatedAt) ? now()->diffInDays(\Carbon\Carbon::parse($order->updatedAt)) <= 3 : true),
                'isCancellable' => in_array((int)$order->trangThaiDonHang, [1, 2, 7]) && (int)$order->trangThaiThanhToan === 0,
                'trangThai' => $stTitle,
                'TRANGTHAI' => $stTitle,
                'status' => $stTitle,
                'trangThaiDonHang' => $order->trangThaiDonHang,
                'trangThaiThanhToan' => $order->trangThaiThanhToan,
                'phuongThucTT' => $order->phuongThucThanhToan == 1 ? 'Chuyển khoản / VietQR' : ($order->phuongThucThanhToan == 2 ? 'MoMo' : 'COD — thanh toán khi nhận hàng'),
                'PHUONGTHUCTHANHTOAN' => $order->phuongThucThanhToan == 1 ? 'Chuyển khoản / VietQR' : ($order->phuongThucThanhToan == 2 ? 'MoMo' : 'COD — thanh toán khi nhận hàng'),
                'loaiDonHang' => $order->loaiDonHang == 1 ? 'Bán tại quầy' : 'Trực tuyến',
                'minhChungThanhToan' => $order->minhChungThanhToan,
                'tenKH' => $khName,
                'hoTenKH' => $khName,
                'HOTEN_KH' => $khName,
                'sdtKH' => $khSdt,
                'SDT' => $khSdt,
                'EMAIL' => $khEmail,
                'khach_hang' => [
                    'HOTEN' => $khName,
                    'SDT' => $khSdt,
                    'EMAIL' => $khEmail,
                    'user' => [
                        'EMAIL' => $khEmail
                    ]
                ],
                'chitiet' => $items,
                'details' => $items,
                'items' => $items,
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
     * Customer: Cancel order with reason
     * Đơn hàng chỉ được hủy khi trạng thái là chờ xử lý hoặc chưa giao hàng
     */
    public function customerCancelOrder(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $order = DonHang::where('maDonHang', $id)->orWhere('id', $id)->first();
        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Kiểm tra quyền hủy của khách hàng
        if ($user->vaiTroId == 6 && $user->nguoidung) {
            if ($order->nguoidungId && $order->nguoidungId !== $user->nguoidung->id) {
                return response()->json(['error' => 'Bạn không có quyền hủy đơn hàng này'], 403);
            }
        }

        // Đơn hàng chỉ được hủy khi trạng thái là chờ xử lý hoặc chưa giao hàng (1: Chờ xác nhận, 2: Đang chuẩn bị, 7: Chờ lấy hàng)
        if (in_array((int)$order->trangThaiDonHang, [3, 4, 5])) {
            return response()->json(['error' => 'Đơn hàng đang giao hoặc đã hoàn thành, không thể hủy!'], 400);
        }

        if (in_array((int)$order->trangThaiDonHang, [0, 6, 8])) {
            return response()->json(['error' => 'Đơn hàng này không ở trạng thái có thể hủy!'], 400);
        }

        // QUY TẮC BẮT BUỘC: Đơn hàng chỉ cho phép hủy khi khách CHƯA THANH TOÁN (trangThaiThanhToan == 0).
        // Nếu khách đã thanh toán (trangThaiThanhToan == 1), KHÔNG CHO PHÉP HỦY, chỉ cho phép hoàn hàng sau khi nhận hàng.
        if ((int)$order->trangThaiThanhToan === 1) {
            return response()->json([
                'error' => 'Đơn hàng đã được thanh toán không thể hủy đơn! Quý khách vui lòng nhận hàng hoặc yêu cầu Trả hàng & Hoàn tiền theo chính sách sau khi đơn hàng được giao.'
            ], 400);
        }

        $lyDoHuy = trim((string)($request->input('lyDoHuy') ?? $request->input('reason') ?? $request->input('ly_do_huy') ?? ''));
        if (empty($lyDoHuy)) {
            return response()->json(['error' => 'Vui lòng chọn 1 trong các lý do hủy đơn!'], 422);
        }

        $ghiChuHuy = trim((string)($request->input('ghiChuHuy') ?? $request->input('note') ?? $request->input('noiDungBoSung') ?? $request->input('noi_dung_bo_sung') ?? ''));

        $nganHangHoan = trim((string)($request->input('nganHangHoanTien') ?? $request->input('ngan_hang_hoan') ?? $request->input('bank') ?? ''));
        $soTaiKhoanHoan = trim((string)($request->input('soTaiKhoanHoanTien') ?? $request->input('so_tai_khoan_hoan') ?? $request->input('accountNo') ?? ''));
        $tenNguoiNhanHoan = trim((string)($request->input('tenNguoiNhanHoanTien') ?? $request->input('chu_tai_khoan_hoan') ?? $request->input('accountName') ?? ''));

        $oldStatus = (int) $order->trangThaiDonHang;
        $order->trangThaiDonHang = 0; // 0: DA_HUY
        $order->lyDoHuy = $lyDoHuy;
        $order->ghiChuHuy = $ghiChuHuy;

        // Nếu đơn đã từng thanh toán trực tuyến hoặc chuyển khoản -> Chuyển sang Hoàn tiền (2)
        $isPaidOnline = (int)$order->trangThaiThanhToan === 1 || in_array((int)$order->phuongThucThanhToan, [1, 2]);
        if ($isPaidOnline) {
            $order->trangThaiThanhToan = 2; // 2: HOAN_TIEN (Chờ hoàn tiền)
            $order->trangThaiHoanTien = 'CHO_HOAN_TIEN';
        }

        if ($nganHangHoan) {
            $order->nganHangHoanTien = $nganHangHoan;
        }
        if ($soTaiKhoanHoan) {
            $order->soTaiKhoanHoanTien = $soTaiKhoanHoan;
        }
        if ($tenNguoiNhanHoan) {
            $order->tenNguoiNhanHoanTien = mb_strtoupper($tenNguoiNhanHoan, 'UTF-8');
        }

        $order->save();

        // Hoàn trả lại số lượng tồn kho cho chi nhánh
        if ($order->cuaHangId) {
            foreach ($order->chitiet as $item) {
                $tonKho = TonKhoChiNhanh::where('cuaHangId', $order->cuaHangId)
                    ->where('sanPhamId', $item->sanPhamId)
                    ->first();
                if ($tonKho) {
                    $tonKho->increment('soLuongTon', $item->soLuong);
                }
            }
        }

        // Hoàn lại voucher nếu khách có dùng
        if (!empty($order->maVoucher) && $order->nguoidungId) {
            DB::table('voucher_khachhang')
                ->where('nguoidungId', $order->nguoidungId)
                ->where('maVoucher', $order->maVoucher)
                ->update([
                    'trangThai' => 0,
                    'ngaySuDung' => null,
                    'maDonHang' => null
                ]);

            $voucherObj = \App\Models\Voucher::where('MA_VOUCHER', $order->maVoucher)->first();
            if ($voucherObj && $voucherObj->SO_LUONG_DA_DUNG > 0) {
                $voucherObj->decrement('SO_LUONG_DA_DUNG');
            }
        }

        // Ghi vết vào lịch sử trạng thái
        LichSuTrangThaiDonHang::create([
            'donHangId' => $order->id,
            'trangThaiCu' => $oldStatus,
            'trangThaiMoi' => 0,
            'ghnStatusCode' => 'cancel',
            'moTaChiTiet' => "Khách hàng hủy đơn: {$lyDoHuy}" . ($ghiChuHuy ? " (Bổ sung: {$ghiChuHuy})" : ""),
            'nguonCapNhat' => 2, // Khách hàng
        ]);

        // Hủy đơn trên GHN nếu đơn đã được đẩy mã vận đơn sang GHN
        if (!empty($order->maVanDonGhn)) {
            try {
                \App\Jobs\CancelOrderOnGhnJob::dispatch($order->maVanDonGhn);
            } catch (\Exception $eGhn) {
                \Illuminate\Support\Facades\Log::warning("Không thể dispatch job hủy GHN: " . $eGhn->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy đơn hàng thành công!',
            'data' => $order
        ]);
    }

    /**
     * Admin/Staff confirm refund completed for customer
     */
    public function refundOrder(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $order = DonHang::where('maDonHang', $id)->orWhere('id', $id)->first();
        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);
        }

        $ghiChu = trim((string)($request->input('ghiChu') ?? $request->input('note') ?? $request->input('ghiChuHoanTien') ?? 'Đã hoàn tiền cho khách qua chuyển khoản'));
        $soTienHoan = (float)($request->input('soTienHoan') ?? $request->input('amount') ?? $order->tongThanhToan);
        $maGiaoDich = trim((string)($request->input('maGiaoDichHoanTien') ?? $request->input('transactionCode') ?? $request->input('maGD') ?? ''));

        // Handle upload of refund proof file (ảnh biên lai/chuyển tiền hoàn)
        $proofUrl = null;
        if ($request->hasFile('minhChungHoanTien') || $request->hasFile('receipt') || $request->hasFile('proof') || $request->hasFile('minhChung') || $request->hasFile('image')) {
            $file = $request->file('minhChungHoanTien') ?? $request->file('receipt') ?? $request->file('proof') ?? $request->file('minhChung') ?? $request->file('image');
            try {
                $uploadService = new \App\Services\UploadService();
                $proofUrl = $uploadService->uploadImage($file, 'refunds');
            } catch (\Exception $eUpload) {
                \Illuminate\Support\Facades\Log::warning("Lỗi upload minh chứng hoàn tiền: " . $eUpload->getMessage());
            }
        } elseif ($request->input('minhChungHoanTien') || $request->input('proofUrl')) {
            $proofUrl = $request->input('minhChungHoanTien') ?? $request->input('proofUrl');
        }

        $order->trangThaiThanhToan = 3; // 3: DA_HOAN_TIEN
        $order->trangThaiHoanTien = 'DA_HOAN_TIEN';
        $order->ngayHoanTien = now();
        $order->ghiChuHoanTien = $ghiChu;
        $order->soTienHoan = $soTienHoan;
        if ($maGiaoDich) {
            $order->maGiaoDichHoanTien = $maGiaoDich;
        }
        if ($proofUrl) {
            $order->minhChungHoanTien = $proofUrl;
        }
        if ($user && $user->nguoidung) {
            $order->nhanVienHoanTienId = $user->nguoidung->id;
        }
        $order->save();

        $staffName = ($user && $user->nguoidung) ? $user->nguoidung->hoTen : 'Nhân viên';

        // Ghi vết vào lịch sử trạng thái
        LichSuTrangThaiDonHang::create([
            'donHangId' => $order->id,
            'trangThaiCu' => $order->trangThaiDonHang,
            'trangThaiMoi' => $order->trangThaiDonHang,
            'ghnStatusCode' => 'refunded',
            'moTaChiTiet' => "{$staffName} xác nhận hoàn tiền: " . number_format($soTienHoan, 0, ',', '.') . "đ" . ($maGiaoDich ? " (Mã GD: {$maGiaoDich})" : "") . ($ghiChu ? " - Ghi chú: {$ghiChu}" : ""),
            'nguonCapNhat' => 1, // Nhân viên
        ]);

        return response()->json([
            'success' => true,
            'message' => "Đã xác nhận hoàn tiền thành công (" . number_format($soTienHoan, 0, ',', '.') . "đ) cho đơn hàng #{$order->maDonHang}!",
            'data' => [
                'id' => $order->id,
                'maDonHang' => $order->maDonHang,
                'trangThaiThanhToan' => $order->trangThaiThanhToan,
                'trangThaiHoanTien' => $order->trangThaiHoanTien,
                'soTienHoan' => (float)$order->soTienHoan,
                'maGiaoDichHoanTien' => $order->maGiaoDichHoanTien,
                'minhChungHoanTien' => $order->minhChungHoanTien,
                'tenNhanVienHoan' => $staffName,
                'ngayHoanTien' => $order->ngayHoanTien ? $order->ngayHoanTien->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : '',
                'ghiChuHoanTien' => $order->ghiChuHoanTien,
            ]
        ]);
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

    /**
     * Khách hàng: Gửi yêu cầu Trả hàng & Hoàn tiền khi đơn đã giao (hỏng hóc hoặc trong hạn đổi trả)
     */
    public function customerRequestReturn(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $order = DonHang::where('maDonHang', $id)->orWhere('id', $id)->first();
        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Kiểm tra quyền
        if ($user->vaiTroId == 6 && $user->nguoidung) {
            if ($order->nguoidungId && $order->nguoidungId !== $user->nguoidung->id) {
                return response()->json(['error' => 'Bạn không có quyền gửi yêu cầu cho đơn hàng này'], 403);
            }
        }

        // Chỉ cho phép trả hàng khi đơn hàng ĐÃ GIAO / HOÀN THÀNH (4 hoặc 5)
        if (!in_array((int)$order->trangThaiDonHang, [4, 5])) {
            return response()->json([
                'error' => 'Chỉ có thể yêu cầu trả hàng khi đơn hàng đã được giao đến bạn thành công!'
            ], 400);
        }

        // Kiểm tra nếu đã yêu cầu hoàn trả hoặc đã hoàn
        if ((int)$order->trangThaiDonHang === 8 || $order->trangThaiHoanHang === 'CHO_DUYET') {
            return response()->json([
                'error' => 'Đơn hàng này đang có yêu cầu hoàn trả chờ xét duyệt, vui lòng không gửi lại!'
            ], 400);
        }
        if ((int)$order->trangThaiDonHang === 6 || $order->trangThaiHoanHang === 'DA_HOAN_HANG') {
            return response()->json(['error' => 'Đơn hàng này đã được xử lý hoàn trả trước đó!'], 400);
        }

        // Kiểm tra thời hạn đổi trả (trong vòng 3 ngày kể từ khi đơn hoàn thành)
        $deliveryDate = $order->updatedAt ?? $order->createdAt;
        if ($deliveryDate && now()->diffInDays(\Carbon\Carbon::parse($deliveryDate)) > 3) {
            return response()->json([
                'error' => 'Đã quá thời hạn đổi trả quy định (3 ngày kể từ ngày nhận hàng). Không thể gửi yêu cầu hoàn trả!'
            ], 400);
        }

        $lyDo = trim((string)($request->input('lyDoHoanTra') ?? $request->input('reason') ?? ''));
        if (empty($lyDo)) {
            return response()->json(['error' => 'Vui lòng chọn hoặc nhập lý do hoàn trả hàng!'], 422);
        }

        $ghiChu = trim((string)($request->input('ghiChuHoanTra') ?? $request->input('note') ?? ''));
        $nganHang = trim((string)($request->input('nganHangHoanTien') ?? $request->input('bank') ?? ''));
        $soTaiKhoan = trim((string)($request->input('soTaiKhoanHoanTien') ?? $request->input('accountNo') ?? ''));
        $tenNguoiNhan = trim((string)($request->input('tenNguoiNhanHoanTien') ?? $request->input('accountName') ?? ''));

        // Xử lý upload ảnh minh chứng hỏng hóc nếu có
        $proofImage = null;
        if ($request->hasFile('hinhAnhHoanTra') || $request->hasFile('image') || $request->hasFile('evidence')) {
            $file = $request->file('hinhAnhHoanTra') ?? $request->file('image') ?? $request->file('evidence');
            try {
                $uploadService = new \App\Services\UploadService();
                $proofImage = $uploadService->uploadImage($file, 'returns');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Lỗi upload ảnh minh chứng hoàn trả: " . $e->getMessage());
            }
        } elseif ($request->input('hinhAnhHoanTra') || $request->input('imageUrl')) {
            $proofImage = $request->input('hinhAnhHoanTra') ?? $request->input('imageUrl');
        }

        // Kiểm tra thông tin ngân hàng nếu đơn đã thanh toán
        $isPaid = (int)$order->trangThaiThanhToan === 1 || in_array((int)$order->phuongThucThanhToan, [1, 2]);
        if ($isPaid) {
            if (empty($soTaiKhoan) || empty($tenNguoiNhan)) {
                return response()->json([
                    'error' => 'Đơn hàng này đã thanh toán trước đó. Vui lòng cung cấp Số tài khoản và Tên chủ tài khoản ngân hàng để nhân viên chuyển khoản hoàn tiền!'
                ], 422);
            }
        }

        $oldStatus = (int) $order->trangThaiDonHang;
        $order->trangThaiDonHang = 8; // 8: YEU_CAU_HOAN_HANG (Chờ duyệt hoàn trả)
        $order->trangThaiHoanHang = 'CHO_DUYET';
        $order->lyDoHoanTra = $lyDo;
        $order->ghiChuHoanTra = $ghiChu;
        $order->ngayYeuCauHoanTra = now();

        if ($proofImage) {
            $order->hinhAnhHoanTra = $proofImage;
        }

        if ($nganHang) $order->nganHangHoanTien = $nganHang;
        if ($soTaiKhoan) $order->soTaiKhoanHoanTien = $soTaiKhoan;
        if ($tenNguoiNhan) $order->tenNguoiNhanHoanTien = mb_strtoupper($tenNguoiNhan, 'UTF-8');

        if ($isPaid) {
            $order->trangThaiThanhToan = 2; // 2: CHO_HOAN_TIEN
            $order->trangThaiHoanTien = 'CHO_HOAN_TIEN';
        }

        $order->save();

        // Ghi vết vào lịch sử trạng thái
        LichSuTrangThaiDonHang::create([
            'donHangId' => $order->id,
            'trangThaiCu' => $oldStatus,
            'trangThaiMoi' => 8,
            'ghnStatusCode' => 'return_requested',
            'moTaChiTiet' => "Khách hàng yêu cầu Trả hàng & Hoàn tiền. Lý do: {$lyDo}. Chi tiết: {$ghiChu}." . ($isPaid ? " Ngân hàng nhận: {$nganHang} - STK: {$soTaiKhoan} ({$order->tenNguoiNhanHoanTien})" : ""),
            'nguonCapNhat' => 2, // Khách hàng
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu Trả hàng & Hoàn tiền thành công! Cửa hàng sẽ kiểm tra và giải quyết trong vòng 24h làm việc.',
            'data' => $order->refresh()
        ]);
    }

    /**
     * Admin/Staff duyệt yêu cầu hoàn trả & hoàn tiền cho khách
     */
    public function adminApproveReturn(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $order = DonHang::where('maDonHang', $id)->orWhere('id', $id)->first();
        if (!$order) return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);

        $restock = filter_var($request->input('restock', false), FILTER_VALIDATE_BOOLEAN);
        $note = trim((string)($request->input('note') ?? $request->input('ghiChu') ?? 'Đã duyệt yêu cầu hoàn hàng và hoàn tiền'));
        $maGD = trim((string)($request->input('maGiaoDich') ?? $request->input('transactionCode') ?? ''));

        $oldStatus = (int) $order->trangThaiDonHang;
        $order->trangThaiDonHang = 6; // 6: HOAN_HANG (Đã hoàn tất trả hàng)
        $order->trangThaiHoanHang = 'DA_DUYET';
        $order->trangThaiThanhToan = 3; // 3: DA_HOAN_TIEN (Đã hoàn tiền)
        $order->trangThaiHoanTien = 'DA_HOAN_TIEN';
        $order->ngayHoanTien = now();
        $order->ghiChuHoanTien = $note;
        $order->soTienHoan = $order->tongThanhToan;
        if ($maGD) $order->maGiaoDichHoanTien = $maGD;

        if ($user && $user->nguoidung) {
            $order->nhanVienHoanTienId = $user->nguoidung->id;
        }

        // Nhập lại kho nếu bánh còn nguyên
        if ($restock) {
            foreach ($order->chitiet as $item) {
                $tonKho = TonKhoChiNhanh::where('cuaHangId', $order->cuaHangId)
                    ->where('sanPhamId', $item->sanPhamId)
                    ->first();
                if ($tonKho) {
                    $tonKho->increment('soLuongTon', $item->soLuong);
                }
            }
            $desc = "Duyệt trả hàng: Bánh còn nguyên -> Đã cộng trả lại kho. Ghi chú: {$note}";
        } else {
            $desc = "Duyệt trả hàng: Bánh bị hỏng hóc/tiêu hủy -> KHÔNG nhập lại kho. Ghi chú: {$note}";
        }

        $order->save();

        LichSuTrangThaiDonHang::create([
            'donHangId' => $order->id,
            'trangThaiCu' => $oldStatus,
            'trangThaiMoi' => 6,
            'ghnStatusCode' => 'return_approved',
            'moTaChiTiet' => $desc,
            'nguonCapNhat' => 1, // Nhân viên
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã duyệt yêu cầu hoàn trả và ghi nhận hoàn tiền thành công!',
            'data' => $order->refresh()
        ]);
    }

    /**
     * Admin/Staff từ chối yêu cầu trả hàng
     */
    public function adminRejectReturn(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $order = DonHang::where('maDonHang', $id)->orWhere('id', $id)->first();
        if (!$order) return response()->json(['error' => 'Không tìm thấy đơn hàng'], 404);

        $lyDoTuChoi = trim((string)($request->input('lyDoTuChoi') ?? $request->input('reason') ?? ''));
        if (empty($lyDoTuChoi)) {
            return response()->json(['error' => 'Vui lòng nhập lý do từ chối hoàn trả!'], 422);
        }

        $oldStatus = (int) $order->trangThaiDonHang;
        $order->trangThaiDonHang = 4; // Khôi phục lại trạng thái Đã giao hàng
        $order->trangThaiHoanHang = 'TU_CHOI';
        $order->lyDoTuChoiHoanTra = $lyDoTuChoi;

        if ($order->trangThaiThanhToan == 2) {
            $order->trangThaiThanhToan = 1; // Khôi phục trạng thái đã thanh toán
            $order->trangThaiHoanTien = 'TU_CHOI';
        }

        $order->save();

        LichSuTrangThaiDonHang::create([
            'donHangId' => $order->id,
            'trangThaiCu' => $oldStatus,
            'trangThaiMoi' => 4,
            'ghnStatusCode' => 'return_rejected',
            'moTaChiTiet' => "Từ chối yêu cầu trả hàng của khách. Lý do: {$lyDoTuChoi}",
            'nguonCapNhat' => 1, // Nhân viên
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã từ chối yêu cầu hoàn trả đơn hàng!',
            'data' => $order->refresh()
        ]);
    }
}
