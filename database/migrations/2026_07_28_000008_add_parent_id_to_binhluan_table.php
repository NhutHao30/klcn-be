<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('binhluan', function (Blueprint $table) {
            if (!Schema::hasColumn('binhluan', 'PARENT_ID')) {
                $table->unsignedBigInteger('PARENT_ID')->nullable()->after('MATINTUC');
                try {
                    $table->foreign('PARENT_ID')->references('MABL')->on('binhluan')->onDelete('cascade');
                } catch (\Exception $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('binhluan', function (Blueprint $table) {
            if (Schema::hasColumn('binhluan', 'PARENT_ID')) {
                try {
                    $table->dropForeign(['PARENT_ID']);
                } catch (\Exception $e) {}
                $table->dropColumn('PARENT_ID');
            }
        });
    }
};
