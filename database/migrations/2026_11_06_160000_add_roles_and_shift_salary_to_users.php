<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Đảm bảo vai trò ACCOUNTANT (7) và DIRECTOR (8) tồn tại trong bảng vaitro
        $vaitroTable = Schema::hasTable('vaiTro') ? 'vaiTro' : 'vaitro';
        if (Schema::hasTable($vaitroTable)) {
            $tenCol = Schema::hasColumn($vaitroTable, 'tenVaiTro') ? 'tenVaiTro' : 'ten_vaitro';
            $moTaCol = Schema::hasColumn($vaitroTable, 'moTa') ? 'moTa' : 'mo_ta';
            $createdCol = Schema::hasColumn($vaitroTable, 'createdAt') ? 'createdAt' : 'created_at';
            $updatedCol = Schema::hasColumn($vaitroTable, 'updatedAt') ? 'updatedAt' : 'updated_at';
            $now = Carbon::now();

            $newRoles = [
                ['id' => 7, 'name' => 'ACCOUNTANT', 'desc' => 'Nhân viên kế toán'],
                ['id' => 8, 'name' => 'DIRECTOR', 'desc' => 'Ban Giám đốc'],
            ];

            foreach ($newRoles as $r) {
                $exists = DB::table($vaitroTable)->where('id', $r['id'])->first();
                if (!$exists) {
                    $insertData = [
                        'id' => $r['id'],
                        $tenCol => $r['name'],
                        $moTaCol => $r['desc'],
                    ];
                    if (Schema::hasColumn($vaitroTable, $createdCol)) {
                        $insertData[$createdCol] = $now;
                    }
                    if (Schema::hasColumn($vaitroTable, $updatedCol)) {
                        $insertData[$updatedCol] = $now;
                    }
                    DB::table($vaitroTable)->insert($insertData);
                }
            }
        }

        // 2. Thêm cột caLamViec và luong vào bảng nguoidung
        if (Schema::hasTable('nguoidung')) {
            Schema::table('nguoidung', function (Blueprint $table) {
                if (!Schema::hasColumn('nguoidung', 'caLamViec')) {
                    $table->string('caLamViec', 50)->default('Ca Sáng')->nullable();
                }
                if (!Schema::hasColumn('nguoidung', 'luong')) {
                    $table->decimal('luong', 15, 2)->default(0)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nguoidung')) {
            Schema::table('nguoidung', function (Blueprint $table) {
                if (Schema::hasColumn('nguoidung', 'caLamViec')) {
                    $table->dropColumn('caLamViec');
                }
                if (Schema::hasColumn('nguoidung', 'luong')) {
                    $table->dropColumn('luong');
                }
            });
        }
    }
};
