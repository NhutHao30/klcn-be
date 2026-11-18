<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tintuc')) {
            Schema::create('tintuc', function (Blueprint $table) {
                $table->string('MATINTUC', 50)->primary();
                $table->string('TIEUDE', 255);
                $table->text('MOTA')->nullable();
                $table->text('HINHANH')->nullable();
                $table->date('NGAYDANG');
                $table->unsignedBigInteger('taiKhoanId')->nullable();
                $table->tinyInteger('trangThai')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('chitiettintuc')) {
            Schema::create('chitiettintuc', function (Blueprint $table) {
                $table->id('MACT');
                $table->string('MATINTUC', 50)->index();
                $table->string('BOCUC', 50)->default('paragraph');
                $table->longText('ARTICLE')->nullable();
                $table->text('HINHANH')->nullable();
                $table->integer('THUTU')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('binhluantintuc')) {
            Schema::create('binhluantintuc', function (Blueprint $table) {
                $table->id('MABL');
                $table->string('MATINTUC', 50)->index();
                $table->string('MAKH', 50)->nullable();
                $table->unsignedBigInteger('taiKhoanId')->nullable();
                $table->string('HOTEN', 150)->nullable();
                $table->text('NOIDUNG');
                $table->dateTime('NGAYDANG');
                $table->unsignedBigInteger('PARENT_ID')->nullable()->index();
                $table->timestamps();
            });
        }

        // Seed bài viết mẫu nếu bảng đang trống
        if (DB::table('tintuc')->count() === 0) {
            $now = now();
            $articles = [
                [
                    'MATINTUC' => 'TT001',
                    'TIEUDE' => 'Khám phá bộ sưu tập Bánh Kem Tươi Givral mùa lễ hội 2026',
                    'MOTA' => 'Bộ sưu tập bánh kem tươi mới nhất của Givral Bakery kết hợp nguyên liệu bơ sữa Pháp thượng hạng cùng trái cây nhiệt đới tươi mát.',
                    'HINHANH' => '/api/proxy-image?path=productnew1.webp',
                    'NGAYDANG' => $now->copy()->subDays(5)->toDateString(),
                    'paragraphs' => [
                        'Mỗi chiếc bánh kem tại Givral Bakery đều được chế biến thủ công trong ngày bởi đội ngũ nghệ nhân làm bánh giàu kinh nghiệm, sử dụng 100% kem tươi whipping cream nhập khẩu và cốt bánh bông lan mềm mịn ít ngọt.',
                        'Trong mùa lễ hội năm nay, cửa hàng ra mắt 6 hương vị mới bao gồm Tiramisu Cà Phê Ý, Mousse Chanh Dây Nhiệt Đới, Red Velvet Phô Mai và Bánh Kem Dâu Tây Đà Lạt.',
                        'Quý khách hàng có thể đặt bánh trực tuyến ngay trên website hoặc ghé thăm các chi nhánh Givral Bakery trên toàn quốc để thưởng thức hương vị bánh tươi nguyên bản.'
                    ],
                ],
                [
                    'MATINTUC' => 'TT002',
                    'TIEUDE' => 'Bí quyết bảo quản bánh ngọt và bánh mì tươi ngon chuẩn vị tại nhà',
                    'MOTA' => 'Hướng dẫn chi tiết cách giữ độ mềm xốp của bánh bông lan, bánh quy bơ và bánh kem tươi khi chưa sử dụng hết trong ngày.',
                    'HINHANH' => '/api/proxy-image?path=productnew2.webp',
                    'NGAYDANG' => $now->copy()->subDays(3)->toDateString(),
                    'paragraphs' => [
                        'Đối với các dòng bánh kem tươi và bánh Mousse, nhiệt độ lý tưởng để bảo quản là ngăn mát tủ lạnh từ 2 đến 6 độ C trong hộp kín để tránh bánh bị khô mặt hoặc ám mùi thực phẩm khác.',
                        'Với dòng bánh mì tươi và bánh nướng trong ngày như Croissant bơ Pháp hay Bánh mì hoa cúc, bạn nên giữ ở nhiệt độ phòng thoáng mát và có thể làm nóng lại bằng lò nướng ở 160 độ C trong 3 phút trước khi dùng.',
                        'Cam kết chất lượng của Givral Bakery: Toàn bộ sản phẩm bánh tươi không sử dụng chất bảo quản nhân tạo nhằm bảo vệ sức khỏe người tiêu dùng.'
                    ],
                ],
                [
                    'MATINTUC' => 'TT003',
                    'TIEUDE' => 'Ưu đãi thành viên Givral Bakery: Tích điểm đổi quà & Kho Voucher hấp dẫn',
                    'MOTA' => 'Ra mắt chương trình tích điểm thành viên tự động cho mọi đơn hàng hoàn tất cùng Ví Voucher tiện lợi ngay trên trang cá nhân.',
                    'HINHANH' => '/api/proxy-image?path=productnew3.webp',
                    'NGAYDANG' => $now->copy()->subDays(1)->toDateString(),
                    'paragraphs' => [
                        'Từ tháng 10/2026, mọi khách hàng mua sắm tại Givral Bakery đều được tự động tích lũy điểm thưởng với tỷ lệ 10.000đ = 1 điểm thưởng ngay khi đơn hàng hoàn thành.',
                        'Hệ thống tự động nâng hạng thành viên từ Thành viên mới lên hạng Bạc, Vàng và Kim Cương kèm theo nhiều mã giảm giá độc quyền trong Ví Voucher cá nhân.',
                        'Hãy truy cập mục Tài khoản cá nhân -> Ví Voucher để lưu ngay các mã giảm giá mới nhất và áp dụng trực tiếp tại bước thanh toán giỏ hàng!'
                    ],
                ],
            ];

            foreach ($articles as $art) {
                DB::table('tintuc')->insert([
                    'MATINTUC' => $art['MATINTUC'],
                    'TIEUDE' => $art['TIEUDE'],
                    'MOTA' => $art['MOTA'],
                    'HINHANH' => $art['HINHANH'],
                    'NGAYDANG' => $art['NGAYDANG'],
                    'trangThai' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($art['paragraphs'] as $idx => $p) {
                    DB::table('chitiettintuc')->insert([
                        'MATINTUC' => $art['MATINTUC'],
                        'BOCUC' => 'paragraph',
                        'ARTICLE' => $p,
                        'THUTU' => $idx + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('binhluantintuc');
        Schema::dropIfExists('chitiettintuc');
        Schema::dropIfExists('tintuc');
    }
};

