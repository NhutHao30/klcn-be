<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            if (!Schema::hasColumn('donHang', 'soTienHoan')) {
                $table->double('soTienHoan')->nullable()->after('tenNguoiNhanHoanTien');
            }
            if (!Schema::hasColumn('donHang', 'maGiaoDichHoanTien')) {
                $table->string('maGiaoDichHoanTien', 100)->nullable()->after('soTienHoan');
            }
            if (!Schema::hasColumn('donHang', 'minhChungHoanTien')) {
                $table->text('minhChungHoanTien')->nullable()->after('ghiChuHoanTien');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            $cols = ['soTienHoan', 'maGiaoDichHoanTien', 'minhChungHoanTien'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('donHang', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

