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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('MASP', 50);
            $table->unsignedBigInteger('MACUAHANG');
            $table->enum('type', [
                'PURCHASE', 'SALE', 'POS_SALE', 'TRANSFER_OUT', 'TRANSFER_IN', 
                'RETURN', 'DAMAGE', 'ADJUSTMENT', 'STOCKTAKE'
            ]);
            $table->integer('quantity');
            $table->integer('before_quantity');
            $table->integer('after_quantity');
            $table->string('reference_type', 50)->nullable();
            $table->string('reference_id', 50)->nullable();
            $table->string('USERNAME', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('MASP')->references('MASP')->on('sanpham')->onDelete('cascade');
            $table->foreign('MACUAHANG')->references('MACUAHANG')->on('cuahang')->onDelete('cascade');
            $table->foreign('USERNAME')->references('USERNAME')->on('taikhoan')->onDelete('set null');

            $table->index(['MASP', 'MACUAHANG']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
