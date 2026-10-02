<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SampleRevenueSeeder extends Seeder
{
    public function run()
    {
        $sanphams = DB::table('san_pham')->where('trang_thai', 1)->get();
        $nguoidungs = DB::table('nguoidung')->get();
        $cuahangs = DB::table('cuahang')->get();

        if ($sanphams->isEmpty() || $nguoidungs->isEmpty() || $cuahangs->isEmpty()) {
            return;
        }

        $now = Carbon::now();
        
        // Tạo khoảng 150 đơn hàng mẫu trong 1 năm qua
        for ($i = 1; $i <= 150; $i++) {
            $maDonHang = 'DH' . str_pad($i, 4, '0', STR_PAD_LEFT);
            
            $randomDays = rand(0, 365);
            $randomHours = rand(0, 23);
            $randomMinutes = rand(0, 59);
            
            $ngayLap = $now->copy()->subDays($randomDays)->setHour($randomHours)->setMinute($randomMinutes);
            
            $nguoidungId = $nguoidungs->random()->id;
            $cuahangId = $cuahangs->random()->id;
            
            $numItems = rand(1, 4);
            $tongTien = 0;
            
            $spMua = $sanphams->random($numItems);
            
            $donHangId = DB::table('don_hang')->insertGetId([
                'ma_don_hang' => $maDonHang,
                'nguoidung_id' => $nguoidungId,
                'cuahang_id' => $cuahangId,
                'loai_don_hang' => rand(0, 1), // 0: Online, 1: POS
                'tong_tien_hang' => 0, // Cập nhật sau
                'so_tien_giam' => 0,
                'phi_van_chuyen' => 15000,
                'tong_thanhtoan' => 0,
                'phuong_thuc_thanhtoan' => rand(0, 2), // 0: CASH, 1: VNPAY, 2: MOMO
                'trang_thai_thanhtoan' => 1, // 1: DA_THANH_TOAN
                'trang_thai_don_hang' => 5, // 5: DA_GIAO_THANH_CONG
                'created_at' => $ngayLap,
                'updated_at' => $ngayLap,
            ]);

            foreach ($spMua as $sp) {
                $soluong = rand(1, 5);
                $dongia = $sp->gia_ban;
                $thanhtien = $soluong * $dongia;
                $tongTien += $thanhtien;
                
                DB::table('chitiet_don_hang')->insert([
                    'donhang_id' => $donHangId,
                    'sanpham_id' => $sp->id,
                    'so_luong' => $soluong,
                    'don_gia' => $dongia,
                    'thanh_tien' => $thanhtien,
                    'created_at' => $ngayLap,
                    'updated_at' => $ngayLap,
                ]);
            }

            DB::table('don_hang')->where('id', $donHangId)->update([
                'tong_tien_hang' => $tongTien,
                'tong_thanhtoan' => $tongTien + 15000
            ]);
        }
    }
}
