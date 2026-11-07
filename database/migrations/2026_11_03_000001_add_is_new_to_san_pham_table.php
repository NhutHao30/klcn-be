<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $table = Schema::hasTable('sanPham') ? 'sanPham' : 'san_pham';
        Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
            if (!Schema::hasColumn($table, 'isNew')) {
                $tableBlueprint->boolean('isNew')->default(0)->after('trangThai');
            }
        });
    }

    public function down(): void
    {
        $table = Schema::hasTable('sanPham') ? 'sanPham' : 'san_pham';
        Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
            if (Schema::hasColumn($table, 'isNew')) {
                $tableBlueprint->dropColumn('isNew');
            }
        });
    }
};

