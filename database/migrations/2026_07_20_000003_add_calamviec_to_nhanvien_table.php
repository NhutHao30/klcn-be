<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('nhanvien', function (Blueprint $table) {
            if (!Schema::hasColumn('nhanvien', 'CALAMVIEC')) {
                $table->string('CALAMVIEC', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('nhanvien', function (Blueprint $table) {
            if (Schema::hasColumn('nhanvien', 'CALAMVIEC')) {
                $table->dropColumn('CALAMVIEC');
            }
        });
    }
};
