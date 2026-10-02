<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Vai trò (Roles)
        DB::table('vaitro')->insert([
            ['id' => 1, 'ten_vaitro' => 'ADMIN', 'mo_ta' => 'Quản trị hệ thống', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'ten_vaitro' => 'MANAGER', 'mo_ta' => 'Quản lý cửa hàng', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'ten_vaitro' => 'CASHIER', 'mo_ta' => 'Nhân viên bán hàng POS', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'ten_vaitro' => 'WAREHOUSE', 'mo_ta' => 'Nhân viên kho', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'ten_vaitro' => 'CSKH', 'mo_ta' => 'Nhân viên tư vấn CSKH', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'ten_vaitro' => 'CUSTOMER', 'mo_ta' => 'Khách hàng mua sắm', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // 2. Chi nhánh Cửa hàng mẫu
        $cuahangId = DB::table('cuahang')->insertGetId([
            'ten_cuahang' => 'Givral Nguyễn Trãi',
            'so_dien_thoai' => '02838351234',
            'dia_chi_chi_tiet' => '282 Nguyễn Trãi, Phường 8, Quận 5',
            'ma_tinh' => 202,
            'ma_huyen' => 1442,
            'ma_xa' => '20101',
            'ghn_shop_id' => 123456,
            'trang_thai' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Tài khoản Admin & Hồ sơ người dùng
        $adminTkId = DB::table('taikhoan')->insertGetId([
            'username' => 'admin',
            'email' => 'admin@givralbakery.com',
            'password' => Hash::make('123456'),
            'vaitro_id' => 1, // ADMIN
            'trang_thai' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('nguoidung')->insert([
            'taikhoan_id' => $adminTkId,
            'cuahang_id' => $cuahangId,
            'ho_ten' => 'Quản Trị Viên Givral',
            'so_dien_thoai' => '0909123456',
            'gioi_tinh' => 1, // 1: Nam
            'loai_nguoidung' => 1, // 1: Nhân viên
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 4. Danh mục Sản phẩm
        $categories = [
            ['id' => 1, 'ten_loai' => 'Bánh Kem', 'slug' => 'banh-kem', 'trang_thai' => 1],
            ['id' => 2, 'ten_loai' => 'Bánh Ngọt', 'slug' => 'banh-ngot', 'trang_thai' => 1],
            ['id' => 3, 'ten_loai' => 'Bánh Mì', 'slug' => 'banh-mi', 'trang_thai' => 1],
            ['id' => 4, 'ten_loai' => 'Bánh Khô', 'slug' => 'banh-kho', 'trang_thai' => 1],
            ['id' => 5, 'ten_loai' => 'Bánh Tráng Miệng', 'slug' => 'banh-trang-mieng', 'trang_thai' => 1],
            ['id' => 6, 'ten_loai' => 'Bánh Theo Mùa', 'slug' => 'banh-theo-mua', 'trang_thai' => 1],
        ];
        foreach ($categories as $cat) {
            DB::table('loai_san_pham')->insert(array_merge($cat, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 5. Sản phẩm mẫu
        $products = [
            ['ten_san_pham' => 'Bánh Cuộn Vani', 'slug' => 'banh-cuon-vani', 'loai_id' => 1, 'gia_ban' => 35000, 'don_vi_tinh' => 'Cái', 'hinh_anh' => 'productnew4.webp', 'mo_ta' => 'Bánh cuộn thơm ngon vị vani truyền thống Givral.'],
            ['ten_san_pham' => 'Bánh Donut Chocolate 45g', 'slug' => 'banh-donut-chocolate-45g', 'loai_id' => 4, 'gia_ban' => 20000, 'don_vi_tinh' => 'Cái', 'hinh_anh' => 'productnew7.webp', 'mo_ta' => 'Bột mỳ, sô cô la trắng, bột trà xanh.'],
            ['ten_san_pham' => 'Bánh Donut Dâu 45g', 'slug' => 'banh-donut-dau-45g', 'loai_id' => 4, 'gia_ban' => 15000, 'don_vi_tinh' => 'Cái', 'hinh_anh' => 'productSale_8.webp', 'mo_ta' => 'Bột mỳ, sô cô la dâu tươi.'],
            ['ten_san_pham' => 'Bánh Hoàng Kim', 'slug' => 'banh-hoang-kim', 'loai_id' => 2, 'gia_ban' => 40000, 'don_vi_tinh' => 'Cái', 'hinh_anh' => 'productSale_10.webp', 'mo_ta' => 'Bánh kem tươi cốt bánh 3 lớp vani.'],
            ['ten_san_pham' => 'Bánh Mousse Chocolate', 'slug' => 'banh-mousse-chocolate', 'loai_id' => 1, 'gia_ban' => 270000, 'don_vi_tinh' => 'Cái', 'hinh_anh' => 'productMenu_6.webp', 'mo_ta' => 'Bánh mousse socola béo ngậy.'],
            ['ten_san_pham' => 'Bánh Kem Princess', 'slug' => 'banh-kem-princess', 'loai_id' => 1, 'gia_ban' => 400000, 'don_vi_tinh' => 'Cái', 'hinh_anh' => 'productMenu_8.webp', 'mo_ta' => 'Bánh kem tươi 4 lớp chiffon trà bá tước.'],
        ];

        foreach ($products as $p) {
            $spId = DB::table('san_pham')->insertGetId(array_merge($p, [
                'trang_thai' => 1,
                'created_at' => $now,
                'updated_at' => $now
            ]));

            // Tạo kho hàng ban đầu tại chi nhánh
            DB::table('tonkho_chinhanh')->insert([
                'cuahang_id' => $cuahangId,
                'sanpham_id' => $spId,
                'so_luong_ton' => 50,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }

        // 6. Voucher Khuyến mãi mẫu
        DB::table('voucher')->insert([
            'ma_voucher' => 'GIVRAL2026',
            'loai_giam_gia' => 0, // 0: %
            'gia_tri_giam' => 10.0,
            'don_hang_toi_thieu' => 100000,
            'giam_toi_da' => 50000,
            'so_luong' => 100,
            'da_su_dung' => 0,
            'ngay_bat_dau' => $now,
            'ngay_ket_thuc' => $now->copy()->addMonths(6),
            'trang_thai' => 1,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // 7. Chạy Seeders mở rộng
        $this->call([
            CustomerSeeder::class,
            SupplierSeeder::class,
            BatchSeeder::class,
        ]);
    }
}

