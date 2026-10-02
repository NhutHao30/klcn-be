<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmationMail;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;

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

        $donHang = DonHang::with(['chitiet.sanpham', 'nguoidung'])
            ->where('maDonHang', $this->mahd)
            ->orWhere('id', $this->mahd)
            ->first();

        $items = [];
        if ($donHang && $donHang->chitiet) {
            foreach ($donHang->chitiet as $ct) {
                $sp = $ct->sanpham;
                $items[] = [
                    'name' => $sp ? ($sp->tenSanPham ?? $sp->ten_san_pham) : ('Sản phẩm #' . $ct->sanPhamId),
                    'quantity' => $ct->soLuong,
                    'price' => $ct->donGia
                ];
            }
        }

        $pmName = 'Tiền mặt khi nhận hàng (COD)';
        if ($donHang) {
            if ($donHang->phuongThucThanhToan == 1) $pmName = 'Chuyển khoản VietQR';
            if ($donHang->phuongThucThanhToan == 2) $pmName = 'Ví MoMo';
        }

        $orderData = [
            'mahd' => $this->mahd,
            'customer_name' => $this->hoten,
            'date' => $donHang ? ($donHang->createdAt ? $donHang->createdAt->format('d/m/Y H:i:s') : now()->format('d/m/Y H:i:s')) : now()->format('d/m/Y H:i:s'),
            'payment_method' => $pmName,
            'address' => $donHang ? $donHang->diaChiGiaoHang : 'N/A',
            'items' => $items,
            'total' => $this->tongTien,
            'discount' => $donHang ? $donHang->soTienGiam : 0
        ];

        try {
            Mail::to($this->email)->send(new OrderConfirmationMail($orderData));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi gửi email xác nhận đơn hàng: ' . $e->getMessage());
        }
    }
}
