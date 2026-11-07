<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Bảng cuoc_tro_chuyen (Chatbot AI & CSKH Realtime Sessions)
        Schema::create('cuoc_tro_chuyen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('khachhang_id');
            $table->unsignedBigInteger('cskh_nhanvien_id')->nullable();
            $table->tinyInteger('trang_thai_chat')->default(0); // 0: Bot AI tự động, 1: Chuyển sang NV CSKH, 2: Kết thúc
            $table->timestamps();

            $table->foreign('khachhang_id')->references('id')->on('nguoidung')->onDelete('cascade');
            $table->foreign('cskh_nhanvien_id')->references('id')->on('nguoidung')->onDelete('set null');
        });

        // 2. Bảng tin_nhan (Messages)
        Schema::create('tin_nhan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuoc_tro_chuyen_id');
            $table->tinyInteger('nguoi_gui_type')->default(0); // 0: Khách hàng, 1: Chatbot AI, 2: Nhân viên CSKH
            $table->text('noi_dung');
            $table->timestamps();

            $table->foreign('cuoc_tro_chuyen_id')->references('id')->on('cuoc_tro_chuyen')->onDelete('cascade');
        });

        // 3. Bảng idempotency_keys (POS Anti-duplicate Tokens)
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 64)->unique();
            $table->string('request_path', 255);
            $table->string('request_hash', 64);
            $table->text('response_payload')->nullable();
            $table->integer('status_code')->default(200);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // 4. Bảng outbox_events (Outbox Event Dispatcher cho WebSocket & Webhook)
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100);
            $table->text('payload');
            $table->tinyInteger('trang_thai')->default(0); // 0: Chờ xử lý (PENDING), 1: Đã xử lý (PROCESSED)
            $table->timestamps();
        });

        // 5. Bảng audit_logs (Bảo mật & Nhật ký Hệ thống)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('taikhoan_id')->nullable();
            $table->string('action', 100);
            $table->string('table_name', 50)->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->foreign('taikhoan_id')->references('id')->on('taikhoan')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('tin_nhan');
        Schema::dropIfExists('cuoc_tro_chuyen');
    }
};
