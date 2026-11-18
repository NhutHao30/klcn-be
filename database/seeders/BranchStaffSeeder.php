<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\AdminController;
use App\Models\CuaHang;
use App\Models\NguoiDung;
use App\Models\TaiKhoan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchStaffSeeder extends Seeder
{
    /**
     * Tạo tài khoản nhân viên cho Chi nhánh 2 và Chi nhánh 3
     * (Quản lý, Thu ngân/Bán hàng, Thủ kho, Kế toán, CSKH)
     * Mật khẩu mặc định = username (CCCD).
     */
    public function run(): void
    {
        AdminController::ensureRolesAndColumnsExist();

        $staffList = [
            // Chi nhánh 2
            ['username' => '079202000201', 'hoTen' => 'Lê Minh Khang', 'vaiTroId' => 2, 'luong' => 15500000, 'sdt' => '0909200201', 'email' => 'minhkhang.cn2@gmail.com', 'cuaHangId' => 2, 'caLamViec' => 'Hành chính'],
            ['username' => '079202000202', 'hoTen' => 'Nguyễn Ngọc Hân', 'vaiTroId' => 3, 'luong' => 8500000, 'sdt' => '0909200202', 'email' => 'ngochan.cn2@gmail.com', 'cuaHangId' => 2, 'caLamViec' => 'Ca Sáng'],
            ['username' => '079202000203', 'hoTen' => 'Trần Hữu Phước', 'vaiTroId' => 3, 'luong' => 8500000, 'sdt' => '0909200203', 'email' => 'huuphuoc.cn2@gmail.com', 'cuaHangId' => 2, 'caLamViec' => 'Ca Chiều'],
            ['username' => '079202000204', 'hoTen' => 'Võ Thành Đạt', 'vaiTroId' => 4, 'luong' => 9500000, 'sdt' => '0909200204', 'email' => 'thanhdat.cn2@gmail.com', 'cuaHangId' => 2, 'caLamViec' => 'Ca Sáng'],
            ['username' => '079202000205', 'hoTen' => 'Đỗ Thị Phương Vy', 'vaiTroId' => 7, 'luong' => 12000000, 'sdt' => '0909200205', 'email' => 'phuongvy.cn2@gmail.com', 'cuaHangId' => 2, 'caLamViec' => 'Hành chính'],
            ['username' => '079202000206', 'hoTen' => 'Huỳnh Gia Bảo', 'vaiTroId' => 5, 'luong' => 9000000, 'sdt' => '0909200206', 'email' => 'giabao.cn2@gmail.com', 'cuaHangId' => 2, 'caLamViec' => 'Ca Sáng'],

            // Chi nhánh 3
            ['username' => '079203000301', 'hoTen' => 'Phạm Hoàng Nam', 'vaiTroId' => 2, 'luong' => 15500000, 'sdt' => '0909300301', 'email' => 'hoangnam.cn3@gmail.com', 'cuaHangId' => 3, 'caLamViec' => 'Hành chính'],
            ['username' => '079203000302', 'hoTen' => 'Lê Thị Bích Ngọc', 'vaiTroId' => 3, 'luong' => 8500000, 'sdt' => '0909300302', 'email' => 'bichngoc.cn3@gmail.com', 'cuaHangId' => 3, 'caLamViec' => 'Ca Sáng'],
            ['username' => '079203000303', 'hoTen' => 'Nguyễn Minh Tuấn', 'vaiTroId' => 3, 'luong' => 8500000, 'sdt' => '0909300303', 'email' => 'minhtuan.cn3@gmail.com', 'cuaHangId' => 3, 'caLamViec' => 'Ca Chiều'],
            ['username' => '079203000304', 'hoTen' => 'Trương Quốc Huy', 'vaiTroId' => 4, 'luong' => 9500000, 'sdt' => '0909300304', 'email' => 'quochuy.cn3@gmail.com', 'cuaHangId' => 3, 'caLamViec' => 'Ca Sáng'],
            ['username' => '079203000305', 'hoTen' => 'Trần Thanh Trúc', 'vaiTroId' => 7, 'luong' => 12000000, 'sdt' => '0909300305', 'email' => 'thanhtruc.cn3@gmail.com', 'cuaHangId' => 3, 'caLamViec' => 'Hành chính'],
            ['username' => '079203000306', 'hoTen' => 'Phan Bảo Châu', 'vaiTroId' => 5, 'luong' => 9000000, 'sdt' => '0909300306', 'email' => 'baochau.cn3@gmail.com', 'cuaHangId' => 3, 'caLamViec' => 'Ca Sáng'],
        ];

        $existingStoreIds = CuaHang::pluck('id')->all();
        $createdOrUpdated = 0;

        DB::transaction(function () use ($staffList, $existingStoreIds, &$createdOrUpdated) {
            foreach ($staffList as $row) {
                if (!in_array($row['cuaHangId'], $existingStoreIds, true)) {
                    continue;
                }

                $tk = TaiKhoan::where('username', $row['username'])->first();
                if (!$tk) {
                    $tk = TaiKhoan::create([
                        'username' => $row['username'],
                        'password' => $row['username'],
                        'email' => $row['email'],
                        'vaiTroId' => $row['vaiTroId'],
                        'trangThai' => 1,
                    ]);
                } else {
                    $tk->update([
                        'email' => $row['email'],
                        'vaiTroId' => $row['vaiTroId'],
                        'trangThai' => 1,
                    ]);
                }

                NguoiDung::updateOrCreate(
                    ['taiKhoanId' => $tk->id],
                    [
                        'cuaHangId' => $row['cuaHangId'],
                        'hoTen' => $row['hoTen'],
                        'soDienThoai' => $row['sdt'],
                        'cccdNumber' => $row['username'],
                        'loaiNguoiDung' => 1,
                        'caLamViec' => $row['caLamViec'],
                        'luong' => $row['luong'],
                    ]
                );

                $createdOrUpdated++;
            }
        });

        $this->command?->info("BranchStaffSeeder hoàn tất: đã tạo/cập nhật {$createdOrUpdated} tài khoản nhân viên cho Chi nhánh 2 & 3.");
    }
}
