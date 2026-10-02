<?php

namespace Database\Seeders;

use App\Models\NhaCungCap;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'ten_ncc' => 'Công ty TNHH Bột Mỳ & Nguyên Liệu Givral Food',
                'so_dien_thoai' => '02838445566',
                'email' => 'contact@givralfood.vn',
                'dia_chi' => '120 Trần Hưng Đạo, Quận 1, TP. Hồ Chí Minh',
                'trang_thai' => 1,
            ],
            [
                'ten_ncc' => 'Nhà Phân Phối Kem & Bơ Nhập Khẩu Anchor New Zealand',
                'so_dien_thoai' => '02839998877',
                'email' => 'sales@anchor-vietnam.com',
                'dia_chi' => '45 Nguyễn Thị Minh Khai, Quận 3, TP. Hồ Chí Minh',
                'trang_thai' => 1,
            ],
            [
                'ten_ncc' => 'Công ty Cổ phần Sữa & Socola Puratos Grand-Place',
                'so_dien_thoai' => '02743789101',
                'email' => 'info@puratosgrandplace.com',
                'dia_chi' => 'KCN Việt Nam - Singapore, Bình Dương',
                'trang_thai' => 1,
            ],
        ];

        foreach ($suppliers as $data) {
            NhaCungCap::updateOrCreate(['ten_ncc' => $data['ten_ncc']], $data);
        }
    }
}
