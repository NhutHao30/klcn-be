<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanpham', function (Blueprint $table) {
            if (!Schema::hasColumn('sanpham', 'IS_NEW')) {
                $table->boolean('IS_NEW')->default(false)->after('MALOAI');
            }
            if (!Schema::hasColumn('sanpham', 'PHAN_TRAM_GIAM')) {
                $table->decimal('PHAN_TRAM_GIAM', 5, 2)->default(0)->after('IS_NEW');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sanpham', function (Blueprint $table) {
            $colsToDrop = [];
            if (Schema::hasColumn('sanpham', 'IS_NEW')) {
                $colsToDrop[] = 'IS_NEW';
            }
            if (Schema::hasColumn('sanpham', 'PHAN_TRAM_GIAM')) {
                $colsToDrop[] = 'PHAN_TRAM_GIAM';
            }
            if (!empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });
    }
};
