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
            'ma_hoadon_nhap' => 'HDN-GIVRAL-20260901',
            'nhacungcap_id' => $supplier->id,
            'cuahang_id' => $store->id,
            'nguoi_nhap_id' => $user->id,
            'ngay_nhap' => now()->subDays(10),
            'tong_tien' => 10500000,
            'ghi_chu' => 'Nhập lô bánh kem mousse & bánh nướng nguyên liệu đợt 1',
            'trang_thai' => 1, // Da_ky_du_va_nhap_kho
        ]);

        // Tạo 4 Chữ ký số đa bên cho Hóa đơn nhập
        $hdnRoles = [
            1 => 'Kế toán trưởng',
            2 => 'Nhân viên quản lý kho',
            3 => 'Chủ cửa hàng Givral Nguyễn Trãi',
            4 => $supplier->ten_ncc,
        ];

        foreach ($hdnRoles as $roleId => $roleName) {
            ChuKySo::create([
                'ma_chu_ky' => 'SIG-HDN-' . $roleId . '-' . strtoupper(Str::random(6)),
                'loai_tai_lieu' => 1, // HoaDonNhap
                'tai_lieu_id' => $hdNhap->id,
                'vai_tro_ky' => $roleId,
                'nguoi_ky_id' => $user->id,
                'ten_nguoi_ky' => $roleName,
                'digital_signature_hash' => hash('sha256', "GIVRAL_HDN_{$hdNhap->id}_ROLE_{$roleId}_VERIFIED_" . time()),
                'public_key' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzGIVRAL...\n-----END PUBLIC KEY-----',
                'trang_thai' => 1, // Da_ky
                'ngay_ky' => now()->subDays(10),
                'ghi_chu' => 'Đã ký xác nhận hóa đơn nhập hàng',
            ]);
        }

        // 2. Tạo các lô hàng (LoHang) & ChiTietHoaDonNhap
        $batch1 = LoHang::create([
            'ma_lo_hang' => 'LH-MOUSSE-202609A',
            'sanpham_id' => $products[0]->id,
            'cuahang_id' => $store->id,
            'nhacungcap_id' => $supplier->id,
            'so_luong_nhap' => 50,
            'so_luong_con' => 42,
            'ngay_san_xuat' => now()->subDays(5)->toDateString(),
            'hsd' => now()->addDays(25)->toDateString(),
            'gia_nhap' => 120000,
            'trang_thai' => 0, // Con_hang
        ]);
        ChiTietHoaDonNhap::create([
            'hoadon_nhap_id' => $hdNhap->id,
            'lohang_id' => $batch1->id,
            'sanpham_id' => $products[0]->id,
            'so_luong' => 50,
            'don_gia_nhap' => 120000,
            'thanh_tien' => 6000000,
        ]);

        if (isset($products[1])) {
            $batch2 = LoHang::create([
                'ma_lo_hang' => 'LH-TIRAMISU-202609B',
                'sanpham_id' => $products[1]->id,
                'cuahang_id' => $store->id,
                'nhacungcap_id' => $supplier->id,
                'so_luong_nhap' => 30,
                'so_luong_con' => 10,
                'ngay_san_xuat' => now()->subDays(7)->toDateString(),
                'hsd' => now()->addDays(2)->toDateString(),
                'gia_nhap' => 150000,
                'trang_thai' => 0,
            ]);
            ChiTietHoaDonNhap::create([
                'hoadon_nhap_id' => $hdNhap->id,
                'lohang_id' => $batch2->id,
                'sanpham_id' => $products[1]->id,
                'so_luong' => 30,
                'don_gia_nhap' => 150000,
                'thanh_tien' => 4500000,
            ]);
        }

        // 3. Tạo Lô hàng hết hạn & Biên bản hủy
        if (isset($products[2])) {
            $batchExpired = LoHang::create([
                'ma_lo_hang' => 'LH-EXPIRED-202608X',
                'sanpham_id' => $products[2]->id,
                'cuahang_id' => $store->id,
                'nhacungcap_id' => $supplier->id,
                'so_luong_nhap' => 20,
                'so_luong_con' => 0,
                'ngay_san_xuat' => now()->subDays(30)->toDateString(),
                'hsd' => now()->subDays(2)->toDateString(),
                'gia_nhap' => 200000,
                'trang_thai' => 3, // Da_huy
            ]);

            $bbHuy = BienBanHuy::create([
                'ma_bien_ban' => 'BBH-GIVRAL-20260918',
                'cuahang_id' => $store->id,
                'nguoi_lap_id' => $user->id,
                'ngay_lap' => now()->subDay(),
                'ly_do' => 'Hủy sản phẩm bánh ngọt quá hạn sử dụng (HSD)',
                'tong_gia_tri_huy' => 1000000,
                'trang_thai' => 1, // Da_ky_du_va_xac_nhan_huy
            ]);

            ChiTietBienBanHuy::create([
                'bien_ban_huy_id' => $bbHuy->id,
                'lohang_id' => $batchExpired->id,
                'sanpham_id' => $products[2]->id,
                'so_luong_huy' => 5,
                'don_gia' => 200000,
                'thanh_tien' => 1000000,
            ]);

            // Tạo 2 Chữ ký số đa bên cho Biên bản hủy
            $bbhRoles = [
                1 => 'Kế toán kiểm soát tiêu hủy',
                2 => 'Nhân viên quản lý kho tiêu hủy',
            ];

            foreach ($bbhRoles as $roleId => $roleName) {
                ChuKySo::create([
                    'ma_chu_ky' => 'SIG-HUY-' . $roleId . '-' . strtoupper(Str::random(6)),
                    'loai_tai_lieu' => 2, // BienBanHuy
                    'tai_lieu_id' => $bbHuy->id,
                    'vai_tro_ky' => $roleId,
                    'nguoi_ky_id' => $user->id,
                    'ten_nguoi_ky' => $roleName,
                    'digital_signature_hash' => hash('sha256', "GIVRAL_BBH_{$bbHuy->id}_ROLE_{$roleId}_VERIFIED_" . time()),
                    'public_key' => '-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzGIVRAL...\n-----END PUBLIC KEY-----',
                    'trang_thai' => 1, // Da_ky
                    'ngay_ky' => now()->subDay(),
                    'ghi_chu' => 'Đã ký phê duyệt hủy sản phẩm bánh hết hạn',
                ]);
            }
        }
    }
}
