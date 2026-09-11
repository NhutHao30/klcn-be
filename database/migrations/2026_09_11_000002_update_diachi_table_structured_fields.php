<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add structured address components to diachi table.
     */
    public function up(): void
    {
        if (Schema::hasTable('diachi')) {
            Schema::table('diachi', function (Blueprint $table) {
                if (!Schema::hasColumn('diachi', 'SONHA')) {
                    $table->string('SONHA', 100)->nullable()->after('DIACHI');
                }
                if (!Schema::hasColumn('diachi', 'DUONG')) {
                    $table->string('DUONG', 150)->nullable()->after('SONHA');
                }
                if (!Schema::hasColumn('diachi', 'PHUONGXA')) {
                    $table->string('PHUONGXA', 100)->nullable()->after('DUONG');
                }
                if (!Schema::hasColumn('diachi', 'QUANHUYEN')) {
                    $table->string('QUANHUYEN', 100)->nullable()->after('PHUONGXA');
                }
                if (!Schema::hasColumn('diachi', 'TINHTP')) {
                    $table->string('TINHTP', 100)->nullable()->after('QUANHUYEN');
                }
                if (!Schema::hasColumn('diachi', 'LOAI')) {
                    $table->string('LOAI', 50)->default('Nhà riêng')->after('TINHTP');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('diachi')) {
            Schema::table('diachi', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('diachi', 'SONHA')) $cols[] = 'SONHA';
                if (Schema::hasColumn('diachi', 'DUONG')) $cols[] = 'DUONG';
                if (Schema::hasColumn('diachi', 'PHUONGXA')) $cols[] = 'PHUONGXA';
                if (Schema::hasColumn('diachi', 'QUANHUYEN')) $cols[] = 'QUANHUYEN';
                if (Schema::hasColumn('diachi', 'TINHTP')) $cols[] = 'TINHTP';
                if (Schema::hasColumn('diachi', 'LOAI')) $cols[] = 'LOAI';
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
