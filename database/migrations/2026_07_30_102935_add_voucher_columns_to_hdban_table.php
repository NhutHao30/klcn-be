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
        Schema::table('hdban', function (Blueprint $table) {
            $table->string('MA_VOUCHER')->nullable()->after('MACUAHANG');
            $table->integer('SOTIEN_GIAM_VOUCHER')->default(0)->after('MA_VOUCHER');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hdban', function (Blueprint $table) {
            $table->dropColumn(['MA_VOUCHER', 'SOTIEN_GIAM_VOUCHER']);
        });
    }
};
