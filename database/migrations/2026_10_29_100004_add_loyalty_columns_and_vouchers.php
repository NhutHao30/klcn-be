<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nguoidung', function (Blueprint $table) {
            if (!Schema::hasColumn('nguoidung', 'diemTichLuy')) {
                $table->integer('diemTichLuy')->default(0)->after('loaiNguoiDung');
            }
            if (!Schema::hasColumn('nguoidung', 'tongChiTieu')) {
                $table->decimal('tongChiTieu', 15, 2)->default(0)->after('diemTichLuy');
            }
            if (!Schema::hasColumn('nguoidung', 'hangThanhVien')) {
                $table->string('hangThanhVien', 50)->default('Đồng')->after('tongChiTieu');
            }
        });

        // Đồng bộ lại điểm tích lũy và hạng thành viên cho tất cả người dùng hiện có từ các đơn hoàn tất
        $users = DB::table('nguoidung')->get();
        $orderTable = Schema::hasTable('donHang') ? 'donHang' : (Schema::hasTable('donhang') ? 'donhang' : null);
        if ($orderTable) {
            $userCol = Schema::hasColumn($orderTable, 'nguoidungId') ? 'nguoidungId' : 'nguoidung_id';
            $statusCol = Schema::hasColumn($orderTable, 'trangThaiDonHang') ? 'trangThaiDonHang' : 'trang_thai_don_hang';
            $amountCol = Schema::hasColumn($orderTable, 'tongThanhToan') ? 'tongThanhToan' : 'tong_thanhtoan';

            foreach ($users as $u) {
                $totalSpent = (float) DB::table($orderTable)
                    ->where($userCol, $u->id)
                    ->whereIn($statusCol, [4, 5])
                    ->sum($amountCol);

                $points = (int) floor($totalSpent / 10000);
                $rank = 'Đồng';
                if ($points >= 1000) $rank = 'Thách đấu';
                elseif ($points >= 500) $rank = 'Kim cương';
                elseif ($points >= 200) $rank = 'Vàng';
                elseif ($points >= 50) $rank = 'Bạc';

                DB::table('nguoidung')->where('id', $u->id)->update([
                    'tongChiTieu' => $totalSpent,
                    'diemTichLuy' => $points,
                    'hangThanhVien' => $rank,
                ]);
            }
        }

        // Gia hạn các voucher hiện có và bổ sung thêm các voucher mới để khách hàng lưu vào Ví Voucher
        if (Schema::hasTable('voucher')) {
            $voucherUpdate = [];
            if (Schema::hasColumn('voucher', 'NGAY_BAT_DAU')) $voucherUpdate['NGAY_BAT_DAU'] = '2026-10-01 00:00:00';
            if (Schema::hasColumn('voucher', 'NGAY_KET_THUC')) $voucherUpdate['NGAY_KET_THUC'] = '2026-12-31 23:59:59';
            if (Schema::hasColumn('voucher', 'TRANG_THAI')) $voucherUpdate['TRANG_THAI'] = 1;
            if (!empty($voucherUpdate)) {
                DB::table('voucher')->update($voucherUpdate);
            }

            $sampleVouchers = [
                [
                    'MA_VOUCHER' => 'GIAM20K',
                    'TEN_VOUCHER' => 'Giảm ngay 20.000đ cho đơn từ 100.000đ',
                    'LOAI_GIAM' => 'TIEN_MAT',
                    'GIA_TRI' => 20000,
                    'DON_TOI_THIEU' => 100000,
                    'GIAM_TOI_DA' => 20000,
                    'SO_LUONG_GIOI_HAN' => 100,
                    'SO_LUONG_DA_DUNG' => 0,
                    'NGAY_BAT_DAU' => '2026-10-01 00:00:00',
                    'NGAY_KET_THUC' => '2026-12-31 23:59:59',
                    'TRANG_THAI' => 1,
                ],
                [
                    'MA_VOUCHER' => 'FREESHIP30K',
                    'TEN_VOUCHER' => 'Hỗ trợ phí giao bánh 30.000đ (Đơn từ 150K)',
                    'LOAI_GIAM' => 'TIEN_MAT',
                    'GIA_TRI' => 30000,
                    'DON_TOI_THIEU' => 150000,
                    'GIAM_TOI_DA' => 30000,
                    'SO_LUONG_GIOI_HAN' => 100,
                    'SO_LUONG_DA_DUNG' => 0,
                    'NGAY_BAT_DAU' => '2026-10-01 00:00:00',
                    'NGAY_KET_THUC' => '2026-12-31 23:59:59',
                    'TRANG_THAI' => 1,
                ],
                [
                    'MA_VOUCHER' => 'BANHKEM15',
                    'TEN_VOUCHER' => 'Ưu đãi 15% tối đa 50.000đ cho đơn Bánh Kem',
                    'LOAI_GIAM' => 'PHAN_TRAM',
                    'GIA_TRI' => 15,
                    'DON_TOI_THIEU' => 200000,
                    'GIAM_TOI_DA' => 50000,
                    'SO_LUONG_GIOI_HAN' => 80,
                    'SO_LUONG_DA_DUNG' => 0,
                    'NGAY_BAT_DAU' => '2026-10-01 00:00:00',
                    'NGAY_KET_THUC' => '2026-12-31 23:59:59',
                    'TRANG_THAI' => 1,
                ],
                [
                    'MA_VOUCHER' => 'THANHVIENVIP',
                    'TEN_VOUCHER' => 'Voucher Đặc Quyền Thành Viên - Giảm 50.000đ',
                    'LOAI_GIAM' => 'TIEN_MAT',
                    'GIA_TRI' => 50000,
                    'DON_TOI_THIEU' => 300000,
                    'GIAM_TOI_DA' => 50000,
                    'SO_LUONG_GIOI_HAN' => 50,
                    'SO_LUONG_DA_DUNG' => 0,
                    'NGAY_BAT_DAU' => '2026-10-01 00:00:00',
                    'NGAY_KET_THUC' => '2026-12-31 23:59:59',
                    'TRANG_THAI' => 1,
                ],
            ];

            foreach ($sampleVouchers as $v) {
                $exists = DB::table('voucher')->where('MA_VOUCHER', $v['MA_VOUCHER'])->exists();
                if (!$exists) {
                    DB::table('voucher')->insert($v);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('nguoidung', function (Blueprint $table) {
            if (Schema::hasColumn('nguoidung', 'hangThanhVien')) {
                $table->dropColumn('hangThanhVien');
            }
            if (Schema::hasColumn('nguoidung', 'tongChiTieu')) {
                $table->dropColumn('tongChiTieu');
            }
            if (Schema::hasColumn('nguoidung', 'diemTichLuy')) {
                $table->dropColumn('diemTichLuy');
            }
        });
    }
};

