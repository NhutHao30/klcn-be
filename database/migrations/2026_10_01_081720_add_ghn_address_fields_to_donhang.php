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
        Schema::table('donHang', function (Blueprint $table) {
            $table->string('toWardCode', 20)->nullable()->after('diaChiGiaoHang');
            $table->integer('toDistrictId')->nullable()->after('toWardCode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            $table->dropColumn(['toWardCode', 'toDistrictId']);
        });
    }
};
