<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('taikhoan', function (Blueprint $table) {
            if (!Schema::hasColumn('taikhoan', 'GOOGLE_ID')) {
                $table->string('GOOGLE_ID')->nullable()->unique()->after('EMAIL');
            }
            if (!Schema::hasColumn('taikhoan', 'AVATAR')) {
                $table->string('AVATAR', 255)->nullable()->after('GOOGLE_ID');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taikhoan', function (Blueprint $table) {
            if (Schema::hasColumn('taikhoan', 'GOOGLE_ID')) {
                $table->dropColumn('GOOGLE_ID');
            }
            if (Schema::hasColumn('taikhoan', 'AVATAR')) {
                $table->dropColumn('AVATAR');
            }
        });
    }
};
