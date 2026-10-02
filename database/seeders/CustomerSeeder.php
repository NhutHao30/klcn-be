<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        foreach ($customers as $c) {
            // Check nếu đã tồn tại thì bỏ qua
            $exists = DB::table('taikhoan')->where('username', $c['username'])->first();
            if (!$exists) {
                $tkId = DB::table('taikhoan')->insertGetId([
                    'username' => $c['username'],
                    'email' => $c['email'],
                    'password' => Hash::make('123456'),
                    'vaitro_id' => 6, // CUSTOMER
                    'trang_thai' => 1,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);

                DB::table('nguoidung')->insert([
                    'taikhoan_id' => $tkId,
                    'ho_ten' => $c['ho_ten'],
                    'so_dien_thoai' => $c['so_dien_thoai'],
                    'gioi_tinh' => $c['gioi_tinh'], // 0: Nữ, 1: Nam
                    'loai_nguoidung' => 0, // 0: Khách hàng
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }
        }
    }
}
