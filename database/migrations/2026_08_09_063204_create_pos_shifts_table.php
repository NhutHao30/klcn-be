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
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('MACUAHANG');
            $table->string('USERNAME', 50);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 15, 2);
            $table->decimal('closing_cash_expected', 15, 2)->nullable();
            $table->decimal('closing_cash_actual', 15, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('MACUAHANG')->references('MACUAHANG')->on('cuahang')->onDelete('cascade');
            $table->foreign('USERNAME')->references('USERNAME')->on('taikhoan')->onDelete('cascade');

            $table->index(['MACUAHANG', 'status']);
            $table->index('USERNAME');
            $table->index('opened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
