<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $customers = [
            [
                'username' => 'khachhang_01',
                'email' => 'khachhang01@gmail.com',
                'ho_ten' => 'Nguyễn Văn An',
                'so_dien_thoai' => '0987654321',
                'gioi_tinh' => 1, // Nam
            ],
            [
                'username' => 'khachhang_02',
                'email' => 'khachhang02@gmail.com',
                'ho_ten' => 'Trần Thị Bình',
                'so_dien_thoai' => '0987654322',
                'gioi_tinh' => 0, // Nữ
            ]
        ];

        $tkTable = 'taikhoan';
        $ndTable = 'nguoidung';
        $vaiTroIdCol = Schema::hasColumn($tkTable, 'vaiTroId') ? 'vaiTroId' : 'vaitro_id';
        $tkTrangThaiCol = Schema::hasColumn($tkTable, 'trangThai') ? 'trangThai' : 'trang_thai';
        $tkCreatedCol = Schema::hasColumn($tkTable, 'createdAt') ? 'createdAt' : 'created_at';
        $tkUpdatedCol = Schema::hasColumn($tkTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        $taiKhoanIdCol = Schema::hasColumn($ndTable, 'taiKhoanId') ? 'taiKhoanId' : 'taikhoan_id';
        $hoTenCol = Schema::hasColumn($ndTable, 'hoTen') ? 'hoTen' : 'ho_ten';
        $sdtNdCol = Schema::hasColumn($ndTable, 'soDienThoai') ? 'soDienThoai' : 'so_dien_thoai';
        $gioiTinhCol = Schema::hasColumn($ndTable, 'gioiTinh') ? 'gioiTinh' : 'gioi_tinh';
        $loaiNdCol = Schema::hasColumn($ndTable, 'loaiNguoiDung') ? 'loaiNguoiDung' : 'loai_nguoidung';
        $ndCreatedCol = Schema::hasColumn($ndTable, 'createdAt') ? 'createdAt' : 'created_at';
        $ndUpdatedCol = Schema::hasColumn($ndTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

        foreach ($customers as $c) {
            $exists = DB::table($tkTable)->where('username', $c['username'])->first();
            if (!$exists) {
                $tkId = DB::table($tkTable)->insertGetId([
                    'username' => $c['username'],
                    'email' => $c['email'],
                    'password' => Hash::make('123456'),
                    $vaiTroIdCol => 6, // CUSTOMER
                    $tkTrangThaiCol => 1,
                    $tkCreatedCol => $now,
                    $tkUpdatedCol => $now
                ]);

                DB::table($ndTable)->insert([
                    $taiKhoanIdCol => $tkId,
                    $hoTenCol => $c['ho_ten'],
                    $sdtNdCol => $c['so_dien_thoai'],
                    $gioiTinhCol => $c['gioi_tinh'], // 0: Nữ, 1: Nam
                    $loaiNdCol => 0, // 0: Khách hàng
                    $ndCreatedCol => $now,
                    $ndUpdatedCol => $now
                ]);
            }
        }
    }
}
