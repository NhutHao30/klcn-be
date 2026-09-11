<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lo_hang')) {
            Schema::create('lo_hang', function (Blueprint $table) {
            // Mã lô: VD LH-20260904-0001
            $table->string('MALOHAN', 20)->primary();

            // Phiếu nhập hàng tương ứng (nullable để linh hoạt tạo lô thủ công)
            $table->string('MAHDNHAP', 10)->nullable();

            // Sản phẩm
            $table->string('MASP', 10);

            // Chi nhánh nhận lô hàng
            $table->unsignedBigInteger('MACUAHANG');

            // Thông tin lô
            $table->date('NGAYNHAP');           // Ngày nhập thực tế
            $table->date('NGAYSX')->nullable();  // Ngày sản xuất
            $table->date('HSD');                 // Hạn sử dụng của lô này

            // Số lượng
            $table->integer('SOLUONG_NHAP')->default(0);  // Ban đầu khi nhập
            $table->integer('SOLUONG_CON')->default(0);   // Còn lại realtime
            $table->integer('SOLUONG_BAN')->default(0);   // Đã bán (denormalized)
            $table->integer('SOLUONG_HUY')->default(0);   // Đã hủy do hỏng/hết hạn

            // Giá nhập của lô này (có thể khác lô cũ)
            $table->decimal('DONGIA_NHAP', 18, 2)->default(0);

            /**
             * Trạng thái vòng đời lô:
             *  - con_hang   : Còn hàng, HSD còn hiệu lực
             *  - da_ban_het : Đã bán hết toàn bộ
             *  - het_han    : Hết hạn sử dụng (auto bởi job)
             *  - bi_huy     : Bị hủy thủ công (hỏng, thu hồi,...)
             */
            $table->enum('TRANGTHAI', ['con_hang', 'da_ban_het', 'het_han', 'bi_huy'])
                  ->default('con_hang');

            $table->text('GHICHU')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('MAHDNHAP')->references('MAHDNHAP')->on('hdnhap')->onDelete('set null');
            $table->foreign('MASP')->references('MASP')->on('sanpham')->onDelete('cascade');
            $table->foreign('MACUAHANG')->references('MACUAHANG')->on('cuahang')->onDelete('cascade');

            // Indexes tối ưu query
            $table->index(['MASP', 'MACUAHANG', 'TRANGTHAI']);
            $table->index(['HSD', 'TRANGTHAI']);           // Query cảnh báo HSD
            $table->index(['MASP', 'HSD', 'TRANGTHAI']);   // FEFO lookup
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lo_hang');
    }
};
