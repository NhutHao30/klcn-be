<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tạo bảng chitiethdnhap MỚI (đầy đủ hơn) để theo dõi lô hàng.
 *
 * Lưu ý: Bảng này đã bị xóa bởi migration replace_hdnhap_with_internal_transfers.
 * Được tạo lại với cột MACUAHANG, NGAYSX, HSD_LO để quản lý lô hàng.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('chitiethdnhap')) {
            Schema::create('chitiethdnhap', function (Blueprint $table) {
                $table->string('MAHDNHAP', 10);
                $table->string('MASP', 10);
                $table->integer('SOLUONGTCT')->default(0);      // Số lượng theo chứng từ
                $table->integer('SOLUONGTN')->default(0);       // Số lượng thực nhận
                $table->decimal('DONGIANHAP', 18, 2)->default(0);
                $table->decimal('THANHTIENN', 18, 2)->virtualAs('SOLUONGTN * DONGIANHAP');
                $table->string('GHICHU', 255)->nullable();
                $table->unsignedBigInteger('MACUAHANG')->nullable(); // Chi nhánh nhận lô
                $table->date('NGAYSX')->nullable();                   // Ngày sản xuất lô
                $table->date('HSD_LO')->nullable();                   // HSD riêng của lô này
                $table->timestamps();

                $table->primary(['MAHDNHAP', 'MASP']);
                $table->foreign('MAHDNHAP')->references('MAHDNHAP')->on('hdnhap')->onDelete('cascade');
                $table->foreign('MASP')->references('MASP')->on('sanpham');
            });
        } else {
            // Bảng đã tồn tại → thêm cột batch nếu chưa có
            Schema::table('chitiethdnhap', function (Blueprint $table) {
                if (!Schema::hasColumn('chitiethdnhap', 'MACUAHANG')) {
                    $table->unsignedBigInteger('MACUAHANG')->nullable()->after('GHICHU');
                }
                if (!Schema::hasColumn('chitiethdnhap', 'NGAYSX')) {
                    $table->date('NGAYSX')->nullable()->after('MACUAHANG');
                }
                if (!Schema::hasColumn('chitiethdnhap', 'HSD_LO')) {
                    $table->date('HSD_LO')->nullable()->after('NGAYSX');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chitiethdnhap');
    }
};
