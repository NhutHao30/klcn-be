<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('nguoidung')) {
            Schema::table('nguoidung', function (Blueprint $table) {
                if (!Schema::hasColumn('nguoidung', 'avatar')) {
                    $table->text('avatar')->nullable()->after('hoTen');
                }
            });
        }

        if (Schema::hasTable('taikhoan')) {
            Schema::table('taikhoan', function (Blueprint $table) {
                if (!Schema::hasColumn('taikhoan', 'avatar')) {
                    $table->text('avatar')->nullable()->after('email');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('nguoidung') && Schema::hasColumn('nguoidung', 'avatar')) {
            Schema::table('nguoidung', function (Blueprint $table) {
                $table->dropColumn('avatar');
            });
        }

        if (Schema::hasTable('taikhoan') && Schema::hasColumn('taikhoan', 'avatar')) {
            Schema::table('taikhoan', function (Blueprint $table) {
                $table->dropColumn('avatar');
            });
        }
    }
};

