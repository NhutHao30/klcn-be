<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SampleRevenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Xóa dữ liệu cũ nếu muốn (tuỳ chọn)
        // DB::table('chitiethdban')->truncate();
        // DB::table('hdban')->truncate();

        // Lấy danh sách sản phẩm, khách hàng, cửa hàng hiện có
        $sanphams = DB::table('sanpham')->whereNull('deleted_at')->get();
        $khachhangs = DB::table('khachhang')->get();
        $cuahangs = DB::table('cuahang')->get();

        if ($sanphams->isEmpty() || $khachhangs->isEmpty() || $cuahangs->isEmpty()) {
            $this->command->warn('Vui lòng đảm bảo đã có Sản phẩm, Khách hàng và Cửa hàng trong DB trước khi chạy Seeder.');
            return;
        }

        $now = Carbon::now();
        $hoadonData = [];
        $chitietData = [];
        
        // Tạo khoảng 150 hóa đơn ngẫu nhiên trong 1 năm qua
        for ($i = 1; $i <= 150; $i++) {
            $mahd = 'HD' . str_pad($i, 3, '0', STR_PAD_LEFT) . rand(100, 999);
            
            // Random lùi ngày từ 0 đến 365 ngày trước, thêm random giờ để test biểu đồ phân bổ thời gian
            $randomDays = rand(0, 365);
            $randomHours = rand(0, 23);
            $randomMinutes = rand(0, 59);
            
            $ngayLap = $now->copy()->subDays($randomDays)->setHour($randomHours)->setMinute($randomMinutes);
            
            $makh = $khachhangs->random()->MAKH;
            $macuahang = $cuahangs->random()->MACUAHANG;
            
            // Random số lượng sản phẩm mua trong 1 hóa đơn (từ 1 đến 4 loại)
            $numItems = rand(1, 4);
            $tongTien = 0;
            
            // Lấy ngẫu nhiên $numItems sản phẩm
            $spMua = $sanphams->random($numItems);
            
            foreach ($spMua as $sp) {
                $soluong = rand(1, 5);
                $dongia = $sp->GIABAN;
                $thanhtien = $soluong * $dongia;
                $tongTien += $thanhtien;
                
                $chitietData[] = [
                    'MAHD' => $mahd,
                    'MASP' => $sp->MASP,
                    'SOLUONG' => $soluong,
                    'DONGIA' => $dongia,
                    'created_at' => $ngayLap,
                    'updated_at' => $ngayLap,
                ];
            }
            
            $hoadonData[] = [
                'MAHD' => $mahd,
                'NGAYLAP' => $ngayLap,
                'MAKH' => $makh,
                'TONGTIEN' => $tongTien,
                'PHUONGTHUCTHANHTOAN' => rand(0, 1) ? 'Tiền mặt' : 'Chuyển khoản',
                'TRANGTHAITHANHTOAN' => 'Đã thanh toán', // Bắt buộc phải 'Đã thanh toán' thì mới tính doanh thu
                'MACUAHANG' => $macuahang,
                'created_at' => $ngayLap,
                'updated_at' => $ngayLap,
            ];
        }

        // Chèn vào database thành từng batch để tránh lỗi insert quá lớn
        foreach (array_chunk($hoadonData, 50) as $chunk) {
            DB::table('hdban')->insert($chunk);
        }
        
        foreach (array_chunk($chitietData, 50) as $chunk) {
            DB::table('chitiethdban')->insert($chunk);
        }

        $this->command->info('Đã tạo thành công 150 hóa đơn mẫu và chi tiết kèm theo.');
    }
}
