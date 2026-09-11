<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dat_hang_truoc', function (Blueprint $table) {
            if (!Schema::hasColumn('dat_hang_truoc', 'HINH_THUC_NHAN')) {
                $table->enum('HINH_THUC_NHAN', ['tai_cua_hang', 'giao_hang'])->default('tai_cua_hang')->after('LY_DO_DAT_TRUOC');
            }
            if (!Schema::hasColumn('dat_hang_truoc', 'MADIACHI_GIAO_FK')) {
                $table->unsignedBigInteger('MADIACHI_GIAO_FK')->nullable()->after('HINH_THUC_NHAN');
            }
            if (!Schema::hasColumn('dat_hang_truoc', 'PHI_GIAO_HANG')) {
                $table->integer('PHI_GIAO_HANG')->default(0)->after('MADIACHI_GIAO_FK');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dat_hang_truoc', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('dat_hang_truoc', 'HINH_THUC_NHAN')) $cols[] = 'HINH_THUC_NHAN';
            if (Schema::hasColumn('dat_hang_truoc', 'MADIACHI_GIAO_FK')) $cols[] = 'MADIACHI_GIAO_FK';
            if (Schema::hasColumn('dat_hang_truoc', 'PHI_GIAO_HANG')) $cols[] = 'PHI_GIAO_HANG';
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};
