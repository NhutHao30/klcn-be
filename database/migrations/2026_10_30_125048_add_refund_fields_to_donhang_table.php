<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            if (!Schema::hasColumn('donHang', 'nganHangHoanTien')) {
                $table->string('nganHangHoanTien', 100)->nullable()->after('ghiChuHuy');
            }
            if (!Schema::hasColumn('donHang', 'soTaiKhoanHoanTien')) {
                $table->string('soTaiKhoanHoanTien', 50)->nullable()->after('nganHangHoanTien');
            }
            if (!Schema::hasColumn('donHang', 'tenNguoiNhanHoanTien')) {
                $table->string('tenNguoiNhanHoanTien', 150)->nullable()->after('soTaiKhoanHoanTien');
            }
            if (!Schema::hasColumn('donHang', 'trangThaiHoanTien')) {
                $table->string('trangThaiHoanTien', 50)->nullable()->after('tenNguoiNhanHoanTien');
            }
            if (!Schema::hasColumn('donHang', 'ngayHoanTien')) {
                $table->dateTime('ngayHoanTien')->nullable()->after('trangThaiHoanTien');
            }
            if (!Schema::hasColumn('donHang', 'ghiChuHoanTien')) {
                $table->text('ghiChuHoanTien')->nullable()->after('ngayHoanTien');
            }
            if (!Schema::hasColumn('donHang', 'nhanVienHoanTienId')) {
                $table->unsignedBigInteger('nhanVienHoanTienId')->nullable()->after('ghiChuHoanTien');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            $cols = [
                'nganHangHoanTien',
                'soTaiKhoanHoanTien',
                'tenNguoiNhanHoanTien',
                'trangThaiHoanTien',
                'ngayHoanTien',
                'ghiChuHoanTien',
                'nhanVienHoanTienId',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('donHang', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

