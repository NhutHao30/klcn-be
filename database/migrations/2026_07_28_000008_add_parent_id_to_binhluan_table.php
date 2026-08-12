<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('binhluan', function (Blueprint $table) {
            $table->unsignedBigInteger('PARENT_ID')->nullable()->after('MATINTUC');
            $table->foreign('PARENT_ID')->references('MABL')->on('binhluan')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('binhluan', function (Blueprint $table) {
            $table->dropForeign(['PARENT_ID']);
            $table->dropColumn('PARENT_ID');
        });
    }
};
