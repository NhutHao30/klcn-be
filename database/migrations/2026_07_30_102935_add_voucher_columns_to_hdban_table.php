<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hdban', function (Blueprint $table) {
            if (!Schema::hasColumn('hdban', 'MA_VOUCHER')) {
                $table->string('MA_VOUCHER', 50)->nullable();
            }
            if (!Schema::hasColumn('hdban', 'SOTIEN_GIAM_VOUCHER')) {
                $table->integer('SOTIEN_GIAM_VOUCHER')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('hdban', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('hdban', 'MA_VOUCHER')) $cols[] = 'MA_VOUCHER';
            if (Schema::hasColumn('hdban', 'SOTIEN_GIAM_VOUCHER')) $cols[] = 'SOTIEN_GIAM_VOUCHER';
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};
