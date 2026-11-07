<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('pos_shifts', 'doanh_thu_tien_mat')) {
                $table->decimal('doanh_thu_tien_mat', 15, 2)->default(0)->after('tien_mat_dau_ca');
            }
            if (!Schema::hasColumn('pos_shifts', 'doanh_thu_chuyen_khoan')) {
                $table->decimal('doanh_thu_chuyen_khoan', 15, 2)->default(0)->after('doanh_thu_tien_mat');
            }
            if (!Schema::hasColumn('pos_shifts', 'doanh_thu_momo')) {
                $table->decimal('doanh_thu_momo', 15, 2)->default(0)->after('doanh_thu_chuyen_khoan');
            }
            if (!Schema::hasColumn('pos_shifts', 'tong_doanh_thu')) {
                $table->decimal('tong_doanh_thu', 15, 2)->default(0)->after('doanh_thu_momo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'doanh_thu_tien_mat',
                'doanh_thu_chuyen_khoan',
                'doanh_thu_momo',
                'tong_doanh_thu'
            ]);
        });
    }
};
