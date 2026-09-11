<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('nhanvien', function (Blueprint $table) {
            if (!Schema::hasColumn('nhanvien', 'TRANGTHAI')) {
                $table->string('TRANGTHAI', 20)->default('Đang làm việc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nhanvien', function (Blueprint $table) {
            if (Schema::hasColumn('nhanvien', 'TRANGTHAI')) {
                $table->dropColumn('TRANGTHAI');
            }
        });
    }
};
