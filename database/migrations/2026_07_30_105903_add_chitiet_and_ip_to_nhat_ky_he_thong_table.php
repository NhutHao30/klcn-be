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
        Schema::table('nhat_ky_he_thong', function (Blueprint $table) {
            $table->text('CHITIET')->nullable()->after('HANH_DONG');
            $table->string('IP_ADDRESS', 45)->nullable()->after('CHITIET');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nhat_ky_he_thong', function (Blueprint $table) {
            $table->dropColumn(['CHITIET', 'IP_ADDRESS']);
        });
    }
};
