<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmationMail;
use App\Models\HdBan;
use App\Models\ChiTietHdBan;

class SendOrderEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $email;
    protected $mahd;
    protected $tongTien;
    protected $hoten;

    /**
     * Create a new job instance.
     */
    public function __construct($email, $mahd, $tongTien, $hoten)
    {
        $this->email = $email;
        $this->mahd = $mahd;
        $this->tongTien = $tongTien;
        $this->hoten = $hoten;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if (empty($this->email)) {
            return;
        }

        // Lấy chi tiết đơn hàng để truyền vào view
        $hd = HdBan::find($this->mahd);
        $chitiet = ChiTietHdBan::with('sanpham')->where('MAHD', $this->mahd)->get();

        $items = [];
        foreach ($chitiet as $ct) {
            $items[] = [
                'name' => $ct->sanpham ? $ct->sanpham->TENSP : 'Sản phẩm',
                'quantity' => $ct->SOLUONG,
                'price' => $ct->DONGIA
            ];
        }

        $orderData = [
            'mahd' => $this->mahd,
            'customer_name' => $this->hoten,
            'date' => $hd ? \Carbon\Carbon::parse($hd->created_at ?? $hd->NGAYLAP)->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s'),
            'payment_method' => $hd ? $hd->PHUONGTHUCTHANHTOAN : 'N/A',
            'address' => $hd ? preg_replace('/\[GHN_WARD:.*?\]/', '', $hd->GHICHU) : 'N/A',
            'items' => $items,
            'total' => $this->tongTien,
            'discount' => $hd ? $hd->SOTIEN_GIAM_VOUCHER : 0
        ];

        Mail::to($this->email)->send(new OrderConfirmationMail($orderData));
    }
}
