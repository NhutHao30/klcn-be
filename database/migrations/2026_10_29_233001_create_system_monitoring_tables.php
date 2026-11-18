<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Bảng cấu hình cảnh báo tự động
        if (!Schema::hasTable('he_thong_canh_bao')) {
            Schema::create('he_thong_canh_bao', function (Blueprint $table) {
                $table->id();
                $table->integer('cpuThreshold')->default(90);
                $table->integer('ramThreshold')->default(85);
                $table->integer('diskThreshold')->default(90);
                $table->integer('latencyThreshold')->default(1500); // ms
                $table->integer('errorRateThreshold')->default(5); // %
                $table->boolean('emailAlertEnabled')->default(true);
                $table->string('alertEmail')->default('haotruong01007@gmail.com');
                $table->boolean('telegramAlertEnabled')->default(false);
                $table->string('telegramBotToken')->nullable();
                $table->string('telegramChatId')->nullable();
                $table->boolean('slackAlertEnabled')->default(false);
                $table->string('slackWebhookUrl')->nullable();
                $table->boolean('smsAlertEnabled')->default(false);
                $table->string('smsPhoneNumber')->nullable();
                $table->timestamp('lastAlertSentAt')->nullable();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            });

            DB::table('he_thong_canh_bao')->insert([
                'cpuThreshold' => 90,
                'ramThreshold' => 85,
                'diskThreshold' => 90,
                'latencyThreshold' => 1500,
                'errorRateThreshold' => 5,
                'emailAlertEnabled' => true,
                'alertEmail' => 'haotruong01007@gmail.com',
                'telegramAlertEnabled' => false,
                'slackAlertEnabled' => false,
                'smsAlertEnabled' => false,
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);
        }

        // 2. Bảng theo dõi và gom nhóm lỗi hệ thống (Bug & Exception Tracking)
        if (!Schema::hasTable('he_thong_loi')) {
            Schema::create('he_thong_loi', function (Blueprint $table) {
                $table->id();
                $table->string('maLoi', 64)->unique(); // MD5 hash signature
                $table->string('loaiLoi', 50)->default('SERVER_500'); // DATABASE_ERROR, SERVER_500, SYNTAX_BUG, AUTH_ERROR
                $table->text('tieuDeLoi');
                $table->string('file')->nullable();
                $table->integer('dong')->nullable();
                $table->text('stackTrace')->nullable();
                $table->string('url')->nullable();
                $table->integer('soLanXuatHien')->default(1);
                $table->enum('mucDo', ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'])->default('HIGH');
                $table->timestamp('lanCuoiXuatHien')->useCurrent();
                $table->tinyInteger('trangThai')->default(0); // 0: Đang chờ xử lý, 1: Đã giải quyết, 2: Bỏ qua
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

                $table->index('loaiLoi');
                $table->index('mucDo');
                $table->index('trangThai');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('he_thong_loi');
        Schema::dropIfExists('he_thong_canh_bao');
    }
};

