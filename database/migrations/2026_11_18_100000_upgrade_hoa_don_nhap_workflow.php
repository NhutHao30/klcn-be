<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hdnTable = Schema::hasTable('hoaDonNhap') ? 'hoaDonNhap' : 'hoadonnhap';
        $ctTable = Schema::hasTable('chiTietHoaDonNhap') ? 'chiTietHoaDonNhap' : 'chitiethoadonnhap';

        if (Schema::hasTable($hdnTable)) {
            Schema::table($hdnTable, function (Blueprint $table) use ($hdnTable) {
                if (!Schema::hasColumn($hdnTable, 'maTrangThai')) {
                    $table->string('maTrangThai', 30)->nullable()->after('trangThai');
                }
                if (!Schema::hasColumn($hdnTable, 'nguoiTaoRole')) {
                    $table->string('nguoiTaoRole', 30)->nullable()->after('nguoiNhapId');
                }
                if (!Schema::hasColumn($hdnTable, 'nguoiDuyetId')) {
                    $table->unsignedBigInteger('nguoiDuyetId')->nullable()->after('nguoiTaoRole');
                }
                if (!Schema::hasColumn($hdnTable, 'thoiGianDuyet')) {
                    $table->timestamp('thoiGianDuyet')->nullable()->after('nguoiDuyetId');
                }
                if (!Schema::hasColumn($hdnTable, 'lyDoTuChoi')) {
                    $table->text('lyDoTuChoi')->nullable()->after('thoiGianDuyet');
                }
                if (!Schema::hasColumn($hdnTable, 'tonKhoDaCong')) {
                    $table->boolean('tonKhoDaCong')->default(false)->after('lyDoTuChoi');
                }
            });

            // Backfill dữ liệu cũ để không ảnh hưởng các phiếu đã hoàn tất trước đây
            DB::table($hdnTable)
                ->whereNull('maTrangThai')
                ->where('trangThai', 1)
                ->update([
                    'maTrangThai' => 'HOAN_TAT',
                    'tonKhoDaCong' => true,
                ]);

            DB::table($hdnTable)
                ->whereNull('maTrangThai')
                ->where('trangThai', 2)
                ->update([
                    'maTrangThai' => 'TU_CHOI',
                    'tonKhoDaCong' => false,
                ]);

            DB::table($hdnTable)
                ->whereNull('maTrangThai')
                ->update([
                    'maTrangThai' => 'CHO_DUYET',
                    'tonKhoDaCong' => false,
                ]);
        }

        if (Schema::hasTable($ctTable)) {
            Schema::table($ctTable, function (Blueprint $table) use ($ctTable) {
                if (!Schema::hasColumn($ctTable, 'soLuongThucNhan')) {
                    $table->integer('soLuongThucNhan')->nullable()->after('soLuong');
                }
                if (!Schema::hasColumn($ctTable, 'ghiChuChenhLech')) {
                    $table->string('ghiChuChenhLech', 500)->nullable()->after('soLuongThucNhan');
                }
            });

            // Backfill soLuongThucNhan = soLuong cho các phiếu cũ đã hoàn tất
            if (Schema::hasTable($hdnTable)) {
                $completedIds = DB::table($hdnTable)->where('trangThai', 1)->pluck('id')->all();
                if (!empty($completedIds)) {
                    foreach (array_chunk($completedIds, 500) as $chunk) {
                        DB::table($ctTable)
                            ->whereIn('hoaDonNhapId', $chunk)
                            ->whereNull('soLuongThucNhan')
                            ->update(['soLuongThucNhan' => DB::raw('soLuong')]);
                    }
                }
            }
        }

        if (!Schema::hasTable('purchase_order_signatures')) {
            Schema::create('purchase_order_signatures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('hoaDonNhapId')->index();
                $table->string('vaiTroKy', 50);
                $table->unsignedBigInteger('nguoiKyId')->nullable();
                $table->string('tenNguoiKy')->nullable();
                $table->longText('chuKyAnh')->nullable();
                $table->string('ghiChu', 500)->nullable();
                $table->timestamp('thoiGianKy')->nullable();
                $table->timestamps();

                $table->unique(['hoaDonNhapId', 'vaiTroKy'], 'uq_po_sig_hdn_role');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_signatures');

        $ctTable = Schema::hasTable('chiTietHoaDonNhap') ? 'chiTietHoaDonNhap' : 'chitiethoadonnhap';
        if (Schema::hasTable($ctTable)) {
            Schema::table($ctTable, function (Blueprint $table) use ($ctTable) {
                $cols = array_filter(['soLuongThucNhan', 'ghiChuChenhLech'], fn ($c) => Schema::hasColumn($ctTable, $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        $hdnTable = Schema::hasTable('hoaDonNhap') ? 'hoaDonNhap' : 'hoadonnhap';
        if (Schema::hasTable($hdnTable)) {
            Schema::table($hdnTable, function (Blueprint $table) use ($hdnTable) {
                $cols = array_filter(
                    ['maTrangThai', 'nguoiTaoRole', 'nguoiDuyetId', 'thoiGianDuyet', 'lyDoTuChoi', 'tonKhoDaCong'],
                    fn ($c) => Schema::hasColumn($hdnTable, $c)
                );
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
