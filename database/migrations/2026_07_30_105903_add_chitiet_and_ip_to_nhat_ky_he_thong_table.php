<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nhat_ky_he_thong', function (Blueprint $table) {
            if (!Schema::hasColumn('nhat_ky_he_thong', 'CHITIET')) {
                $table->text('CHITIET')->nullable()->after('NOIDUNG');
            }
            if (!Schema::hasColumn('nhat_ky_he_thong', 'IP_ADDRESS')) {
                $table->string('IP_ADDRESS', 45)->nullable()->after('CHITIET');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nhat_ky_he_thong', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('nhat_ky_he_thong', 'CHITIET')) $cols[] = 'CHITIET';
            if (Schema::hasColumn('nhat_ky_he_thong', 'IP_ADDRESS')) $cols[] = 'IP_ADDRESS';
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};
