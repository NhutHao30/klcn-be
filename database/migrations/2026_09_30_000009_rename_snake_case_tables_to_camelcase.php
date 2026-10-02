<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations to rename snake_case tables to camelCase.
     */
    public function up(): void
    {
        $tableMap = [
            'audit_logs'                  => 'auditLogs',
            'bien_ban_huy'                => 'bienBanHuy',
            'cham_cong'                   => 'chamCong',
            'chitiet_bien_ban_huy'        => 'chiTietBienBanHuy',
            'chitiet_dieuchuyen'          => 'chiTietDieuChuyen',
            'chitiet_don_hang'            => 'chiTietDonHang',
            'chitiet_gio_hang'            => 'chiTietGioHang',
            'chitiet_hoadon_nhap'         => 'chiTietHoaDonNhap',
            'chu_ky_so'                   => 'chuKySo',
            'cuoc_tro_chuyen'             => 'cuocTroChuyen',
            'danhgia_sanpham'             => 'danhGiaSanPham',
            'dia_chi'                     => 'diaChi',
            'don_hang'                    => 'donHang',
            'gio_hang'                    => 'gioHang',
            'giohang'                     => 'gioHang',
            'chitietgiohang'              => 'chiTietGioHang',
            'hoadon_nhap'                 => 'hoaDonNhap',
            'idempotency_keys'            => 'idempotencyKeys',
            'lich_su_gia_san_pham'        => 'lichSuGiaSanPham',
            'lich_su_trang_thai_don_hang' => 'lichSuTrangThaiDonHang',
            'lo_hang'                     => 'loHang',
            'loai_san_pham'               => 'loaiSanPham',
            'nha_cung_cap'                => 'nhaCungCap',
            'nhatky_kho'                  => 'nhatKyKho',
            'outbox_events'               => 'outboxEvents',
            'pos_shifts'                  => 'posShifts',
            'san_pham'                    => 'sanPham',
            'tin_nhan'                    => 'tinNhan',
            'tonkho_chinhanh'             => 'tonKhoChiNhanh',
            'yeucau_dieuchuyen'           => 'yeuCauDieuChuyen',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($tableMap as $oldTable => $newTable) {
            if (Schema::hasTable($oldTable) && !Schema::hasTable($newTable)) {
                Schema::rename($oldTable, $newTable);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableMap = [
            'auditLogs'               => 'audit_logs',
            'bienBanHuy'             => 'bien_ban_huy',
            'chiTietDonHang'         => 'chitiet_don_hang',
            'chiTietGioHang'         => 'chitiet_gio_hang',
            'donHang'                => 'don_hang',
            'gioHang'                => 'gio_hang',
            'sanPham'                => 'san_pham',
            'loaiSanPham'            => 'loai_san_pham',
            'tonKhoChiNhanh'         => 'tonkho_chinhanh',
            'diaChi'                 => 'dia_chi',
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($tableMap as $newTable => $oldTable) {
            if (Schema::hasTable($newTable) && !Schema::hasTable($oldTable)) {
                Schema::rename($newTable, $oldTable);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
