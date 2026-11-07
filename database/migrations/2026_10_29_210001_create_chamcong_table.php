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
        if (!Schema::hasTable('chamcong')) {
            Schema::create('chamcong', function (Blueprint $table) {
                $table->id();
                $table->string('USERNAME', 50)->index();
                $table->date('NGAYCHAMCONG')->index();
                $table->boolean('TRANGTHAI')->default(1);
                $table->text('GHI_CHU')->nullable();
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();

                $table->unique(['USERNAME', 'NGAYCHAMCONG'], 'chamcong_username_date_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chamcong');
    }
};

