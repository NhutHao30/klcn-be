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
        Schema::table('sanpham', function (Blueprint $table) {
            $table->boolean('IS_NEW')->default(false)->after('MALOAI');
            $table->decimal('PHAN_TRAM_GIAM', 5, 2)->default(0)->after('IS_NEW');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanpham', function (Blueprint $table) {
            $table->dropColumn(['IS_NEW', 'PHAN_TRAM_GIAM']);
        });
    }
};
