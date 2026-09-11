<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuahang', function (Blueprint $table) {
            if (!Schema::hasColumn('cuahang', 'GHN_SHOP_ID')) {
                $table->string('GHN_SHOP_ID', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuahang', function (Blueprint $table) {
            if (Schema::hasColumn('cuahang', 'GHN_SHOP_ID')) {
                $table->dropColumn('GHN_SHOP_ID');
            }
        });
    }
};
