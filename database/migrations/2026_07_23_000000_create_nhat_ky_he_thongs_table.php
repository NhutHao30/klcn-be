<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('nhat_ky_he_thong')) {
            Schema::create('nhat_ky_he_thong', function (Blueprint $table) {
            $table->id();
            $table->string('USERNAME', 100)->nullable();
            $table->string('HOTEN', 150)->nullable();
            $table->integer('MAROLE')->nullable();
            $table->integer('MACUAHANG')->nullable();
            $table->string('HANH_DONG', 150);
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nhat_ky_he_thong');
    }
};
