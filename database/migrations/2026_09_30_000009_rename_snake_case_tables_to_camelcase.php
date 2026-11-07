<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations to rename snake_case tables to camelCase and rename their columns to camelCase.
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

        // 1. Rename tables
        foreach ($tableMap as $oldTable => $newTable) {
            if (Schema::hasTable($oldTable) && !Schema::hasTable($newTable)) {
                Schema::rename($oldTable, $newTable);
            }
        }

        // 2. Rename columns to camelCase
        $columnMap = [
            'donHang' => [
                'ma_don_hang' => 'maDonHang',
                'nguoidung_id' => 'nguoidungId',
                'cuahang_id' => 'cuaHangId',
                'voucher_id' => 'voucherId',
                'loai_don_hang' => 'loaiDonHang',
                'tong_tien_hang' => 'tongTienHang',
                'so_tien_giam' => 'soTienGiam',
                'phi_van_chuyen' => 'phiVanChuyen',
                'tong_thanhtoan' => 'tongThanhToan',
                'phuong_thuc_thanhtoan' => 'phuongThucThanhToan',
                'trang_thai_thanhtoan' => 'trangThaiThanhToan',
                'trang_thai_don_hang' => 'trangThaiDonHang',
                'ma_van_don_ghn' => 'maVanDonGhn',
                'ghn_status_code' => 'ghnStatusCode',
                'dia_chi_giao_hang' => 'diaChiGiaoHang',
                'ghi_chu' => 'ghiChu',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'chiTietDonHang' => [
                'donhang_id' => 'donHangId',
                'sanpham_id' => 'sanPhamId',
                'so_luong' => 'soLuong',
                'don_gia' => 'donGia',
                'thanh_tien' => 'thanhTien',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'sanPham' => [
                'loai_id' => 'loaiId',
                'ten_san_pham' => 'tenSanPham',
                'gia_ban' => 'giaBan',
                'hinh_anh' => 'hinhAnh',
                'mo_ta' => 'moTa',
                'don_vi_tinh' => 'donViTinh',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'lichSuGiaSanPham' => [
                'sanpham_id' => 'sanPhamId',
                'gia_cu' => 'giaCu',
                'gia_moi' => 'giaMoi',
                'phan_tram_giam' => 'phanTramGiam',
                'ly_do' => 'lyDo',
                'nguoidung_id' => 'nguoidungId',
                'ngay_ap_dung' => 'ngayApDung',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'loaiSanPham' => [
                'ten_loai' => 'tenLoai',
                'hinh_anh' => 'hinhAnh',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'tonKhoChiNhanh' => [
                'cuahang_id' => 'cuaHangId',
                'sanpham_id' => 'sanPhamId',
                'so_luong_ton' => 'soLuongTon',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'gioHang' => [
                'nguoidung_id' => 'nguoidungId',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'chiTietGioHang' => [
                'giohang_id' => 'gioHangId',
                'sanpham_id' => 'sanPhamId',
                'so_luong' => 'soLuong',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'lichSuTrangThaiDonHang' => [
                'donhang_id' => 'donHangId',
                'trang_thai_cu' => 'trangThaiCu',
                'trang_thai_moi' => 'trangThaiMoi',
                'ghn_status_code' => 'ghnStatusCode',
                'mo_ta_chi_tiet' => 'moTaChiTiet',
                'nguon_cap_nhat' => 'nguonCapNhat',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'posShifts' => [
                'cuahang_id' => 'cuaHangId',
                'nhanvien_id' => 'nhanVienId',
                'thoi_gian_mo_ca' => 'thoiGianMoCa',
                'thoi_gian_dong_ca' => 'thoiGianDongCa',
                'tien_mat_dau_ca' => 'tienMatDauCa',
                'tien_mat_sach' => 'tienMatSach',
                'tien_mat_thuc_te' => 'tienMatThucTe',
                'chenh_lech' => 'chenhLech',
                'doanh_thu_tien_mat' => 'doanhThuTienMat',
                'doanh_thu_chuyen_khoan' => 'doanhThuChuyenKhoan',
                'doanh_thu_momo' => 'doanhThuMomo',
                'tong_doanh_thu' => 'tongDoanhThu',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'hoaDonNhap' => [
                'ma_hoadon_nhap' => 'maHoaDonNhap',
                'nhacungcap_id' => 'nhaCungCapId',
                'cuahang_id' => 'cuaHangId',
                'nguoi_nhap_id' => 'nguoiNhapId',
                'ngay_nhap' => 'ngayNhap',
                'tong_tien' => 'tongTien',
                'ghi_chu' => 'ghiChu',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'chiTietHoaDonNhap' => [
                'hoadon_nhap_id' => 'hoaDonNhapId',
                'lohang_id' => 'loHangId',
                'sanpham_id' => 'sanPhamId',
                'so_luong' => 'soLuong',
                'don_gia_nhap' => 'donGiaNhap',
                'thanh_tien' => 'thanhTien',
            ],
            'loHang' => [
                'ma_lo_hang' => 'maLoHang',
                'sanpham_id' => 'sanPhamId',
                'cuahang_id' => 'cuaHangId',
                'nhacungcap_id' => 'nhaCungCapId',
                'so_luong_nhap' => 'soLuongNhap',
                'so_luong_con' => 'soLuongCon',
                'ngay_san_xuat' => 'ngaySanXuat',
                'gia_nhap' => 'giaNhap',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'chuKySo' => [
                'ma_chu_ky' => 'maChuKy',
                'loai_tai_lieu' => 'loaiTaiLieu',
                'tai_lieu_id' => 'taiLieuId',
                'vai_tro_ky' => 'vaiTroKy',
                'nguoi_ky_id' => 'nguoiKyId',
                'ten_nguoi_ky' => 'tenNguoiKy',
                'digital_signature_hash' => 'digitalSignatureHash',
                'public_key' => 'publicKey',
                'trang_thai' => 'trangThai',
                'ngay_ky' => 'ngayKy',
                'ghi_chu' => 'ghiChu',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'nhaCungCap' => [
                'ten_ncc' => 'tenNcc',
                'so_dien_thoai' => 'soDienThoai',
                'dia_chi' => 'diaChi',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'diaChi' => [
                'nguoidung_id' => 'nguoidungId',
                'ho_ten_nguoi_nhan' => 'hoTenNguoiNhan',
                'so_dien_thoai' => 'soDienThoai',
                'dia_chi_chi_tiet' => 'diaChiChiTiet',
                'ma_tinh' => 'maTinh',
                'ma_huyen' => 'maHuyen',
                'ma_xa' => 'maXa',
                'is_default' => 'isDefault',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'nguoidung' => [
                'taikhoan_id' => 'taiKhoanId',
                'cuahang_id' => 'cuaHangId',
                'ho_ten' => 'hoTen',
                'so_dien_thoai' => 'soDienThoai',
                'gioi_tinh' => 'gioiTinh',
                'ngay_sinh' => 'ngaySinh',
                'cccd_number' => 'cccdNumber',
                'cccd_image_url' => 'cccdImageUrl',
                'loai_nguoidung' => 'loaiNguoiDung',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'cuahang' => [
                'ten_cuahang' => 'tenCuaHang',
                'so_dien_thoai' => 'soDienThoai',
                'dia_chi_chi_tiet' => 'diaChiChiTiet',
                'ma_tinh' => 'maTinh',
                'ma_huyen' => 'maHuyen',
                'ma_xa' => 'maXa',
                'ghn_shop_id' => 'ghnShopId',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'taikhoan' => [
                'vaitro_id' => 'vaiTroId',
                'google_id' => 'googleId',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'vaitro' => [
                'ten_vaitro' => 'tenVaiTro',
                'mo_ta' => 'moTa',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'danhGiaSanPham' => [
                'donhang_id' => 'donHangId',
                'sanpham_id' => 'sanPhamId',
                'nguoidung_id' => 'nguoidungId',
                'so_sao' => 'soSao',
                'noi_dung' => 'noiDung',
                'hinh_anh_kem_theo' => 'hinhAnhKemTheo',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'yeuCauDieuChuyen' => [
                'cuahang_xuat_id' => 'cuaHangXuatId',
                'cuahang_nhap_id' => 'cuaHangNhapId',
                'nguoi_tao_id' => 'nguoiTaoId',
                'nguoi_duyet_id' => 'nguoiDuyetId',
                'trang_thai' => 'trangThai',
                'ghi_chu' => 'ghiChu',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'chiTietDieuChuyen' => [
                'yeucau_id' => 'yeuCauId',
                'sanpham_id' => 'sanPhamId',
                'so_luong_chuyen' => 'soLuongChuyen',
                'so_luong_thuc_nhan' => 'soLuongThucNhan',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'cuocTroChuyen' => [
                'khachhang_id' => 'khachHangId',
                'cskh_nhanvien_id' => 'cskhNhanVienId',
                'trang_thai_chat' => 'trangThaiChat',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'tinNhan' => [
                'cuoc_tro_chuyen_id' => 'cuocTroChuyenId',
                'nguoi_gui_type' => 'nguoiGuiType',
                'noi_dung' => 'noiDung',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'outboxEvents' => [
                'event_type' => 'eventType',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'auditLogs' => [
                'taikhoan_id' => 'taiKhoanId',
                'table_name' => 'tableName',
                'old_values' => 'oldValues',
                'new_values' => 'newValues',
                'ip_address' => 'ipAddress',
                'user_agent' => 'userAgent',
                'nguoidung_id' => 'nguoidungId',
                'thao_tac' => 'thaoTac',
                'bang_anh_huong' => 'bangAnhHuong',
                'ban_ghi_id' => 'banGhiId',
                'du_lieu_cu' => 'duLieuCu',
                'du_lieu_moi' => 'duLieuMoi',
                'dia_chi_ip' => 'diaChiIp',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'bienBanHuy' => [
                'ma_bien_ban' => 'maBienBan',
                'cuahang_id' => 'cuaHangId',
                'nguoi_lap_id' => 'nguoiLapId',
                'ngay_lap' => 'ngayLap',
                'ly_do' => 'lyDo',
                'tong_gia_tri_huy' => 'tongGiaTriHuy',
                'trang_thai' => 'trangThai',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
            'chiTietBienBanHuy' => [
                'bien_ban_huy_id' => 'bienBanHuyId',
                'lohang_id' => 'loHangId',
                'sanpham_id' => 'sanPhamId',
                'so_luong_huy' => 'soLuongHuy',
                'don_gia' => 'donGia',
                'thanh_tien' => 'thanhTien',
            ],
            'nhatKyKho' => [
                'cuahang_id' => 'cuaHangId',
                'sanpham_id' => 'sanPhamId',
                'loai_bien_dong' => 'loaiBienDong',
                'so_luong_thay_doi' => 'soLuongThayDoi',
                'so_luong_ton_sau' => 'soLuongTonSau',
                'ma_tham_chieu' => 'maThamChieu',
                'created_at' => 'createdAt',
                'updated_at' => 'updatedAt',
            ],
        ];

        foreach ($columnMap as $tableName => $cols) {
            $actualTable = Schema::hasTable($tableName) ? $tableName : (Schema::hasTable(strtolower($tableName)) ? strtolower($tableName) : null);
            if (!$actualTable) {
                continue;
            }

            foreach ($cols as $oldCol => $newCol) {
                if (Schema::hasColumn($actualTable, $oldCol) && !Schema::hasColumn($actualTable, $newCol)) {
                    try {
                        DB::statement("ALTER TABLE `{$actualTable}` RENAME COLUMN `{$oldCol}` TO `{$newCol}`");
                    } catch (\Throwable $e) {
                        // Safe fallback if column cannot be renamed
                    }
                }
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
