<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            if (!Schema::hasColumn('donHang', 'lyDoHuy')) {
                $table->string('lyDoHuy', 255)->nullable()->after('ghiChu');
            }
            if (!Schema::hasColumn('donHang', 'ghiChuHuy')) {
                $table->text('ghiChuHuy')->nullable()->after('lyDoHuy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('donHang', function (Blueprint $table) {
            if (Schema::hasColumn('donHang', 'lyDoHuy')) {
                $table->dropColumn('lyDoHuy');
            }
            if (Schema::hasColumn('donHang', 'ghiChuHuy')) {
                $table->dropColumn('ghiChuHuy');
            }
        });
    }
};

