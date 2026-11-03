<?php

namespace Database\Seeders;

use App\Models\BienBanHuy;
use App\Models\ChiTietBienBanHuy;
use App\Models\ChiTietHoaDonNhap;
use App\Models\ChuKySo;
use App\Models\CuaHang;
use App\Models\HoaDonNhap;
use App\Models\LoHang;
use App\Models\NguoiDung;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class BatchSeeder extends Seeder
{
    public function run(): void
    {
        $store = CuaHang::first();
        $supplier = NhaCungCap::first();
        $user = NguoiDung::first();
        $products = SanPham::take(4)->get();

        if (!$store || !$supplier || !$user || $products->isEmpty()) {
            return;
        }

        // 1. Tạo Hóa đơn nhập (bắt đầu ở trạng thái 1: Da_ky_du_va_nhap_kho)
        $hdNhap = HoaDonNhap::create([
            'maHoaDonNhap' => 'HDN-GIVRAL-20260901',
            'nhaCungCapId' => $supplier->id,
            'cuaHangId' => $store->id,
            'nguoiNhapId' => $user->id,
            'ngayNhap' => now()->subDays(10),
            'tongTien' => 10500000,
            'ghiChu' => 'Nhập lô bánh kem mousse & bánh nướng nguyên liệu đợt 1',
            'trangThai' => 1, // Da_ky_du_va_nhap_kho
        ]);

        // Tạo 4 Chữ ký số đa bên cho Hóa đơn nhập
        $supplierName = $supplier->tenNcc ?? $supplier->ten_ncc ?? 'Nhà Cung Cấp Givral';
        $hdnRoles = [
            1 => 'Kế toán trưởng',
            2 => 'Nhân viên quản lý kho',
            3 => 'Chủ cửa hàng Givral Nguyễn Trãi',
            4 => $supplierName,
        ];

        foreach ($hdnRoles as $roleId => $roleName) {
            ChuKySo::create([
                'maChuKy' => 'SIG-HDN-' . $roleId . '-' . strtoupper(Str::random(6)),
                'loaiTaiLieu' => 1, // HoaDonNhap
                'taiLieuId' => $hdNhap->id,
                'vaiTroKy' => $roleId,
                'nguoiKyId' => $user->id,
                'tenNguoiKy' => $roleName,
                'digitalSignatureHash' => hash('sha256', "GIVRAL_HDN_{$hdNhap->id}_ROLE_{$roleId}_VERIFIED_" . time()),
                'publicKey' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzGIVRAL...\n-----END PUBLIC KEY-----',
                'trangThai' => 1, // Da_ky
                'ngayKy' => now()->subDays(10),
                'ghiChu' => 'Đã ký xác nhận hóa đơn nhập hàng',
            ]);
        }

        // 2. Tạo các lô hàng (LoHang) & ChiTietHoaDonNhap
        $batch1 = LoHang::create([
            'maLoHang' => 'LH-MOUSSE-202609A',
            'sanPhamId' => $products[0]->id,
            'cuaHangId' => $store->id,
            'nhaCungCapId' => $supplier->id,
            'soLuongNhap' => 50,
            'soLuongCon' => 42,
            'ngaySanXuat' => now()->subDays(5)->toDateString(),
            'hsd' => now()->addDays(25)->toDateString(),
            'giaNhap' => 120000,
            'trangThai' => 0, // Con_hang
        ]);
        ChiTietHoaDonNhap::create([
            'hoaDonNhapId' => $hdNhap->id,
            'loHangId' => $batch1->id,
            'sanPhamId' => $products[0]->id,
            'soLuong' => 50,
            'donGiaNhap' => 120000,
            'thanhTien' => 6000000,
        ]);

        if (isset($products[1])) {
            $batch2 = LoHang::create([
                'maLoHang' => 'LH-TIRAMISU-202609B',
                'sanPhamId' => $products[1]->id,
                'cuaHangId' => $store->id,
                'nhaCungCapId' => $supplier->id,
                'soLuongNhap' => 30,
                'soLuongCon' => 10,
                'ngaySanXuat' => now()->subDays(7)->toDateString(),
                'hsd' => now()->addDays(2)->toDateString(),
                'giaNhap' => 150000,
                'trangThai' => 0,
            ]);
            ChiTietHoaDonNhap::create([
                'hoaDonNhapId' => $hdNhap->id,
                'loHangId' => $batch2->id,
                'sanPhamId' => $products[1]->id,
                'soLuong' => 30,
                'donGiaNhap' => 150000,
                'thanhTien' => 4500000,
            ]);
        }

        // 3. Tạo Lô hàng hết hạn & Biên bản hủy
        if (isset($products[2])) {
            $batchExpired = LoHang::create([
                'maLoHang' => 'LH-EXPIRED-202608X',
                'sanPhamId' => $products[2]->id,
                'cuaHangId' => $store->id,
                'nhaCungCapId' => $supplier->id,
                'soLuongNhap' => 20,
                'soLuongCon' => 0,
                'ngaySanXuat' => now()->subDays(30)->toDateString(),
                'hsd' => now()->subDays(2)->toDateString(),
                'giaNhap' => 200000,
                'trangThai' => 3, // Da_huy
            ]);

            $bbTable = Schema::hasTable('bienBanHuy') ? 'bienBanHuy' : 'bien_ban_huy';
            $maBbCol = Schema::hasColumn($bbTable, 'maBienBan') ? 'maBienBan' : 'ma_bien_ban';
            $chCol = Schema::hasColumn($bbTable, 'cuaHangId') ? 'cuaHangId' : 'cuahang_id';
            $nlCol = Schema::hasColumn($bbTable, 'nguoiLapId') ? 'nguoiLapId' : 'nguoi_lap_id';
            $ngayLapCol = Schema::hasColumn($bbTable, 'ngayLap') ? 'ngayLap' : 'ngay_lap';
            $lyDoCol = Schema::hasColumn($bbTable, 'lyDo') ? 'lyDo' : 'ly_do';
            $tongGiaTriCol = Schema::hasColumn($bbTable, 'tongGiaTriHuy') ? 'tongGiaTriHuy' : 'tong_gia_tri_huy';
            $ttCol = Schema::hasColumn($bbTable, 'trangThai') ? 'trangThai' : 'trang_thai';

            $bbHuy = BienBanHuy::create([
                $maBbCol => 'BBH-GIVRAL-20260918',
                $chCol => $store->id,
                $nlCol => $user->id,
                $ngayLapCol => now()->subDay(),
                $lyDoCol => 'Hủy sản phẩm bánh ngọt quá hạn sử dụng (HSD)',
                $tongGiaTriCol => 1000000,
                $ttCol => 1, // Da_ky_du_va_xac_nhan_huy
            ]);

            $ctBbTable = Schema::hasTable('chiTietBienBanHuy') ? 'chiTietBienBanHuy' : 'chitiet_bien_ban_huy';
            $bbIdCol = Schema::hasColumn($ctBbTable, 'bienBanHuyId') ? 'bienBanHuyId' : 'bien_ban_huy_id';
            $lhIdCol = Schema::hasColumn($ctBbTable, 'loHangId') ? 'loHangId' : 'lohang_id';
            $spIdCol = Schema::hasColumn($ctBbTable, 'sanPhamId') ? 'sanPhamId' : 'sanpham_id';
            $slHuyCol = Schema::hasColumn($ctBbTable, 'soLuongHuy') ? 'soLuongHuy' : 'so_luong_huy';
            $dgCol = Schema::hasColumn($ctBbTable, 'donGia') ? 'donGia' : 'don_gia';
            $ttienCol = Schema::hasColumn($ctBbTable, 'thanhTien') ? 'thanhTien' : 'thanh_tien';

            ChiTietBienBanHuy::create([
                $bbIdCol => $bbHuy->id,
                $lhIdCol => $batchExpired->id,
                $spIdCol => $products[2]->id,
                $slHuyCol => 5,
                $dgCol => 200000,
                $ttienCol => 1000000,
            ]);

            // Tạo 2 Chữ ký số đa bên cho Biên bản hủy
            $bbhRoles = [
                1 => 'Kế toán kiểm soát tiêu hủy',
                2 => 'Nhân viên quản lý kho tiêu hủy',
            ];

            foreach ($bbhRoles as $roleId => $roleName) {
                ChuKySo::create([
                    'maChuKy' => 'SIG-HUY-' . $roleId . '-' . strtoupper(Str::random(6)),
                    'loaiTaiLieu' => 2, // BienBanHuy
                    'taiLieuId' => $bbHuy->id,
                    'vaiTroKy' => $roleId,
                    'nguoiKyId' => $user->id,
                    'tenNguoiKy' => $roleName,
                    'digitalSignatureHash' => hash('sha256', "GIVRAL_BBH_{$bbHuy->id}_ROLE_{$roleId}_VERIFIED_" . time()),
                    'publicKey' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzGIVRAL...\n-----END PUBLIC KEY-----',
                    'trangThai' => 1, // Da_ky
                    'ngayKy' => now()->subDay(),
                    'ghiChu' => 'Đã ký phê duyệt hủy sản phẩm bánh hết hạn',
                ]);
            }
        }
    }
}
