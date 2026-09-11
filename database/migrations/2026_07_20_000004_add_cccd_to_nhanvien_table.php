<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCccdToNhanvienTable extends Migration
{
    public function up()
    {
        Schema::table('nhanvien', function (Blueprint $table) {
            if (!Schema::hasColumn('nhanvien', 'CCCD')) {
                $table->string('CCCD', 20)->nullable()->after('USERNAME');
            }
        });
    }

    public function down()
    {
        Schema::table('nhanvien', function (Blueprint $table) {
            if (Schema::hasColumn('nhanvien', 'CCCD')) {
                $table->dropColumn('CCCD');
            }
        });
    }
}
