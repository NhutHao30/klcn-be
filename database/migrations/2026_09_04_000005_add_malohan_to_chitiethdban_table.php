<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chitiethdban', function (Blueprint $table) {
            if (!Schema::hasColumn('chitiethdban', 'MALOHAN')) {
                $table->string('MALOHAN', 50)->nullable()->after('MASP');
                try {
                    $table->foreign('MALOHAN')->references('MALOHAN')->on('lo_hang')->onDelete('set null');
                } catch (\Exception $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('chitiethdban', function (Blueprint $table) {
            if (Schema::hasColumn('chitiethdban', 'MALOHAN')) {
                try {
                    $table->dropForeign(['MALOHAN']);
                } catch (\Exception $e) {}
                $table->dropColumn('MALOHAN');
            }
        });
    }
};
