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
        Schema::table('cuahang', function (Blueprint $table) {
            $table->string('GHN_SHOP_ID')->nullable()->after('TRANGTHAI');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuahang', function (Blueprint $table) {
            $table->dropColumn('GHN_SHOP_ID');
        });
    }
};
