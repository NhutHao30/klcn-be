<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add missing fields from Phiếu Nhập Kho template to hdnhap table.
     */
    public function up(): void
    {
        if (Schema::hasTable('hdnhap')) {
            Schema::table('hdnhap', function (Blueprint $table) {
                if (!Schema::hasColumn('hdnhap', 'MA_PHIEU_NCC')) {
                    $table->string('MA_PHIEU_NCC', 50)->nullable()->after('MANCC');
                }
                if (!Schema::hasColumn('hdnhap', 'SO_HOA_DON')) {
                    $table->string('SO_HOA_DON', 50)->nullable()->after('MA_PHIEU_NCC');
                }
                if (!Schema::hasColumn('hdnhap', 'NGAY_HOA_DON')) {
                    $table->date('NGAY_HOA_DON')->nullable()->after('SO_HOA_DON');
                }
                if (!Schema::hasColumn('hdnhap', 'PHUONGTHUCTHANHTOAN')) {
                    $table->enum('PHUONGTHUCTHANHTOAN', ['Tiền mặt', 'Chuyển khoản', 'Công nợ'])->default('Chuyển khoản')->after('TONGTIEN_NHAP');
                }
                if (!Schema::hasColumn('hdnhap', 'CHIETKHAU')) {
                    $table->decimal('CHIETKHAU', 18, 2)->default(0)->after('PHUONGTHUCTHANHTOAN');
                }
                if (!Schema::hasColumn('hdnhap', 'THUE_VAT')) {
                    $table->float('THUE_VAT')->default(0)->after('CHIETKHAU');
                }
                if (!Schema::hasColumn('hdnhap', 'PHI_KHAC')) {
                    $table->decimal('PHI_KHAC', 18, 2)->default(0)->after('THUE_VAT');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hdnhap')) {
            Schema::table('hdnhap', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('hdnhap', 'MA_PHIEU_NCC')) $cols[] = 'MA_PHIEU_NCC';
                if (Schema::hasColumn('hdnhap', 'SO_HOA_DON')) $cols[] = 'SO_HOA_DON';
                if (Schema::hasColumn('hdnhap', 'NGAY_HOA_DON')) $cols[] = 'NGAY_HOA_DON';
                if (Schema::hasColumn('hdnhap', 'PHUONGTHUCTHANHTOAN')) $cols[] = 'PHUONGTHUCTHANHTOAN';
                if (Schema::hasColumn('hdnhap', 'CHIETKHAU')) $cols[] = 'CHIETKHAU';
                if (Schema::hasColumn('hdnhap', 'THUE_VAT')) $cols[] = 'THUE_VAT';
                if (Schema::hasColumn('hdnhap', 'PHI_KHAC')) $cols[] = 'PHI_KHAC';
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
