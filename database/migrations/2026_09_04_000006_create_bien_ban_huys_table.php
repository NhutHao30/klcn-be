<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bien_ban_huy')) {
            Schema::create('bien_ban_huy', function (Blueprint $table) {
            $table->id();

            // Lô hàng bị hủy
            $table->string('MALOHAN', 20);

            // Chi nhánh thực hiện hủy
            $table->unsignedBigInteger('MACUAHANG');

            // Người lập biên bản hủy
            $table->string('USERNAME', 100)->nullable();

            // Số lượng hủy trong biên bản này
            $table->integer('SOLUONG_HUY')->default(0);

            /**
             * Lý do hủy:
             *  - het_han    : Hết hạn sử dụng (tự động bởi job)
             *  - hong_hong  : Hỏng hóc trong quá trình bảo quản
             *  - thu_hoi    : Thu hồi từ nhà cung cấp / lỗi sản xuất
             *  - khac       : Lý do khác (ghi chú thủ công)
             */
            $table->enum('LY_DO', ['het_han', 'hong_hong', 'thu_hoi', 'khac'])
                  ->default('het_han');

            $table->text('GHICHU')->nullable();   // Ghi chú chi tiết
            $table->timestamp('NGAY_HUY')->useCurrent(); // Thời điểm hủy
            $table->timestamps();

            // Foreign keys
            $table->foreign('MALOHAN')->references('MALOHAN')->on('lo_hang')->onDelete('cascade');
            $table->foreign('MACUAHANG')->references('MACUAHANG')->on('cuahang')->onDelete('cascade');
            $table->foreign('USERNAME')->references('USERNAME')->on('taikhoan')->onDelete('set null');

            // Indexes
            $table->index(['MALOHAN']);
            $table->index(['MACUAHANG', 'NGAY_HUY']);
            $table->index('NGAY_HUY');
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bien_ban_huy');
    }
};
