<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tạo bảng hdnhap MỚI cho phiếu nhập hàng từ Nhà Cung Cấp (NCC) bên ngoài.
 *
 * Lưu ý: Bảng hdnhap cũ đã bị xóa bởi migration replace_hdnhap_with_internal_transfers.
 * Bảng này được tạo lại với cấu trúc đầy đủ hơn để phục vụ quản lý lô hàng.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('hdnhap')) {
            Schema::create('hdnhap', function (Blueprint $table) {
                $table->string('MAHDNHAP', 10)->primary();
                $table->dateTime('NGAYLAP')->useCurrent();
                $table->string('USERNAME', 100)->nullable();
                $table->string('GHICHU', 255)->nullable();
                $table->string('MANCC', 10)->nullable();           // Nhà cung cấp
                $table->unsignedBigInteger('MACUAHANG_NHAP')->nullable(); // Chi nhánh nhận hàng
                $table->decimal('TONGTIEN_NHAP', 18, 2)->default(0);      // Tổng chi phí
                $table->timestamps();

                $table->foreign('MANCC')->references('MANCC')->on('nha_cung_cap')->onDelete('set null');
            });
        } else {
            // Bảng đã tồn tại → chỉ thêm cột mới nếu chưa có
            Schema::table('hdnhap', function (Blueprint $table) {
                if (!Schema::hasColumn('hdnhap', 'MANCC')) {
                    $table->string('MANCC', 10)->nullable()->after('GHICHU');
                    $table->foreign('MANCC')->references('MANCC')->on('nha_cung_cap')->onDelete('set null');
                }
                if (!Schema::hasColumn('hdnhap', 'MACUAHANG_NHAP')) {
                    $table->unsignedBigInteger('MACUAHANG_NHAP')->nullable()->after('MANCC');
                }
                if (!Schema::hasColumn('hdnhap', 'TONGTIEN_NHAP')) {
                    $table->decimal('TONGTIEN_NHAP', 18, 2)->default(0)->after('MACUAHANG_NHAP');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hdnhap');
    }
};
