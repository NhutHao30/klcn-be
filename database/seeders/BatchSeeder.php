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
            'ma_hoadon_nhap' => 'HDN-GIVRAL-20260901',
            'nhaCungCapId' => $supplier->id,
            'nhacungcap_id' => $supplier->id,
            'cuaHangId' => $store->id,
            'cuahang_id' => $store->id,
            'nguoiNhapId' => $user->id,
            'nguoi_nhap_id' => $user->id,
            'ngayNhap' => now()->subDays(10),
            'ngay_nhap' => now()->subDays(10),
            'tongTien' => 10500000,
            'tong_tien' => 10500000,
            'ghiChu' => 'Nhập lô bánh kem mousse & bánh nướng nguyên liệu đợt 1',
            'ghi_chu' => 'Nhập lô bánh kem mousse & bánh nướng nguyên liệu đợt 1',
            'trangThai' => 1, // Da_ky_du_va_nhap_kho
            'trang_thai' => 1,
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
                'ma_chu_ky' => 'SIG-HDN-' . $roleId . '-' . strtoupper(Str::random(6)),
                'loaiTaiLieu' => 1, // HoaDonNhap
                'loai_tai_lieu' => 1,
                'taiLieuId' => $hdNhap->id,
                'tai_lieu_id' => $hdNhap->id,
                'vaiTroKy' => $roleId,
                'vai_tro_ky' => $roleId,
                'nguoiKyId' => $user->id,
                'nguoi_ky_id' => $user->id,
                'tenNguoiKy' => $roleName,
                'ten_nguoi_ky' => $roleName,
                'digitalSignatureHash' => hash('sha256', "GIVRAL_HDN_{$hdNhap->id}_ROLE_{$roleId}_VERIFIED_" . time()),
                'digital_signature_hash' => hash('sha256', "GIVRAL_HDN_{$hdNhap->id}_ROLE_{$roleId}_VERIFIED_" . time()),
                'publicKey' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzGIVRAL...\n-----END PUBLIC KEY-----',
                'public_key' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzGIVRAL...\n-----END PUBLIC KEY-----',
                'trangThai' => 1, // Da_ky
                'trang_thai' => 1,
                'ngayKy' => now()->subDays(10),
                'ngay_ky' => now()->subDays(10),
                'ghiChu' => 'Đã ký xác nhận hóa đơn nhập hàng',
                'ghi_chu' => 'Đã ký xác nhận hóa đơn nhập hàng',
            ]);
        }

        // 2. Tạo các lô hàng (LoHang) & ChiTietHoaDonNhap
        $batch1 = LoHang::create([
            'maLoHang' => 'LH-MOUSSE-202609A',
            'ma_lo_hang' => 'LH-MOUSSE-202609A',
            'sanPhamId' => $products[0]->id,
            'sanpham_id' => $products[0]->id,
            'cuaHangId' => $store->id,
            'cuahang_id' => $store->id,
            'nhaCungCapId' => $supplier->id,
            'nhacungcap_id' => $supplier->id,
            'soLuongNhap' => 50,
            'so_luong_nhap' => 50,
            'soLuongCon' => 42,
            'so_luong_con' => 42,
            'ngaySanXuat' => now()->subDays(5)->toDateString(),
            'ngay_san_xuat' => now()->subDays(5)->toDateString(),
            'hsd' => now()->addDays(25)->toDateString(),
            'giaNhap' => 120000,
            'gia_nhap' => 120000,
            'trangThai' => 0, // Con_hang
            'trang_thai' => 0,
        ]);
        ChiTietHoaDonNhap::create([
            'hoaDonNhapId' => $hdNhap->id,
            'hoadon_nhap_id' => $hdNhap->id,
            'loHangId' => $batch1->id,
            'lohang_id' => $batch1->id,
            'sanPhamId' => $products[0]->id,
            'sanpham_id' => $products[0]->id,
            'soLuong' => 50,
            'so_luong' => 50,
            'donGiaNhap' => 120000,
            'don_gia_nhap' => 120000,
            'thanhTien' => 6000000,
            'thanh_tien' => 6000000,
        ]);

        if (isset($products[1])) {
            $batch2 = LoHang::create([
                'maLoHang' => 'LH-TIRAMISU-202609B',
                'ma_lo_hang' => 'LH-TIRAMISU-202609B',
                'sanPhamId' => $products[1]->id,
                'sanpham_id' => $products[1]->id,
                'cuaHangId' => $store->id,
                'cuahang_id' => $store->id,
                'nhaCungCapId' => $supplier->id,
                'nhacungcap_id' => $supplier->id,
                'soLuongNhap' => 30,
                'so_luong_nhap' => 30,
                'soLuongCon' => 10,
                'so_luong_con' => 10,
                'ngaySanXuat' => now()->subDays(7)->toDateString(),
                'ngay_san_xuat' => now()->subDays(7)->toDateString(),
                'hsd' => now()->addDays(2)->toDateString(),
                'giaNhap' => 150000,
                'gia_nhap' => 150000,
                'trangThai' => 0,
                'trang_thai' => 0,
            ]);
            ChiTietHoaDonNhap::create([
                'hoaDonNhapId' => $hdNhap->id,
                'hoadon_nhap_id' => $hdNhap->id,
                'loHangId' => $batch2->id,
                'lohang_id' => $batch2->id,
                'sanPhamId' => $products[1]->id,
                'sanpham_id' => $products[1]->id,
                'soLuong' => 30,
                'so_luong' => 30,
                'donGiaNhap' => 150000,
                'don_gia_nhap' => 150000,
                'thanhTien' => 4500000,
                'thanh_tien' => 4500000,
            ]);
        }

        // 3. Tạo Lô hàng hết hạn & Biên bản hủy
        if (isset($products[2])) {
            $batchExpired = LoHang::create([
                'maLoHang' => 'LH-EXPIRED-202608X',
                'ma_lo_hang' => 'LH-EXPIRED-202608X',
                'sanPhamId' => $products[2]->id,
                'sanpham_id' => $products[2]->id,
                'cuaHangId' => $store->id,
                'cuahang_id' => $store->id,
                'nhaCungCapId' => $supplier->id,
                'nhacungcap_id' => $supplier->id,
                'soLuongNhap' => 20,
                'so_luong_nhap' => 20,
                'soLuongCon' => 0,
                'so_luong_con' => 0,
                'ngaySanXuat' => now()->subDays(30)->toDateString(),
                'ngay_san_xuat' => now()->subDays(30)->toDateString(),
                'hsd' => now()->subDays(2)->toDateString(),
                'giaNhap' => 200000,
                'gia_nhap' => 200000,
                'trangThai' => 3, // Da_huy
                'trang_thai' => 3,
            ]);

            $bbHuy = BienBanHuy::create([
                'maBienBan' => 'BBH-GIVRAL-20260918',
                'ma_bien_ban' => 'BBH-GIVRAL-20260918',
                'cuaHangId' => $store->id,
                'cuahang_id' => $store->id,
                'nguoiLapId' => $user->id,
                'nguoi_lap_id' => $user->id,
                'ngayLap' => now()->subDay(),
                'ngay_lap' => now()->subDay(),
                'lyDo' => 'Hủy sản phẩm bánh ngọt quá hạn sử dụng (HSD)',
                'ly_do' => 'Hủy sản phẩm bánh ngọt quá hạn sử dụng (HSD)',
                'tongGiaTriHuy' => 1000000,
                'tong_gia_tri_huy' => 1000000,
                'trangThai' => 1, // Da_ky_du_va_xac_nhan_huy
                'trang_thai' => 1,
            ]);

            ChiTietBienBanHuy::create([
                'bienBanHuyId' => $bbHuy->id,
                'bien_ban_huy_id' => $bbHuy->id,
                'loHangId' => $batchExpired->id,
                'lohang_id' => $batchExpired->id,
                'sanPhamId' => $products[2]->id,
                'sanpham_id' => $products[2]->id,
                'soLuongHuy' => 5,
                'so_luong_huy' => 5,
                'giaVon' => 200000,
                'gia_von' => 200000,
                'thanhTien' => 1000000,
                'thanh_tien' => 1000000,
            ]);

            // Tạo 2 Chữ ký số đa bên cho Biên bản hủy
            $bbhRoles = [
                1 => 'Kế toán kiểm soát tiêu hủy',
                2 => 'Nhân viên quản lý kho tiêu hủy',
            ];

            foreach ($bbhRoles as $roleId => $roleName) {
                ChuKySo::create([
                    'maChuKy' => 'SIG-HUY-' . $roleId . '-' . strtoupper(Str::random(6)),
                    'ma_chu_ky' => 'SIG-HUY-' . $roleId . '-' . strtoupper(Str::random(6)),
                    'loaiTaiLieu' => 2, // BienBanHuy
                    'loai_tai_lieu' => 2,
                    'taiLieuId' => $bbHuy->id,
                    'tai_lieu_id' => $bbHuy->id,
                    'vaiTroKy' => $roleId,
                    'vai_tro_ky' => $roleId,
                    'nguoiKyId' => $user->id,
                    'nguoi_ky_id' => $user->id,
                    'tenNguoiKy' => $roleName,
                    'ten_nguoi_ky' => $roleName,
                    'digitalSignatureHash' => hash('sha256', "GIVRAL_BBH_{$bbHuy->id}_ROLE_{$roleId}_VERIFIED_" . time()),
                    'digital_signature_hash' => hash('sha256', "GIVRAL_BBH_{$bbHuy->id}_ROLE_{$roleId}_VERIFIED_" . time()),
                    'publicKey' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzGIVRAL...\n-----END PUBLIC KEY-----',
                    'public_key' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzGIVRAL...\n-----END PUBLIC KEY-----',
                    'trangThai' => 1, // Da_ky
                    'trang_thai' => 1,
                    'ngayKy' => now()->subDay(),
                    'ngay_ky' => now()->subDay(),
                    'ghiChu' => 'Đã ký phê duyệt hủy sản phẩm bánh hết hạn',
                    'ghi_chu' => 'Đã ký phê duyệt hủy sản phẩm bánh hết hạn',
                ]);
            }
        }
    }
}
