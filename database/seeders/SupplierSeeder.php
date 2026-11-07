<?php

namespace Database\Seeders;

use App\Models\NhaCungCap;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'ten' => 'Công ty TNHH Bột Mỳ & Nguyên Liệu Givral Food',
                'sdt' => '02838445566',
                'email' => 'contact@givralfood.vn',
                'diaChi' => '120 Trần Hưng Đạo, Quận 1, TP. Hồ Chí Minh',
                'trangThai' => 1,
            ],
            [
                'ten' => 'Nhà Phân Phối Kem & Bơ Nhập Khẩu Anchor New Zealand',
                'sdt' => '02839998877',
                'email' => 'sales@anchor-vietnam.com',
                'diaChi' => '45 Nguyễn Thị Minh Khai, Quận 3, TP. Hồ Chí Minh',
                'trangThai' => 1,
            ],
            [
                'ten' => 'Công ty Cổ phần Sữa & Socola Puratos Grand-Place',
                'sdt' => '02743789101',
                'email' => 'info@puratosgrandplace.com',
                'diaChi' => 'KCN Việt Nam - Singapore, Bình Dương',
                'trangThai' => 1,
            ],
        ];

        $table = Schema::hasTable('nhaCungCap') ? 'nhaCungCap' : (Schema::hasTable('nha_cung_cap') ? 'nha_cung_cap' : null);
        if (!$table) {
            return;
        }

        $tenCol = Schema::hasColumn($table, 'tenNcc') ? 'tenNcc' : 'ten_ncc';
        $sdtCol = Schema::hasColumn($table, 'soDienThoai') ? 'soDienThoai' : 'so_dien_thoai';
        $emailCol = Schema::hasColumn($table, 'email') ? 'email' : 'email';
        $diaChiCol = Schema::hasColumn($table, 'diaChi') ? 'diaChi' : 'dia_chi';
        $ttCol = Schema::hasColumn($table, 'trangThai') ? 'trangThai' : 'trang_thai';
        $createdCol = Schema::hasColumn($table, 'createdAt') ? 'createdAt' : 'created_at';
        $updatedCol = Schema::hasColumn($table, 'updatedAt') ? 'updatedAt' : 'updated_at';

        foreach ($suppliers as $s) {
            $exists = DB::table($table)->where($tenCol, $s['ten'])->first();
            if (!$exists) {
                DB::table($table)->insert([
                    $tenCol => $s['ten'],
                    $sdtCol => $s['sdt'],
                    $emailCol => $s['email'],
                    $diaChiCol => $s['diaChi'],
                    $ttCol => $s['trangThai'],
                    $createdCol => now(),
                    $updatedCol => now(),
                ]);
            }
        }
    }
}
