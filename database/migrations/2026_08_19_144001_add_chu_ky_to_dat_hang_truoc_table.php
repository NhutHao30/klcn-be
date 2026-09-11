<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dat_hang_truoc', function (Blueprint $table) {
            if (!Schema::hasColumn('dat_hang_truoc', 'CHU_KY')) {
                $table->text('CHU_KY')->nullable()->after('MAHD_HOAN_THANH');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dat_hang_truoc', function (Blueprint $table) {
            if (Schema::hasColumn('dat_hang_truoc', 'CHU_KY')) {
                $table->dropColumn('CHU_KY');
            }
        });
    }
};
