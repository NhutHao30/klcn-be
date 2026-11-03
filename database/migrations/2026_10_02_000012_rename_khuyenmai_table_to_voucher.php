<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Drop foreign key constraint on donhang if it exists
        $orderTable = Schema::hasTable('donHang') ? 'donHang' : (Schema::hasTable('donhang') ? 'donhang' : null);
        if ($orderTable) {
            try {
                Schema::table($orderTable, function ($table) {
                    $table->dropForeign('don_hang_voucher_id_foreign');
                });
            } catch (\Throwable $e) {
                // Ignore if constraint does not exist
            }
        }

        // 2. If khuyenmai table exists, rename to voucher
        if (Schema::hasTable('khuyenmai')) {
            Schema::dropIfExists('voucher');
            Schema::rename('khuyenmai', 'voucher');
        } elseif (Schema::hasTable('voucher')) {
            // Adapt existing voucher table to expected UPPERCASE column names
            Schema::table('voucher', function (Blueprint $table) {
                if (!Schema::hasColumn('voucher', 'MA_VOUCHER') && Schema::hasColumn('voucher', 'ma_voucher')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `ma_voucher` TO `MA_VOUCHER`");
                }
                if (!Schema::hasColumn('voucher', 'TEN_VOUCHER')) {
                    $table->string('TEN_VOUCHER', 255)->nullable();
                }
                if (!Schema::hasColumn('voucher', 'LOAI_GIAM')) {
                    $table->string('LOAI_GIAM', 50)->default('TIEN_MAT');
                }
                if (!Schema::hasColumn('voucher', 'GIA_TRI') && Schema::hasColumn('voucher', 'gia_tri_giam')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `gia_tri_giam` TO `GIA_TRI`");
                }
                if (!Schema::hasColumn('voucher', 'DON_TOI_THIEU') && Schema::hasColumn('voucher', 'don_hang_toi_thieu')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `don_hang_toi_thieu` TO `DON_TOI_THIEU`");
                }
                if (!Schema::hasColumn('voucher', 'GIAM_TOI_DA') && Schema::hasColumn('voucher', 'giam_toi_da')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `giam_toi_da` TO `GIAM_TOI_DA`");
                }
                if (!Schema::hasColumn('voucher', 'PHAN_TRAM_GIAM')) {
                    $table->integer('PHAN_TRAM_GIAM')->nullable();
                }
                if (!Schema::hasColumn('voucher', 'SO_LUONG_GIOI_HAN') && Schema::hasColumn('voucher', 'so_luong')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `so_luong` TO `SO_LUONG_GIOI_HAN`");
                }
                if (!Schema::hasColumn('voucher', 'SO_LUONG_DA_DUNG') && Schema::hasColumn('voucher', 'da_su_dung')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `da_su_dung` TO `SO_LUONG_DA_DUNG`");
                }
                if (!Schema::hasColumn('voucher', 'NGAY_BAT_DAU') && Schema::hasColumn('voucher', 'ngay_bat_dau')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `ngay_bat_dau` TO `NGAY_BAT_DAU`");
                }
                if (!Schema::hasColumn('voucher', 'NGAY_KET_THUC') && Schema::hasColumn('voucher', 'ngay_ket_thuc')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `ngay_ket_thuc` TO `NGAY_KET_THUC`");
                }
                if (!Schema::hasColumn('voucher', 'TRANG_THAI') && Schema::hasColumn('voucher', 'trang_thai')) {
                    DB::statement("ALTER TABLE `voucher` RENAME COLUMN `trang_thai` TO `TRANG_THAI`");
                }
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        if (Schema::hasTable('voucher') && !Schema::hasTable('khuyenmai')) {
            Schema::rename('voucher', 'khuyenmai');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
