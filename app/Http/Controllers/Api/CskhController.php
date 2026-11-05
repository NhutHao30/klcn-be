<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\NguoiDung;
use App\Models\SanPham;
use App\Models\LoaiSanPham;
use App\Models\CuaHang;
use App\Models\TonKhoChiNhanh;
use App\Models\DiaChi;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;
use App\Models\Voucher;
use App\Models\VoucherKhachHang;

class CskhController extends Controller
{
    /**
     * Tra cứu thông tin chi tiết khách hàng, địa chỉ, đơn hàng gần đây và ví voucher
     */
    public function getCustomerInfo($identifier)
    {
        $khachHang = null;

        // 1. Thử tìm theo ID số nguyên (nguoidung.id hoặc taiKhoanId)
        if (is_numeric($identifier)) {
            $khachHang = NguoiDung::with(['taikhoan'])->where('id', $identifier)->orWhere('taiKhoanId', $identifier)->first();
        }

        // 2. Thử tìm theo mã chuỗi KH_0000x hoặc KH-x
        if (!$khachHang && is_string($identifier) && preg_match('/^KH[-_]?([0-9]+)$/i', trim($identifier), $matches)) {
            $khachHang = NguoiDung::with(['taikhoan'])->where('id', $matches[1])->orWhere('taiKhoanId', $matches[1])->first();
        }

        // 3. Thử tìm theo họ tên hoặc username/email/sdt
        if (!$khachHang && is_string($identifier)) {
            $trimmed = trim($identifier);
            $khachHang = NguoiDung::with(['taikhoan'])
                ->where('hoTen', $trimmed)
                ->orWhere('soDienThoai', $trimmed)
                ->orWhereHas('taikhoan', function ($q) use ($trimmed) {
                    $q->where('username', $trimmed)->orWhere('email', $trimmed)->orWhere('soDienThoai', $trimmed);
                })
                ->first();
        }

        // 4. Thử tìm theo tên tương đối LIKE
        if (!$khachHang && is_string($identifier)) {
            $trimmed = trim($identifier);
            $khachHang = NguoiDung::with(['taikhoan'])
                ->where('hoTen', 'LIKE', "%{$trimmed}%")
                ->first();
        }

        // 5. Nếu vẫn không thấy, lấy khách hàng đầu tiên trong hệ thống
        if (!$khachHang) {
            $khachHang = NguoiDung::with(['taikhoan'])->where('loaiNguoiDung', 0)->first();
        }

        if (!$khachHang) {
            return response()->json(['error' => 'Không tìm thấy thông tin khách hàng'], 404);
        }

        // Địa chỉ nhận hàng
        $diaChi = null;
        try {
            $diaChi = DiaChi::where('nguoidungId', $khachHang->id)
                ->orderByDesc('isDefault')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {}

        // Đơn hàng gần đây (tối đa 5 đơn)
        $donHangs = collect();
        $tongChiTieu = 0;
        $tongDonHang = 0;
        try {
            $donHangs = DonHang::where('nguoidungId', $khachHang->id)
                ->orderByDesc('id')
                ->take(5)
                ->get();
            $tongChiTieu = DonHang::where('nguoidungId', $khachHang->id)->sum('tongThanhToan') ?: 0;
            $tongDonHang = DonHang::where('nguoidungId', $khachHang->id)->count() ?: 0;
        } catch (\Throwable $e) {}

        // Hạng thành viên
        $hangThanhVien = 'Đồng (Bronze)';
        if ($tongChiTieu >= 5000000) {
            $hangThanhVien = 'Kim Cương (Diamond)';
        } elseif ($tongChiTieu >= 2000000) {
            $hangThanhVien = 'Vàng (Gold)';
        } elseif ($tongChiTieu >= 500000) {
            $hangThanhVien = 'Bạc (Silver)';
        }

        // Ví Voucher của khách hàng
        $viVoucher = [];
        try {
            $viVoucher = VoucherKhachHang::with('voucher')
                ->where('nguoidungId', $khachHang->id)
                ->get()
                ->map(function ($vk) {
                    $v = $vk->voucher;
                    return [
                        'viVoucherId' => $vk->id,
                        'maVoucher' => $vk->maVoucher,
                        'MA_VOUCHER' => $vk->maVoucher,
                        'TEN_VOUCHER' => $v?->TEN_VOUCHER ?? $vk->maVoucher,
                        'LOAI_GIAM' => $v?->LOAI_GIAM ?? 'TIEN_MAT',
                        'GIA_TRI' => $v?->GIA_TRI ?? 0,
                        'DON_TOI_THIEU' => $v?->DON_TOI_THIEU ?? 0,
                        'trangThaiVi' => $vk->trangThai,
                        'ngayThuThap' => $vk->ngayThuThap,
                    ];
                });
        } catch (\Throwable $e) {}

        // Tất cả Voucher hệ thống đang hoạt động
        $allVouchers = [];
        try {
            $allVouchers = Voucher::where('TRANG_THAI', 1)
                ->orderByDesc('created_at')
                ->get();
        } catch (\Throwable $e) {}

        return response()->json([
            'khachhang' => [
                'id' => $khachHang->id,
                'MAKH' => 'KH_' . str_pad($khachHang->id, 5, '0', STR_PAD_LEFT),
                'HOTEN' => $khachHang->hoTen ?: ($khachHang->taikhoan?->username ?: 'Khách hàng'),
                'SDT' => $khachHang->soDienThoai ?: ($khachHang->taikhoan?->soDienThoai ?: 'Chưa cập nhật'),
                'EMAIL' => $khachHang->taikhoan?->email ?: 'Chưa cập nhật',
                'DIACHI' => $diaChi ? $diaChi->diaChiChiTiet : 'Chưa lưu địa chỉ giao hàng',
                'DIEMTICHLUY' => $khachHang->diemTichLuy ?? round($tongChiTieu / 10000),
                'HANGTHANHVIEN' => $hangThanhVien,
                'tongChiTieu' => (float)$tongChiTieu,
                'tongDonHang' => (int)$tongDonHang,
            ],
            'diachi' => $diaChi,
            'donhangs' => $donHangs,
            'viVoucher' => $viVoucher,
            'allVouchers' => $allVouchers,
        ]);
    }

    /**
     * Lấy danh sách danh mục sản phẩm (loại sản phẩm)
     */
    public function getCategories()
    {
        try {
            $categories = LoaiSanPham::where('trangThai', 1)
                ->select('id', 'tenLoai', 'slug')
                ->orderBy('id', 'asc')
                ->get();

            return response()->json($categories);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    /**
     * Lấy danh sách sản phẩm phục vụ tra cứu & tư vấn gửi cho khách
     */
    public function getProducts(Request $request)
    {
        try {
            $search = $request->query('search');
            $loaiId = $request->query('loaiId') ?: $request->query('category');
            $query = SanPham::where('trangThai', 1);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tenSanPham', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            }

            if ($loaiId && $loaiId !== 'all') {
                $query->where('loaiId', $loaiId);
            }

            $rawProducts = $query->orderBy('id', 'asc')->get();

            $products = $rawProducts->map(function($sp) {
                $name = $sp->tenSanPham ?: ($sp->ten_san_pham ?: ($sp->TENSP ?: ($sp->name ?: 'Bánh Givral')));
                $price = (float)($sp->giaBan ?: ($sp->gia_ban ?: ($sp->GIABAN ?: 0)));
                $img = $sp->hinhAnh ?: ($sp->hinh_anh ?: ($sp->HINHANH ?: ''));
                return [
                    'id' => $sp->id,
                    'MASP' => 'SP' . str_pad($sp->id, 3, '0', STR_PAD_LEFT),
                    'loaiId' => $sp->loaiId ?? $sp->loai_id,
                    'tenSanPham' => $name,
                    'TENSP' => $name,
                    'name' => $name,
                    'giaBan' => $price,
                    'GIABAN' => $price,
                    'price' => $price,
                    'hinhAnh' => $img,
                    'HINHANH' => $img,
                    'image' => $img,
                    'donViTinh' => $sp->donViTinh ?? $sp->don_vi_tinh ?? 'Cái',
                    'moTa' => $sp->moTa ?? $sp->mo_ta ?? '',
                ];
            });

            return response()->json($products);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi CskhController getProducts: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Tra cứu tồn kho của một sản phẩm trên tất cả các chi nhánh
     */
    public function checkProductStock(Request $request)
    {
        $masp = $request->query('masp') ?: $request->query('sanPhamId');
        if (!$masp) {
            return response()->json([]);
        }

        $cleanId = is_numeric($masp) ? (int)$masp : (int)preg_replace('/[^0-9]/', '', $masp);

        try {
            $stockInfo = TonKhoChiNhanh::with(['cuahang', 'sanpham'])
                ->where('sanPhamId', $cleanId)
                ->get()
                ->map(function ($item) {
                    return [
                        'cuaHangId' => $item->cuaHangId,
                        'TENCUAHANG' => $item->cuahang?->tenCuaHang ?? ('Chi nhánh #' . $item->cuaHangId),
                        'DIACHI' => $item->cuahang?->diaChiChiTiet ?? $item->cuahang?->diaChi ?? '',
                        'SDT' => $item->cuahang?->soDienThoai ?? '',
                        'SOLUONG_TON' => (int)$item->soLuongTon,
                        'MASP' => $item->sanPhamId,
                        'TENSP' => $item->sanpham?->tenSanPham ?? '',
                        'GIABAN' => $item->sanpham?->giaBan ?? 0,
                        'HINHANH' => $item->sanpham?->hinhAnh ?? '',
                    ];
                });

            return response()->json($stockInfo);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    /**
     * Lọc danh sách cửa hàng gần khách hàng có sẵn bánh
     */
    public function findNearbyStores(Request $request)
    {
        try {
            $masp = $request->query('masp');
            $keyword = $request->query('keyword');

            $query = CuaHang::where('trangThai', 1);

            if ($keyword) {
                $query->where('diaChiChiTiet', 'LIKE', '%' . $keyword . '%');
            }

            $stores = $query->get()->map(function ($store) use ($masp) {
                $stock = 0;
                if ($masp) {
                    $cleanId = is_numeric($masp) ? (int)$masp : (int)preg_replace('/[^0-9]/', '', $masp);
                    $stock = (int)(TonKhoChiNhanh::where('cuaHangId', $store->id)
                        ->where('sanPhamId', $cleanId)
                        ->value('soLuongTon') ?? 0);
                }
                $store->soLuongTon = $stock;
                return $store;
            });

            return response()->json($stores);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    /**
     * Lấy danh sách Voucher khuyến mãi đang mở
     */
    public function getVouchers()
    {
        try {
            $vouchers = Voucher::where('TRANG_THAI', 1)
                ->orderByDesc('GIA_TRI')
                ->get();

            return response()->json($vouchers);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    /**
     * Tặng Voucher trực tiếp vào ví của khách hàng
     */
    public function giftVoucherToCustomer(Request $request)
    {
        $request->validate([
            'nguoidungId' => 'required',
            'maVoucher' => 'required|string',
        ]);

        try {
            $exists = VoucherKhachHang::where('nguoidungId', $request->nguoidungId)
                ->where('maVoucher', $request->maVoucher)
                ->where('trangThai', 0)
                ->first();

            if ($exists) {
                return response()->json(['message' => 'Khách hàng đã có mã voucher này trong ví!']);
            }

            VoucherKhachHang::create([
                'nguoidungId' => $request->nguoidungId,
                'maVoucher' => $request->maVoucher,
                'ngayThuThap' => now(),
                'trangThai' => 0, // 0: chưa dùng
            ]);

            return response()->json(['message' => 'Đã gửi tặng Voucher vào ví khách hàng thành công!']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Không thể gửi tặng Voucher: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Đặt hàng giùm khách hàng trực tiếp từ khung chat CSKH
     */
    public function placeOrderForCustomer($identifier, Request $request)
    {
        $khachHang = null;
        if (is_numeric($identifier)) {
            $khachHang = NguoiDung::where('id', $identifier)->orWhere('taiKhoanId', $identifier)->first();
        }
        if (!$khachHang && is_string($identifier) && preg_match('/^KH[-_]?([0-9]+)$/i', trim($identifier), $matches)) {
            $khachHang = NguoiDung::where('id', $matches[1])->orWhere('taiKhoanId', $matches[1])->first();
        }
        if (!$khachHang && is_string($identifier)) {
            $trimmed = trim($identifier);
            $khachHang = NguoiDung::where('hoTen', $trimmed)
                ->orWhere('soDienThoai', $trimmed)
                ->first();
        }
        if (!$khachHang) {
            $khachHang = NguoiDung::where('loaiNguoiDung', 0)->first();
        }

        if (!$khachHang) {
            return response()->json(['error' => 'Không tìm thấy hồ sơ khách hàng để tạo đơn.'], 404);
        }

        $items = $request->input('items', []);
        if (empty($items) || !is_array($items)) {
            return response()->json(['error' => 'Danh sách sản phẩm không được rỗng.'], 422);
        }

        $cuaHangId = $request->input('MACUAHANG') ?: $request->input('cuaHangId');
        if (!$cuaHangId) {
            $firstStore = CuaHang::where('trangThai', 1)->first();
            $cuaHangId = $firstStore?->id ?? 1;
        }

        $diaChiGiao = $request->input('DIACHIGIAO') ?: $request->input('diaChiGiaoHang');
        if (!$diaChiGiao) {
            return response()->json(['error' => 'Vui lòng cung cấp địa chỉ giao hàng.'], 422);
        }

        $shippingFee = (float)($request->input('shippingFee') ?: $request->input('phiVanChuyen') ?: 0);

        try {
            return DB::transaction(function () use ($khachHang, $cuaHangId, $diaChiGiao, $shippingFee, $items) {
                $maDonHang = 'HD' . date('ymd') . rand(1000, 9999);
                $tongTienHang = 0;
                $orderItemsData = [];

                foreach ($items as $item) {
                    $rawId = $item['MASP'] ?? ($item['sanPhamId'] ?? ($item['id'] ?? null));
                    $cleanId = is_numeric($rawId) ? (int)$rawId : (int)preg_replace('/[^0-9]/', '', (string)$rawId);
                    $quantity = (int)($item['SOLUONG'] ?? ($item['soLuong'] ?? 1));
                    if ($quantity <= 0) $quantity = 1;

                    $product = SanPham::find($cleanId);
                    if (!$product) {
                        continue;
                    }

                    $donGia = (float)$product->giaBan;
                    $thanhTien = $donGia * $quantity;
                    $tongTienHang += $thanhTien;

                    $orderItemsData[] = [
                        'sanPhamId' => $product->id,
                        'soLuong' => $quantity,
                        'donGia' => $donGia,
                        'thanhTien' => $thanhTien,
                    ];
                }

                if (empty($orderItemsData)) {
                    throw new \Exception('Không tìm thấy thông tin sản phẩm hợp lệ trong đơn.');
                }

                $tongThanhToan = $tongTienHang + $shippingFee;

                $donHang = DonHang::create([
                    'maDonHang' => $maDonHang,
                    'nguoidungId' => $khachHang->id,
                    'cuaHangId' => $cuaHangId,
                    'voucherId' => null,
                    'maVoucher' => null,
                    'loaiDonHang' => 0, // Online / CSKH hỗ trợ
                    'tongTienHang' => $tongTienHang,
                    'soTienGiam' => 0,
                    'phiVanChuyen' => $shippingFee,
                    'tongThanhToan' => $tongThanhToan,
                    'phuongThucThanhToan' => 0, // COD
                    'trangThaiThanhToan' => 0, // Chưa thanh toán
                    'trangThaiDonHang' => 0, // Chờ xác nhận
                    'diaChiGiaoHang' => $diaChiGiao,
                    'ghiChu' => 'Đơn hàng do CSKH hỗ trợ đặt qua khung chat hỗ trợ trực tuyến',
                ]);

                foreach ($orderItemsData as $itemData) {
                    ChiTietDonHang::create([
                        'donHangId' => $donHang->id,
                        'sanPhamId' => $itemData['sanPhamId'],
                        'soLuong' => $itemData['soLuong'],
                        'donGia' => $itemData['donGia'],
                        'thanhTien' => $itemData['thanhTien'],
                    ]);

                    // Trừ tồn kho chi nhánh nếu có
                    try {
                        $tonKho = TonKhoChiNhanh::where('cuaHangId', $cuaHangId)
                            ->where('sanPhamId', $itemData['sanPhamId'])
                            ->first();
                        if ($tonKho && $tonKho->soLuongTon >= $itemData['soLuong']) {
                            $tonKho->decrement('soLuongTon', $itemData['soLuong']);
                        }
                    } catch (\Throwable $e) {}
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Tạo đơn hàng giùm khách thành công!',
                    'mahd' => $maDonHang,
                    'donHang' => $donHang->load('chitiet'),
                ], 201);
            });
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Lỗi khi tạo đơn hàng: ' . $e->getMessage()], 500);
        }
    }
}
