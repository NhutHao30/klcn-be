<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('he_thong_khoa_chuc_nang')) {
            Schema::create('he_thong_khoa_chuc_nang', function (Blueprint $table) {
                $table->id();
                $table->string('maChucNang', 50)->unique();
                $table->string('tenChucNang', 150);
                $table->string('nhomChucNang', 100)->default('Khác');
                $table->boolean('isLocked')->default(false);
                $table->string('lyDoKhoa')->nullable();
                $table->string('updatedBy')->nullable();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            });

            // Seed initial key system features
            DB::table('he_thong_khoa_chuc_nang')->insert([
                [
                    'maChucNang' => 'POS',
                    'tenChucNang' => 'Bán hàng tại vòi (POS)',
                    'nhomChucNang' => 'Bán hàng',
                    'isLocked' => false,
                    'lyDoKhoa' => null,
                    'updatedBy' => 'Hệ thống',
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ],
                [
                    'maChucNang' => 'ONLINE_ORDER',
                    'tenChucNang' => 'Đặt hàng Online',
                    'nhomChucNang' => 'Bán hàng',
                    'isLocked' => false,
                    'lyDoKhoa' => null,
                    'updatedBy' => 'Hệ thống',
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ],
                [
                    'maChucNang' => 'INVENTORY_TRANSFER',
                    'tenChucNang' => 'Điều chuyển kho giữa các chi nhánh',
                    'nhomChucNang' => 'Kho hàng',
                    'isLocked' => false,
                    'lyDoKhoa' => null,
                    'updatedBy' => 'Hệ thống',
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ],
                [
                    'maChucNang' => 'PROMOTION',
                    'tenChucNang' => 'Khuyến mãi & Mã giảm giá (Voucher)',
                    'nhomChucNang' => 'Marketing',
                    'isLocked' => false,
                    'lyDoKhoa' => null,
                    'updatedBy' => 'Hệ thống',
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ],
                [
                    'maChucNang' => 'STAFF_MGMT',
                    'tenChucNang' => 'Quản lý Nhân viên & Tài khoản',
                    'nhomChucNang' => 'Hệ thống',
                    'isLocked' => false,
                    'lyDoKhoa' => null,
                    'updatedBy' => 'Hệ thống',
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ],
                [
                    'maChucNang' => 'STORE_MGMT',
                    'tenChucNang' => 'Quản lý Chi nhánh & Cửa hàng',
                    'nhomChucNang' => 'Hệ thống',
                    'isLocked' => false,
                    'lyDoKhoa' => null,
                    'updatedBy' => 'Hệ thống',
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ],
                [
                    'maChucNang' => 'PRODUCT_CATALOG',
                    'tenChucNang' => 'Quản lý Sản phẩm & Danh mục',
                    'nhomChucNang' => 'Sản phẩm',
                    'isLocked' => false,
                    'lyDoKhoa' => null,
                    'updatedBy' => 'Hệ thống',
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('he_thong_khoa_chuc_nang');
    }
};
