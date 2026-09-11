<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // 1. Thêm CHU_KY vào bảng nhanvien
        if (Schema::hasTable('nhanvien') && !Schema::hasColumn('nhanvien', 'CHU_KY')) {
            Schema::table('nhanvien', function (Blueprint $table) {
                $table->longText('CHU_KY')->nullable()->comment('Chữ ký số base64 hoặc URL');
            });
        }

        // 2. Thêm các cột chữ ký vào bảng hdnhap
        if (Schema::hasTable('hdnhap')) {
            Schema::table('hdnhap', function (Blueprint $table) {
                if (!Schema::hasColumn('hdnhap', 'KY_THU_KHO')) {
                    $table->longText('KY_THU_KHO')->nullable();
                }
                if (!Schema::hasColumn('hdnhap', 'KY_NCC')) {
                    $table->longText('KY_NCC')->nullable()->comment('Chữ ký trực tiếp của đại diện NCC');
                }
                if (!Schema::hasColumn('hdnhap', 'KY_KE_TOAN')) {
                    $table->longText('KY_KE_TOAN')->nullable();
                }
                if (!Schema::hasColumn('hdnhap', 'KY_GIAM_DOC')) {
                    $table->longText('KY_GIAM_DOC')->nullable();
                }
                if (!Schema::hasColumn('hdnhap', 'NGUOI_KY_KE_TOAN')) {
                    $table->string('NGUOI_KY_KE_TOAN', 100)->nullable();
                }
                if (!Schema::hasColumn('hdnhap', 'NGUOI_KY_GIAM_DOC')) {
                    $table->string('NGUOI_KY_GIAM_DOC', 100)->nullable();
                }
                if (!Schema::hasColumn('hdnhap', 'TRANGTHAI_KY')) {
                    $table->string('TRANGTHAI_KY', 30)->default('cho_ky')->comment('cho_ky, da_ky_mot_phan, hoan_tat_ky');
                }
            });
        }

        // 3. Thêm các cột chữ ký vào bảng bien_ban_huy
        if (Schema::hasTable('bien_ban_huy')) {
            Schema::table('bien_ban_huy', function (Blueprint $table) {
                if (!Schema::hasColumn('bien_ban_huy', 'KY_THU_KHO')) {
                    $table->longText('KY_THU_KHO')->nullable();
                }
                if (!Schema::hasColumn('bien_ban_huy', 'KY_KE_TOAN')) {
                    $table->longText('KY_KE_TOAN')->nullable();
                }
                if (!Schema::hasColumn('bien_ban_huy', 'KY_GIAM_DOC')) {
                    $table->longText('KY_GIAM_DOC')->nullable();
                }
                if (!Schema::hasColumn('bien_ban_huy', 'NGUOI_KY_KE_TOAN')) {
                    $table->string('NGUOI_KY_KE_TOAN', 100)->nullable();
                }
                if (!Schema::hasColumn('bien_ban_huy', 'NGUOI_KY_GIAM_DOC')) {
                    $table->string('NGUOI_KY_GIAM_DOC', 100)->nullable();
                }
                if (!Schema::hasColumn('bien_ban_huy', 'TRANGTHAI_KY')) {
                    $table->string('TRANGTHAI_KY', 30)->default('cho_ky')->comment('cho_ky, da_ky_mot_phan, hoan_tat_ky');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nhanvien') && Schema::hasColumn('nhanvien', 'CHU_KY')) {
            Schema::table('nhanvien', function (Blueprint $table) {
                $table->dropColumn('CHU_KY');
            });
        }
        if (Schema::hasTable('hdnhap')) {
            Schema::table('hdnhap', function (Blueprint $table) {
                $table->dropColumn([
                    'KY_THU_KHO', 'KY_NCC', 'KY_KE_TOAN', 'KY_GIAM_DOC',
                    'NGUOI_KY_KE_TOAN', 'NGUOI_KY_GIAM_DOC', 'TRANGTHAI_KY'
                ]);
            });
        }
        if (Schema::hasTable('bien_ban_huy')) {
            Schema::table('bien_ban_huy', function (Blueprint $table) {
                $table->dropColumn([
                    'KY_THU_KHO', 'KY_KE_TOAN', 'KY_GIAM_DOC',
                    'NGUOI_KY_KE_TOAN', 'NGUOI_KY_GIAM_DOC', 'TRANGTHAI_KY'
                ]);
            });
        }
    }
};
