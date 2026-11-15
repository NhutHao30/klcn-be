<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistoricalSalesAndImportSeeder extends Seeder
{
    /**
     * Tạo dữ liệu mẫu bán hàng & nhập hàng trải đều các ngày trong tháng và các tháng trong năm.
     * - Nhập hàng: tối đa 50 sản phẩm/lô/mỗi sản phẩm.
     * - Bán hàng ngày thường: vài trăm nghìn đến vài triệu/ngày.
     * - Ngày lễ: cao hơn ngày thường nhưng không vượt quá vài chục triệu/ngày.
     */
    public function run(): void
    {
        $cuaHangTable = Schema::hasTable('cuaHang') ? 'cuaHang' : 'cuahang';
        $sanPhamTable = Schema::hasTable('sanPham') ? 'sanPham' : 'sanpham';
        $nhaCungCapTable = Schema::hasTable('nhaCungCap') ? 'nhaCungCap' : 'nhacungcap';
        $nguoiDungTable = Schema::hasTable('nguoidung') ? 'nguoidung' : 'nguoiDung';
        $hoaDonNhapTable = Schema::hasTable('hoaDonNhap') ? 'hoaDonNhap' : 'hoadonnhap';
        $chiTietHdnTable = Schema::hasTable('chiTietHoaDonNhap') ? 'chiTietHoaDonNhap' : 'chitiethoadonnhap';
        $loHangTable = Schema::hasTable('loHang') ? 'loHang' : 'lohang';
        $donHangTable = Schema::hasTable('donHang') ? 'donHang' : 'donhang';
        $chiTietDhTable = Schema::hasTable('chiTietDonHang') ? 'chiTietDonHang' : 'chitietdonhang';

        $stores = DB::table($cuaHangTable)->get();
        $products = DB::table($sanPhamTable)->where('giaBan', '>', 0)->get();
        $supplier = DB::table($nhaCungCapTable)->first();
        $staffUser = DB::table($nguoiDungTable)->first();

        if ($stores->isEmpty() || $products->isEmpty() || !$supplier || !$staffUser) {
            $this->command?->warn('Thiếu dữ liệu gốc (cuaHang, sanPham, nhaCungCap hoặc nguoidung). Bỏ qua HistoricalSalesAndImportSeeder.');
            return;
        }

        // Lấy danh sách khách hàng nếu có, fallback về staffUser->id
        $customerIds = DB::table($nguoiDungTable)->pluck('id')->all();
        if (empty($customerIds)) {
            $customerIds = [$staffUser->id];
        }

        $productList = $products->values()->all();
        $productCount = count($productList);

        DB::transaction(function () use (
            $stores,
            $productList,
            $productCount,
            $supplier,
            $staffUser,
            $customerIds,
            $hoaDonNhapTable,
            $chiTietHdnTable,
            $loHangTable,
            $donHangTable,
            $chiTietDhTable
        ) {
            // Dọn dữ liệu mẫu SEED cũ để có thể chạy lại an toàn không bị trùng
            $oldSeedOrderIds = DB::table($donHangTable)
                ->where('maDonHang', 'like', 'SEED-%')
                ->pluck('id')
                ->all();
            if (!empty($oldSeedOrderIds)) {
                foreach (array_chunk($oldSeedOrderIds, 500) as $chunk) {
                    DB::table($chiTietDhTable)->whereIn('donHangId', $chunk)->delete();
                    DB::table($donHangTable)->whereIn('id', $chunk)->delete();
                }
            }

            $oldSeedHdnIds = DB::table($hoaDonNhapTable)
                ->where('maHoaDonNhap', 'like', 'HDN-SEED-%')
                ->pluck('id')
                ->all();
            if (!empty($oldSeedHdnIds)) {
                foreach (array_chunk($oldSeedHdnIds, 500) as $chunk) {
                    DB::table($chiTietHdnTable)->whereIn('hoaDonNhapId', $chunk)->delete();
                    DB::table($hoaDonNhapTable)->whereIn('id', $chunk)->delete();
                }
            }
            DB::table($loHangTable)->where('maLoHang', 'like', 'LH-SEED-%')->delete();

            $today = Carbon::today();
            $startDate = $today->copy()->subMonths(11)->startOfMonth();

            $importInvoiceCount = 0;
            $importBatchCount = 0;
            $orderCount = 0;
            $totalRevenueSeeded = 0;

            $orderDetailsBuffer = [];
            $importDetailsBuffer = [];

            // 1. TẠO DỮ LIỆU NHẬP HÀNG (trải đều các ngày trong tháng, tối đa 50 mỗi sản phẩm)
            $cursorMonth = $startDate->copy()->startOfMonth();
            while ($cursorMonth->lte($today)) {
                $importDays = [2, 7, 12, 17, 22, 27];
                foreach ($stores as $store) {
                    foreach ($importDays as $dayNum) {
                        if ($dayNum > $cursorMonth->daysInMonth) {
                            continue;
                        }
                        $importDate = $cursorMonth->copy()->day($dayNum)->setTime(mt_rand(7, 10), mt_rand(0, 59), 0);
                        if ($importDate->gt(Carbon::now())) {
                            continue;
                        }

                        $numItems = min($productCount, mt_rand(3, 5));
                        $pickedIndices = (array) array_rand($productList, $numItems);
                        $hdnTotal = 0;
                        $pendingBatches = [];

                        foreach ($pickedIndices as $idx) {
                            $prod = $productList[$idx];
                            $qty = mt_rand(10, 50); // Tối đa 50 cho mỗi sản phẩm
                            $unitCost = (int) round(((float) $prod->giaBan) * (mt_rand(55, 68) / 100), -2);
                            if ($unitCost <= 0) {
                                $unitCost = 15000;
                            }
                            $lineTotal = $qty * $unitCost;
                            $hdnTotal += $lineTotal;

                            $isRecent = $importDate->gte($today->copy()->subDays(30));
                            $remaining = $isRecent ? mt_rand((int) floor($qty * 0.2), $qty) : mt_rand(0, (int) floor($qty * 0.25));

                            $pendingBatches[] = [
                                'sanPhamId' => $prod->id,
                                'qty' => $qty,
                                'remaining' => $remaining,
                                'unitCost' => $unitCost,
                                'lineTotal' => $lineTotal,
                            ];
                        }

                        $maHdn = sprintf('HDN-SEED-%d-%s-%02d', $store->id, $importDate->format('Ymd'), mt_rand(10, 99));
                        $hdnId = DB::table($hoaDonNhapTable)->insertGetId([
                            'maHoaDonNhap' => $maHdn,
                            'nhaCungCapId' => $supplier->id,
                            'cuaHangId' => $store->id,
                            'nguoiNhapId' => $staffUser->id,
                            'ngayNhap' => $importDate->toDateTimeString(),
                            'tongTien' => $hdnTotal,
                            'ghiChu' => 'Nhập hàng định kỳ chi nhánh #' . $store->id,
                            'trangThai' => 1,
                            'createdAt' => $importDate->toDateTimeString(),
                            'updatedAt' => $importDate->toDateTimeString(),
                        ]);
                        $importInvoiceCount++;

                        foreach ($pendingBatches as $bIdx => $b) {
                            $maLo = sprintf('LH-SEED-%d-%s-%02d%d', $store->id, $importDate->format('Ymd'), $bIdx + 1, mt_rand(1, 9));
                            $loHangId = DB::table($loHangTable)->insertGetId([
                                'maLoHang' => $maLo,
                                'sanPhamId' => $b['sanPhamId'],
                                'cuaHangId' => $store->id,
                                'nhaCungCapId' => $supplier->id,
                                'soLuongNhap' => $b['qty'],
                                'soLuongCon' => $b['remaining'],
                                'ngaySanXuat' => $importDate->copy()->subDays(1)->toDateString(),
                                'hsd' => $importDate->copy()->addDays(mt_rand(15, 35))->toDateString(),
                                'giaNhap' => $b['unitCost'],
                                'trangThai' => $b['remaining'] > 0 ? 0 : 1,
                                'createdAt' => $importDate->toDateTimeString(),
                                'updatedAt' => $importDate->toDateTimeString(),
                            ]);
                            $importBatchCount++;

                            $importDetailsBuffer[] = [
                                'hoaDonNhapId' => $hdnId,
                                'loHangId' => $loHangId,
                                'sanPhamId' => $b['sanPhamId'],
                                'soLuong' => $b['qty'],
                                'donGiaNhap' => $b['unitCost'],
                                'thanhTien' => $b['lineTotal'],
                            ];
                        }
                    }
                }
                $cursorMonth->addMonth();
            }

            if (!empty($importDetailsBuffer)) {
                foreach (array_chunk($importDetailsBuffer, 500) as $chunk) {
                    DB::table($chiTietHdnTable)->insert($chunk);
                }
            }

            // 2. TẠO DỮ LIỆU BÁN HÀNG (trải đều mọi ngày trong 12 tháng)
            $cursorDay = $startDate->copy();
            $storeCount = max(1, $stores->count());

            while ($cursorDay->lte($today)) {
                $isHoliday = $this->isVietnameseHoliday($cursorDay);
                $isWeekend = $cursorDay->isWeekend();

                // Mục tiêu doanh thu mỗi chi nhánh để tổng toàn hệ thống:
                // - Ngày thường: ~400k - 3.8M/ngày (vài trăm hoặc vài triệu)
                // - Ngày lễ: ~7M - 22M/ngày (cao hơn ngày thường, không vượt quá vài chục triệu/ngày)
                if ($isHoliday) {
                    $dailySystemMin = 7000000;
                    $dailySystemMax = 21000000;
                } elseif ($isWeekend) {
                    $dailySystemMin = 1200000;
                    $dailySystemMax = 4200000;
                } else {
                    $dailySystemMin = 450000;
                    $dailySystemMax = 3000000;
                }

                $perStoreTargetMin = (int) floor($dailySystemMin / $storeCount);
                $perStoreTargetMax = (int) floor($dailySystemMax / $storeCount);

                foreach ($stores as $store) {
                    $storeTarget = mt_rand($perStoreTargetMin, max($perStoreTargetMin, $perStoreTargetMax));
                    $accumulated = 0;
                    $dayOrderSeq = 0;
                    $maxOrdersForStore = $isHoliday ? 16 : 6;

                    while ($accumulated < $storeTarget && $dayOrderSeq < $maxOrdersForStore) {
                        $dayOrderSeq++;
                        $orderTime = $cursorDay->copy()->setTime(mt_rand(8, 21), mt_rand(0, 59), mt_rand(0, 59));
                        if ($orderTime->gt(Carbon::now())) {
                            $orderTime = Carbon::now()->subMinutes(mt_rand(5, 60));
                        }

                        $numLines = $isHoliday ? mt_rand(2, 4) : mt_rand(1, 3);
                        $numLines = min($numLines, $productCount);
                        $pickedIndices = (array) array_rand($productList, $numLines);

                        $tongTienHang = 0;
                        $linesForOrder = [];

                        foreach ($pickedIndices as $idx) {
                            $prod = $productList[$idx];
                            $qty = $isHoliday ? mt_rand(1, 4) : mt_rand(1, 2);
                            $price = (float) $prod->giaBan;
                            $thanhTien = $qty * $price;
                            $tongTienHang += $thanhTien;

                            $linesForOrder[] = [
                                'sanPhamId' => $prod->id,
                                'soLuong' => $qty,
                                'donGia' => $price,
                                'thanhTien' => $thanhTien,
                            ];
                        }

                        $isPos = mt_rand(1, 100) <= 65;
                        $loaiDonHang = $isPos ? 1 : 0;
                        $phuongThuc = $isPos
                            ? (mt_rand(1, 100) <= 60 ? 0 : mt_rand(1, 2))
                            : mt_rand(0, 2);
                        $customerId = $customerIds[array_rand($customerIds)];

                        $maDonHang = sprintf(
                            'SEED-%d-%s-%02d',
                            $store->id,
                            $cursorDay->format('Ymd'),
                            $dayOrderSeq
                        );

                        $orderId = DB::table($donHangTable)->insertGetId([
                            'maDonHang' => $maDonHang,
                            'nguoidungId' => $customerId,
                            'cuaHangId' => $store->id,
                            'loaiDonHang' => $loaiDonHang,
                            'tongTienHang' => $tongTienHang,
                            'soTienGiam' => 0,
                            'phiVanChuyen' => 0,
                            'tongThanhToan' => $tongTienHang,
                            'phuongThucThanhToan' => $phuongThuc,
                            'trangThaiThanhToan' => 1,
                            'trangThaiDonHang' => 5,
                            'ghiChu' => $isHoliday ? 'Đơn hàng dịp lễ' : 'Đơn hàng bán lẻ',
                            'createdAt' => $orderTime->toDateTimeString(),
                            'updatedAt' => $orderTime->toDateTimeString(),
                        ]);

                        foreach ($linesForOrder as $line) {
                            $line['donHangId'] = $orderId;
                            $orderDetailsBuffer[] = $line;
                        }

                        $accumulated += $tongTienHang;
                        $totalRevenueSeeded += $tongTienHang;
                        $orderCount++;
                    }
                }

                $cursorDay->addDay();
            }

            if (!empty($orderDetailsBuffer)) {
                foreach (array_chunk($orderDetailsBuffer, 500) as $chunk) {
                    DB::table($chiTietDhTable)->insert($chunk);
                }
            }

            $this->command?->info(sprintf(
                'HistoricalSalesAndImportSeeder hoàn tất: %d phiếu nhập (%d lô hàng, SL <= 50/SP), %d đơn hàng, tổng doanh thu mẫu %s đ.',
                $importInvoiceCount,
                $importBatchCount,
                $orderCount,
                number_format($totalRevenueSeeded, 0, ',', '.')
            ));
        });
    }

    private function isVietnameseHoliday(Carbon $date): bool
    {
        $md = $date->format('m-d');
        $fixedHolidays = [
            '01-01', // Tết Dương lịch
            '02-14', // Valentine
            '03-08', // Quốc tế Phụ nữ
            '04-30', // Giải phóng miền Nam
            '05-01', // Quốc tế Lao động
            '06-01', // Quốc tế Thiếu nhi
            '09-02', // Quốc khánh
            '10-20', // Phụ nữ Việt Nam
            '11-20', // Nhà giáo Việt Nam
            '12-24', // Giáng sinh
            '12-25', // Giáng sinh
        ];

        if (in_array($md, $fixedHolidays, true)) {
            return true;
        }

        // Cao điểm cận Tết (25/01 - 03/02) và mùa Trung Thu (15/09 - 22/09)
        if (($md >= '01-25' && $md <= '02-03') || ($md >= '09-15' && $md <= '09-22')) {
            return true;
        }

        return false;
    }
}

