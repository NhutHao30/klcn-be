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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 255)->unique();
            $table->string('USERNAME', 50)->nullable();
            $table->string('endpoint', 200);
            $table->string('request_hash', 64);
            $table->enum('status', ['processing', 'completed'])->default('processing');
            $table->smallInteger('response_code')->nullable();
            $table->mediumText('response_body')->nullable();
            $table->string('resource_type', 50)->nullable();
            $table->string('resource_id', 50)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('USERNAME')->references('USERNAME')->on('taikhoan')->onDelete('set null');

            $table->index(['USERNAME', 'endpoint']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
