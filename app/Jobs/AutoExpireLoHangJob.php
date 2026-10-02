<?php

namespace App\Jobs;

use App\Services\LoHangService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Job tự động hóa xử lý lô hàng hết hạn mỗi ngày.
 *
 * Chạy qua: php artisan schedule:run (lúc 00:01)
 *
 * Tác vụ:
 *  1. Quét lô có HSD < hôm nay, TRANGTHAI = 'con_hang'
 *  2. Tạo biên bản hủy tự động cho từng lô
 *  3. Trừ SOLUONG_CON và cập nhật TRANGTHAI = 'het_han'
 *  4. Trừ tồn kho tonkho_cuahang
 *  5. Ghi inventory_movement loại DAMAGE
 *  6. Log kết quả
 */
class AutoExpireLoHangJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function handle(LoHangService $loHangService): void
    {
        Log::info('[AutoExpireLoHangJob] Bắt đầu quét lô hết hạn...');

        try {
            $result = $loHangService->autoExpireLo();

            Log::info('[AutoExpireLoHangJob] Hoàn thành.', [
                'expired_lots'     => $result['expired_count'],
                'total_qty_disposed' => $result['total_quantity_disposed'],
                'executed_at'      => now()->toDateTimeString(),
            ]);

            // Ghi vào bảng nhat_ky_he_thong nếu có lô bị hủy
            if ($result['expired_count'] > 0) {
                DB::table('nhat_ky_he_thong')->insert([
                    'HANHONG'     => "AutoExpire: {$result['expired_count']} lô hàng hết hạn đã bị hủy tự động. Tổng SL: {$result['total_quantity_disposed']}",
                    'THOIGIAN'    => now(),
                    'USERNAME'    => 'SYSTEM',
                    'createdAt'  => now(),
                    'updatedAt'  => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[AutoExpireLoHangJob] Lỗi: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
