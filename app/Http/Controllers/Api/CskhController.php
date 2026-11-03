<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\KhachHang;
use App\Models\NguoiDung;
use App\Models\Sanpham;
use App\Models\CuaHang;

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
        if (!$khachHang && is_string($identifier)) {
            $rawId = preg_replace('/[^0-9]/', '', $identifier);
            if ($rawId) {
                $khachHang = NguoiDung::with(['taikhoan'])->where('id', $rawId)->orWhere('taiKhoanId', $rawId)->first();
            }
        }

        // 3. Thử tìm theo họ tên hoặc username
        if (!$khachHang) {
            $khachHang = NguoiDung::with(['taikhoan'])
                ->where('hoTen', $identifier)
                ->orWhereHas('taikhoan', fn($q) => $q->where('username', $identifier))
                ->first();
        }

        // 4. Nếu vẫn không thấy, lấy khách hàng đầu tiên trong hệ thống để demo dữ liệu
        if (!$khachHang) {
            $khachHang = NguoiDung::with(['taikhoan'])->where('loaiNguoiDung', 0)->first();
        }

        if (!$khachHang) {
            return response()->json(['error' => 'Không tìm thấy thông tin khách hàng'], 404);
        }

        // Địa chỉ nhận hàng
        $diaChi = DB::table('diachi')
            ->where('nguoidungId', $khachHang->id)
            ->orderByDesc('isDefault')
            ->orderByDesc('id')
            ->first();

        // Đơn hàng gần đây (tối đa 5 đơn)
        $donHangs = DB::table('donhang')
            ->where('nguoidungId', $khachHang->id)
            ->orderByDesc('id')
            ->take(5)
            ->get();

        $tongChiTieu = DB::table('donhang')->where('nguoidungId', $khachHang->id)->sum('tongThanhToan');
        $tongDonHang = DB::table('donhang')->where('nguoidungId', $khachHang->id)->count();

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
        $viVoucher = DB::table('voucher_khachhang')
            ->join('voucher', 'voucher_khachhang.maVoucher', '=', 'voucher.MA_VOUCHER')
            ->where('voucher_khachhang.nguoidungId', $khachHang->id)
            ->select(
                'voucher.*',
                'voucher_khachhang.id as viVoucherId',
                'voucher_khachhang.trangThai as trangThaiVi',
                'voucher_khachhang.ngayThuThap'
            )
            ->get();

        // Tất cả Voucher hệ thống đang hoạt động
        $allVouchers = DB::table('voucher')
            ->where('TRANG_THAI', 1)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'khachhang' => [
                'id' => $khachHang->id,
                'MAKH' => 'KH_' . str_pad($khachHang->id, 5, '0', STR_PAD_LEFT),
                'HOTEN' => $khachHang->hoTen,
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
        $categories = DB::table('loaisanpham')
            ->where('trangThai', 1)
            ->select('id', 'tenLoai', 'slug')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($categories);
    }

    /**
     * Lấy danh sách sản phẩm phục vụ tra cứu & tư vấn gửi cho khách
     */
    public function getProducts(Request $request)
    {
        $search = $request->query('search');
        $loaiId = $request->query('loaiId') ?: $request->query('category');
        $query = DB::table('sanpham')->where('trangThai', 1);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tenSanPham', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($loaiId && $loaiId !== 'all') {
            $query->where('loaiId', $loaiId);
        }

        $products = $query->select(
            'id',
            'id as MASP',
            'loaiId',
            'tenSanPham as TENSP',
            'giaBan as GIABAN',
            'hinhAnh as HINHANH',
            'donViTinh',
            'moTa'
        )
        ->orderBy('id', 'asc')
        ->get();

        return response()->json($products);
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

        $stockInfo = DB::table('tonkhochinhanh')
            ->join('cuahang', 'tonkhochinhanh.cuaHangId', '=', 'cuahang.id')
            ->join('sanpham', 'tonkhochinhanh.sanPhamId', '=', 'sanpham.id')
            ->where('tonkhochinhanh.sanPhamId', $masp)
            ->select(
                'cuahang.id as cuaHangId',
                'cuahang.tenCuaHang as TENCUAHANG',
                'cuahang.diaChiChiTiet as DIACHI',
                'cuahang.soDienThoai as SDT',
                'tonkhochinhanh.soLuongTon as SOLUONG_TON',
                'sanpham.id as MASP',
                'sanpham.tenSanPham as TENSP',
                'sanpham.giaBan as GIABAN',
                'sanpham.hinhAnh as HINHANH'
            )
            ->orderByDesc('tonkhochinhanh.soLuongTon')
            ->get();

        return response()->json($stockInfo);
    }

    /**
     * Lọc danh sách cửa hàng gần khách hàng có sẵn bánh
     */
    public function findNearbyStores(Request $request)
    {
        $masp = $request->query('masp');
        $keyword = $request->query('keyword');

        $query = DB::table('cuahang')->where('cuahang.trangThai', 1);

        if ($masp) {
            $query->leftJoin('tonkhochinhanh', function ($join) use ($masp) {
                $join->on('cuahang.id', '=', 'tonkhochinhanh.cuaHangId')
                     ->where('tonkhochinhanh.sanPhamId', '=', $masp);
            })
            ->select(
                'cuahang.*',
                DB::raw('COALESCE(tonkhochinhanh.soLuongTon, 0) as soLuongTon')
            )
            ->orderByDesc('soLuongTon');
        }

        if ($keyword) {
            $query->where('cuahang.diaChiChiTiet', 'LIKE', '%' . $keyword . '%');
        }

        return response()->json($query->get());
    }

    /**
     * Lấy danh sách Voucher khuyến mãi đang mở
     */
    public function getVouchers()
    {
        $vouchers = DB::table('voucher')
            ->where('TRANG_THAI', 1)
            ->orderByDesc('GIA_TRI')
            ->get();

        return response()->json($vouchers);
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

        $exists = DB::table('voucher_khachhang')
            ->where('nguoidungId', $request->nguoidungId)
            ->where('maVoucher', $request->maVoucher)
            ->where('trangThai', 0)
            ->first();

        if ($exists) {
            return response()->json(['message' => 'Khách hàng đã có mã voucher này trong ví!']);
        }

        DB::table('voucher_khachhang')->insert([
            'nguoidungId' => $request->nguoidungId,
            'maVoucher' => $request->maVoucher,
            'ngayThuThap' => now(),
            'trangThai' => 0, // 0: chưa dùng
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Đã gửi tặng Voucher vào ví khách hàng thành công!']);
    }
}
