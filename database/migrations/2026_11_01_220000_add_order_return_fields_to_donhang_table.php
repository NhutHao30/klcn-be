<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            if (!Schema::hasColumn('donHang', 'lyDoHoanTra')) {
                $table->string('lyDoHoanTra', 255)->nullable()->after('ghiChuHuy');
            }
            if (!Schema::hasColumn('donHang', 'ghiChuHoanTra')) {
                $table->text('ghiChuHoanTra')->nullable()->after('lyDoHoanTra');
            }
            if (!Schema::hasColumn('donHang', 'hinhAnhHoanTra')) {
                $table->longText('hinhAnhHoanTra')->nullable()->after('ghiChuHoanTra');
            }
            if (!Schema::hasColumn('donHang', 'ngayYeuCauHoanTra')) {
                $table->dateTime('ngayYeuCauHoanTra')->nullable()->after('hinhAnhHoanTra');
            }
            if (!Schema::hasColumn('donHang', 'trangThaiHoanHang')) {
                $table->string('trangThaiHoanHang', 50)->nullable()->after('ngayYeuCauHoanTra'); // CHO_DUYET, DA_DUYET, TU_CHOI
            }
            if (!Schema::hasColumn('donHang', 'lyDoTuChoiHoanTra')) {
                $table->text('lyDoTuChoiHoanTra')->nullable()->after('trangThaiHoanHang');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            $columns = [
                'lyDoHoanTra',
                'ghiChuHoanTra',
                'hinhAnhHoanTra',
                'ngayYeuCauHoanTra',
                'trangThaiHoanHang',
                'lyDoTuChoiHoanTra'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('donHang', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

