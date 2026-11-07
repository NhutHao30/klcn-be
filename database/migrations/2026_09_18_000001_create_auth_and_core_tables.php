<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Bảng vaitro (Roles)
        Schema::create('vaitro', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->autoIncrement();
            $table->string('ten_vaitro', 50)->unique();
            $table->string('mo_ta', 255)->nullable();
            $table->timestamps();
        });

        // 2. Bảng taikhoan (Auth Accounts)
        Schema::create('taikhoan', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('password', 255);
            $table->unsignedTinyInteger('vaitro_id');
            $table->string('google_id', 100)->nullable();
            $table->tinyInteger('trang_thai')->default(1); // 0: Khóa, 1: Hoạt động
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('vaitro_id')->references('id')->on('vaitro');
        });

        // 3. Bảng personal_access_tokens (Laravel Sanctum JWT)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 4. Bảng cuahang (Store Branches)
        Schema::create('cuahang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ten_cuahang', 100);
            $table->string('so_dien_thoai', 15)->nullable();
            $table->string('dia_chi_chi_tiet', 255);
            $table->integer('ma_tinh');
            $table->integer('ma_huyen');
            $table->string('ma_xa', 20);
            $table->integer('ghn_shop_id')->nullable();
            $table->tinyInteger('trang_thai')->default(1); // 0: Tạm đóng, 1: Hoạt động
            $table->timestamps();
        });

        // 5. Bảng nguoidung (User Profiles - Khách hàng & Nhân viên)
        Schema::create('nguoidung', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('taikhoan_id')->unique();
            $table->unsignedInteger('cuahang_id')->nullable();
            $table->string('ho_ten', 100);
            $table->string('so_dien_thoai', 15)->nullable();
            $table->tinyInteger('gioi_tinh')->default(0); // 0: Nữ, 1: Nam, 2: Khác (TINYINT int performance)
            $table->date('ngay_sinh')->nullable();
            $table->string('cccd_number', 20)->unique()->nullable();
            $table->string('cccd_image_url', 255)->nullable();
            $table->tinyInteger('loai_nguoidung')->default(0); // 0: Khách hàng, 1: Nhân viên
            $table->timestamps();

            $table->foreign('taikhoan_id')->references('id')->on('taikhoan')->onDelete('cascade');
            $table->foreign('cuahang_id')->references('id')->on('cuahang')->onDelete('set null');
        });

        // 6. Bảng dia_chi (Addresses - GHN Alignment)
        Schema::create('dia_chi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nguoidung_id');
            $table->string('ho_ten_nguoi_nhan', 100);
            $table->string('so_dien_thoai', 15);
            $table->string('dia_chi_chi_tiet', 255);
            $table->integer('ma_tinh');
            $table->integer('ma_huyen');
            $table->string('ma_xa', 20);
            $table->tinyInteger('is_default')->default(0); // 0: Phụ, 1: Mặc định
            $table->timestamps();

            $table->foreign('nguoidung_id')->references('id')->on('nguoidung')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dia_chi');
        Schema::dropIfExists('nguoidung');
        Schema::dropIfExists('cuahang');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('taikhoan');
        Schema::dropIfExists('vaitro');
    }
};
