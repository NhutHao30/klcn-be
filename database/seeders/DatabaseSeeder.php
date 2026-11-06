<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Vai trò (Roles)
        $roleTable = Schema::hasTable('vaiTro') ? 'vaiTro' : 'vaitro';
        $tenCol = Schema::hasColumn($roleTable, 'tenVaiTro') ? 'tenVaiTro' : 'ten_vaitro';
        $moTaCol = Schema::hasColumn($roleTable, 'moTa') ? 'moTa' : 'mo_ta';
        $createdCol = Schema::hasColumn($roleTable, 'createdAt') ? 'createdAt' : 'created_at';
        $updatedCol = Schema::hasColumn($roleTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        $roles = [
            ['id' => 1, $tenCol => 'ADMIN', $moTaCol => 'Quản trị hệ thống', $createdCol => $now, $updatedCol => $now],
            ['id' => 2, $tenCol => 'MANAGER', $moTaCol => 'Quản lý cửa hàng', $createdCol => $now, $updatedCol => $now],
            ['id' => 3, $tenCol => 'CASHIER', $moTaCol => 'Nhân viên bán hàng POS', $createdCol => $now, $updatedCol => $now],
            ['id' => 4, $tenCol => 'WAREHOUSE', $moTaCol => 'Nhân viên kho', $createdCol => $now, $updatedCol => $now],
            ['id' => 5, $tenCol => 'CSKH', $moTaCol => 'Nhân viên tư vấn CSKH', $createdCol => $now, $updatedCol => $now],
            ['id' => 6, $tenCol => 'CUSTOMER', $moTaCol => 'Khách hàng mua sắm', $createdCol => $now, $updatedCol => $now],
            ['id' => 7, $tenCol => 'ACCOUNTANT', $moTaCol => 'Nhân viên kế toán', $createdCol => $now, $updatedCol => $now],
            ['id' => 8, $tenCol => 'DIRECTOR', $moTaCol => 'Ban Giám đốc', $createdCol => $now, $updatedCol => $now],
        ];
        foreach ($roles as $r) {
            $exists = DB::table($roleTable)->where('id', $r['id'])->first();
            if (!$exists) {
                DB::table($roleTable)->insert($r);
            }
        }

        // 2. Chi nhánh Cửa hàng mẫu
        $chTable = Schema::hasTable('cuaHang') ? 'cuaHang' : 'cuahang';
        $tenChCol = Schema::hasColumn($chTable, 'tenCuaHang') ? 'tenCuaHang' : 'ten_cuahang';
        $sdtChCol = Schema::hasColumn($chTable, 'soDienThoai') ? 'soDienThoai' : 'so_dien_thoai';
        $diaChiChCol = Schema::hasColumn($chTable, 'diaChiChiTiet') ? 'diaChiChiTiet' : 'dia_chi_chi_tiet';
        $maTinhCol = Schema::hasColumn($chTable, 'maTinh') ? 'maTinh' : 'ma_tinh';
        $maHuyenCol = Schema::hasColumn($chTable, 'maHuyen') ? 'maHuyen' : 'ma_huyen';
        $maXaCol = Schema::hasColumn($chTable, 'maXa') ? 'maXa' : 'ma_xa';
        $ghnShopIdCol = Schema::hasColumn($chTable, 'ghnShopId') ? 'ghnShopId' : 'ghn_shop_id';
        $chTrangThaiCol = Schema::hasColumn($chTable, 'trangThai') ? 'trangThai' : 'trang_thai';
        $chCreatedCol = Schema::hasColumn($chTable, 'createdAt') ? 'createdAt' : 'created_at';
        $chUpdatedCol = Schema::hasColumn($chTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        $existingCh = DB::table($chTable)->first();
        if ($existingCh) {
            $cuahangId = $existingCh->id;
        } else {
            $cuahangId = DB::table($chTable)->insertGetId([
                $tenChCol => 'Givral Nguyễn Trãi',
                $sdtChCol => '02838351234',
                $diaChiChCol => '282 Nguyễn Trãi, Phường 8, Quận 5',
                $maTinhCol => 202,
                $maHuyenCol => 1442,
                $maXaCol => '20101',
                $ghnShopIdCol => 123456,
                $chTrangThaiCol => 1,
                $chCreatedCol => $now,
                $chUpdatedCol => $now,
            ]);
        }

        // 3. Tài khoản Admin & Hồ sơ người dùng
        $tkTable = 'taikhoan';
        $vaiTroIdCol = Schema::hasColumn($tkTable, 'vaiTroId') ? 'vaiTroId' : 'vaitro_id';
        $tkTrangThaiCol = Schema::hasColumn($tkTable, 'trangThai') ? 'trangThai' : 'trang_thai';
        $tkCreatedCol = Schema::hasColumn($tkTable, 'createdAt') ? 'createdAt' : 'created_at';
        $tkUpdatedCol = Schema::hasColumn($tkTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        $adminUser = DB::table($tkTable)->where('username', 'admin')->first();
        if ($adminUser) {
            $adminTkId = $adminUser->id;
        } else {
            $adminTkId = DB::table($tkTable)->insertGetId([
                'username' => 'admin',
                'email' => 'admin@givralbakery.com',
                'password' => Hash::make('123456'),
                $vaiTroIdCol => 1, // ADMIN
                $tkTrangThaiCol => 1,
                $tkCreatedCol => $now,
                $tkUpdatedCol => $now,
            ]);
        }

        $ndTable = 'nguoidung';
        $taiKhoanIdCol = Schema::hasColumn($ndTable, 'taiKhoanId') ? 'taiKhoanId' : 'taikhoan_id';
        $cuaHangIdCol = Schema::hasColumn($ndTable, 'cuaHangId') ? 'cuaHangId' : 'cuahang_id';
        $hoTenCol = Schema::hasColumn($ndTable, 'hoTen') ? 'hoTen' : 'ho_ten';
        $sdtNdCol = Schema::hasColumn($ndTable, 'soDienThoai') ? 'soDienThoai' : 'so_dien_thoai';
        $gioiTinhCol = Schema::hasColumn($ndTable, 'gioiTinh') ? 'gioiTinh' : 'gioi_tinh';
        $loaiNdCol = Schema::hasColumn($ndTable, 'loaiNguoiDung') ? 'loaiNguoiDung' : 'loai_nguoidung';
        $ndCreatedCol = Schema::hasColumn($ndTable, 'createdAt') ? 'createdAt' : 'created_at';
        $ndUpdatedCol = Schema::hasColumn($ndTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        $adminProfile = DB::table($ndTable)->where($taiKhoanIdCol, $adminTkId)->first();
        if (!$adminProfile) {
            DB::table($ndTable)->insert([
                $taiKhoanIdCol => $adminTkId,
                $cuaHangIdCol => $cuahangId,
                $hoTenCol => 'Quản Trị Viên Givral',
                $sdtNdCol => '0909123456',
                $gioiTinhCol => 1, // 1: Nam
                $loaiNdCol => 1, // 1: Nhân viên
                $ndCreatedCol => $now,
                $ndUpdatedCol => $now,
            ]);
        }

        // 4. Danh mục Sản phẩm
        $catTable = Schema::hasTable('loaiSanPham') ? 'loaiSanPham' : 'loai_san_pham';
        $tenLoaiCol = Schema::hasColumn($catTable, 'tenLoai') ? 'tenLoai' : 'ten_loai';
        $catTrangThaiCol = Schema::hasColumn($catTable, 'trangThai') ? 'trangThai' : 'trang_thai';
        $catCreatedCol = Schema::hasColumn($catTable, 'createdAt') ? 'createdAt' : 'created_at';
        $catUpdatedCol = Schema::hasColumn($catTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        $categories = [
            ['id' => 1, $tenLoaiCol => 'Bánh Kem', 'slug' => 'banh-kem', $catTrangThaiCol => 1],
            ['id' => 2, $tenLoaiCol => 'Bánh Ngọt', 'slug' => 'banh-ngot', $catTrangThaiCol => 1],
            ['id' => 3, $tenLoaiCol => 'Bánh Mì', 'slug' => 'banh-mi', $catTrangThaiCol => 1],
            ['id' => 4, $tenLoaiCol => 'Bánh Khô', 'slug' => 'banh-kho', $catTrangThaiCol => 1],
            ['id' => 5, $tenLoaiCol => 'Bánh Tráng Miệng', 'slug' => 'banh-trang-mieng', $catTrangThaiCol => 1],
            ['id' => 6, $tenLoaiCol => 'Bánh Theo Mùa', 'slug' => 'banh-theo-mua', $catTrangThaiCol => 1],
        ];
        foreach ($categories as $cat) {
            $exists = DB::table($catTable)->where('id', $cat['id'])->first();
            if (!$exists) {
                DB::table($catTable)->insert(array_merge($cat, [$catCreatedCol => $now, $catUpdatedCol => $now]));
            }
        }

        // 5. Sản phẩm mẫu
        $spTable = Schema::hasTable('sanPham') ? 'sanPham' : 'san_pham';
        $tenSpCol = Schema::hasColumn($spTable, 'tenSanPham') ? 'tenSanPham' : 'ten_san_pham';
        $loaiIdCol = Schema::hasColumn($spTable, 'loaiId') ? 'loaiId' : 'loai_id';
        $giaBanCol = Schema::hasColumn($spTable, 'giaBan') ? 'giaBan' : 'gia_ban';
        $donViTinhCol = Schema::hasColumn($spTable, 'donViTinh') ? 'donViTinh' : 'don_vi_tinh';
        $hinhAnhCol = Schema::hasColumn($spTable, 'hinhAnh') ? 'hinhAnh' : 'hinh_anh';
        $moTaCol = Schema::hasColumn($spTable, 'moTa') ? 'moTa' : 'mo_ta';
        $spTrangThaiCol = Schema::hasColumn($spTable, 'trangThai') ? 'trangThai' : 'trang_thai';
        $spCreatedCol = Schema::hasColumn($spTable, 'createdAt') ? 'createdAt' : 'created_at';
        $spUpdatedCol = Schema::hasColumn($spTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        $stockTable = Schema::hasTable('tonKhoChiNhanh') ? 'tonKhoChiNhanh' : 'tonkho_chinhanh';
        $stockChCol = Schema::hasColumn($stockTable, 'cuaHangId') ? 'cuaHangId' : 'cuahang_id';
        $stockSpCol = Schema::hasColumn($stockTable, 'sanPhamId') ? 'sanPhamId' : 'sanpham_id';
        $stockQtyCol = Schema::hasColumn($stockTable, 'soLuongTon') ? 'soLuongTon' : 'so_luong_ton';
        $stockCreatedCol = Schema::hasColumn($stockTable, 'createdAt') ? 'createdAt' : 'created_at';
        $stockUpdatedCol = Schema::hasColumn($stockTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        $products = [
            [$tenSpCol => 'Bánh Cuộn Vani', 'slug' => 'banh-cuon-vani', $loaiIdCol => 1, $giaBanCol => 35000, $donViTinhCol => 'Cái', $hinhAnhCol => 'productnew4.webp', $moTaCol => 'Bánh cuộn thơm ngon vị vani truyền thống Givral.'],
            [$tenSpCol => 'Bánh Donut Chocolate 45g', 'slug' => 'banh-donut-chocolate-45g', $loaiIdCol => 4, $giaBanCol => 20000, $donViTinhCol => 'Cái', $hinhAnhCol => 'productnew7.webp', $moTaCol => 'Bột mỳ, sô cô la trắng, bột trà xanh.'],
            [$tenSpCol => 'Bánh Donut Dâu 45g', 'slug' => 'banh-donut-dau-45g', $loaiIdCol => 4, $giaBanCol => 15000, $donViTinhCol => 'Cái', $hinhAnhCol => 'productSale_8.webp', $moTaCol => 'Bột mỳ, sô cô la dâu tươi.'],
            [$tenSpCol => 'Bánh Hoàng Kim', 'slug' => 'banh-hoang-kim', $loaiIdCol => 2, $giaBanCol => 40000, $donViTinhCol => 'Cái', $hinhAnhCol => 'productSale_10.webp', $moTaCol => 'Bánh kem tươi cốt bánh 3 lớp vani.'],
            [$tenSpCol => 'Bánh Mousse Chocolate', 'slug' => 'banh-mousse-chocolate', $loaiIdCol => 1, $giaBanCol => 270000, $donViTinhCol => 'Cái', $hinhAnhCol => 'productMenu_6.webp', $moTaCol => 'Bánh mousse socola béo ngậy.'],
            [$tenSpCol => 'Bánh Kem Princess', 'slug' => 'banh-kem-princess', $loaiIdCol => 1, $giaBanCol => 40000, $donViTinhCol => 'Cái', $hinhAnhCol => 'productMenu_8.webp', $moTaCol => 'Bánh kem tươi 4 lớp chiffon trà bá tước.'],
        ];

        foreach ($products as $p) {
            $existingSp = DB::table($spTable)->where('slug', $p['slug'])->first();
            if ($existingSp) {
                $spId = $existingSp->id;
            } else {
                $spId = DB::table($spTable)->insertGetId(array_merge($p, [
                    $spTrangThaiCol => 1,
                    $spCreatedCol => $now,
                    $spUpdatedCol => $now
                ]));
            }

            // Tạo kho hàng ban đầu tại chi nhánh
            $existingStock = DB::table($stockTable)->where($stockChCol, $cuahangId)->where($stockSpCol, $spId)->first();
            if (!$existingStock) {
                DB::table($stockTable)->insert([
                    $stockChCol => $cuahangId,
                    $stockSpCol => $spId,
                    $stockQtyCol => 50,
                    $stockCreatedCol => $now,
                    $stockUpdatedCol => $now
                ]);
            }
        }

        // 6. Voucher Khuyến mãi mẫu
        if (Schema::hasTable('voucher')) {
            $maVoucherCol = Schema::hasColumn('voucher', 'MA_VOUCHER') ? 'MA_VOUCHER' : 'ma_voucher';
            $existsVoucher = DB::table('voucher')->where($maVoucherCol, 'GIVRAL2026')->first();
            if (!$existsVoucher) {
                $vData = [
                    $maVoucherCol => 'GIVRAL2026',
                ];
                if (Schema::hasColumn('voucher', 'TEN_VOUCHER')) {
                    $vData['TEN_VOUCHER'] = 'Ưu đãi Givral Bakery 2026';
                }
                if (Schema::hasColumn('voucher', 'LOAI_GIAM')) {
                    $vData['LOAI_GIAM'] = 'PHAN_TRAM';
                } elseif (Schema::hasColumn('voucher', 'loai_giam_gia')) {
                    $vData['loai_giam_gia'] = 0;
                }
                if (Schema::hasColumn('voucher', 'GIA_TRI')) {
                    $vData['GIA_TRI'] = 10;
                } elseif (Schema::hasColumn('voucher', 'gia_tri_giam')) {
                    $vData['gia_tri_giam'] = 10.0;
                }
                if (Schema::hasColumn('voucher', 'DON_TOI_THIEU')) {
                    $vData['DON_TOI_THIEU'] = 100000;
                } elseif (Schema::hasColumn('voucher', 'don_hang_toi_thieu')) {
                    $vData['don_hang_toi_thieu'] = 100000;
                }
                if (Schema::hasColumn('voucher', 'GIAM_TOI_DA')) {
                    $vData['GIAM_TOI_DA'] = 50000;
                } elseif (Schema::hasColumn('voucher', 'giam_toi_da')) {
                    $vData['giam_toi_da'] = 50000;
                }
                if (Schema::hasColumn('voucher', 'PHAN_TRAM_GIAM')) {
                    $vData['PHAN_TRAM_GIAM'] = 10;
                }
                if (Schema::hasColumn('voucher', 'SO_LUONG_GIOI_HAN')) {
                    $vData['SO_LUONG_GIOI_HAN'] = 100;
                } elseif (Schema::hasColumn('voucher', 'so_luong')) {
                    $vData['so_luong'] = 100;
                }
                if (Schema::hasColumn('voucher', 'SO_LUONG_DA_DUNG')) {
                    $vData['SO_LUONG_DA_DUNG'] = 0;
                } elseif (Schema::hasColumn('voucher', 'da_su_dung')) {
                    $vData['da_su_dung'] = 0;
                }
                if (Schema::hasColumn('voucher', 'NGAY_BAT_DAU')) {
                    $vData['NGAY_BAT_DAU'] = $now;
                } elseif (Schema::hasColumn('voucher', 'ngay_bat_dau')) {
                    $vData['ngay_bat_dau'] = $now;
                }
                if (Schema::hasColumn('voucher', 'NGAY_KET_THUC')) {
                    $vData['NGAY_KET_THUC'] = $now->copy()->addMonths(6);
                } elseif (Schema::hasColumn('voucher', 'ngay_ket_thuc')) {
                    $vData['ngay_ket_thuc'] = $now->copy()->addMonths(6);
                }
                if (Schema::hasColumn('voucher', 'TRANG_THAI')) {
                    $vData['TRANG_THAI'] = 1;
                } elseif (Schema::hasColumn('voucher', 'trang_thai')) {
                    $vData['trang_thai'] = 1;
                }
                if (Schema::hasColumn('voucher', 'createdAt')) {
                    $vData['createdAt'] = $now;
                } elseif (Schema::hasColumn('voucher', 'created_at')) {
                    $vData['created_at'] = $now;
                }
                if (Schema::hasColumn('voucher', 'updatedAt')) {
                    $vData['updatedAt'] = $now;
                } elseif (Schema::hasColumn('voucher', 'updated_at')) {
                    $vData['updated_at'] = $now;
                }

                DB::table('voucher')->insert($vData);
            }
        }

        // 7. Chạy Seeders mở rộng
        $this->call([
            CustomerSeeder::class,
            SupplierSeeder::class,
            BatchSeeder::class,
        ]);
    }
}
