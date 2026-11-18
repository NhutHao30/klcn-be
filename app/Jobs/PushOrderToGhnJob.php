<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\DonHang;

class PushOrderToGhnJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $orderId;
    protected $wardCode;
    protected $districtId;
    protected $addressDisplay;

    /**
     * Create a new job instance.
     */
    public function __construct($orderId, $wardCode = null, $districtId = null, $addressDisplay = null)
    {
        $this->orderId = $orderId;
        $this->wardCode = $wardCode;
        $this->districtId = $districtId;
        $this->addressDisplay = $addressDisplay;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $order = DonHang::with(['chitiet.sanpham', 'cuahang', 'nguoidung'])->find($this->orderId);
        if (!$order) {
            $order = DonHang::with(['chitiet.sanpham', 'cuahang', 'nguoidung'])->where('maDonHang', $this->orderId)->first();
        }

        if (!$order) {
            Log::warning("PushOrderToGhnJob: Không tìm thấy đơn hàng #{$this->orderId}");
            return;
        }

        if (!empty($order->maVanDonGhn)) {
            Log::info("PushOrderToGhnJob: Đơn hàng #{$order->maDonHang} đã có mã vận đơn GHN ({$order->maVanDonGhn})");
            return;
        }

        $res = \App\Http\Controllers\Api\GhnController::createShippingOrderForDonHang(
            $order,
            $this->wardCode ?? $order->toWardCode,
            $this->districtId ?? $order->toDistrictId
        );

        if ($res['success']) {
            Log::info("PushOrderToGhnJob thành công cho HD #{$order->maDonHang}: {$res['order_code']}");
        } else {
            Log::warning("PushOrderToGhnJob thất bại cho HD #{$order->maDonHang}: {$res['message']}");
        }
    }
}
