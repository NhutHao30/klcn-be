<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\DonHang;
use App\Models\LichSuTrangThaiDonHang;

class PaymentWebhookController extends Controller
{
    /**
     * Webhook nhận biến động số dư từ SePay, Casso, PayOS hoặc ngân hàng
     */
    public function webhook(Request $request)
    {
        Log::info('Payment Webhook received:', $request->all());

        // Hỗ trợ cấu trúc payload của SePay, Casso, PayOS
        $content = $request->input('content') 
            ?? $request->input('description') 
            ?? $request->input('orderCode') 
            ?? $request->input('data.description') 
            ?? $request->input('data.orderCode') 
            ?? '';

        $amount = (float)($request->input('transferAmount') 
            ?? $request->input('amount') 
            ?? $request->input('data.amount') 
            ?? 0);

        // Trích xuất mã đơn hàng HD..., POS..., DHT... từ nội dung chuyển khoản
        $matchedCode = null;
        $normalizedContent = preg_replace('/\s+/', '', (string)$content);
        if (preg_match('/((?:HD|POS|DHT)\d{4,25})/i', (string)$content, $matches)) {
            $matchedCode = strtoupper($matches[1]);
        } elseif (preg_match('/((?:HD|POS|DHT)\d{4,25})/i', (string)$normalizedContent, $matches)) {
            $matchedCode = strtoupper($matches[1]);
        }

        // Lưu thêm cache theo số tiền vừa chuyển khoản (trong 3 phút) để bắt được cả trường hợp ngân hàng cắt/đổi nội dung CK tại POS
        if ($amount > 0) {
            Cache::put('paid_recent_amount_' . (int)round($amount), [
                'paid' => true,
                'amount' => $amount,
                'time' => now()->toIso8601String(),
                'content' => $content,
                'orderCode' => $matchedCode,
            ], now()->addMinutes(3));
        }

        if ($matchedCode) {
            // Lưu vào cache để frontend polling nhận được ngay (trong 1 giờ)
            Cache::put('paid_order_' . $matchedCode, [
                'paid' => true,
                'amount' => $amount,
                'time' => now()->toIso8601String(),
                'content' => $content
            ], now()->addHours(1));

            // Nếu đơn hàng đã tồn tại trong DB -> cập nhật trạng thái thanh toán ngay
            $donHang = DonHang::where('maDonHang', $matchedCode)->first();
            if ($donHang) {
                $donHang->trangThaiThanhToan = 1; // 1: Đã thanh toán
                $donHang->save();

                try {
                    LichSuTrangThaiDonHang::create([
                        'donHangId' => $donHang->id,
                        'trangThaiCu' => $donHang->trangThaiDonHang,
                        'trangThaiMoi' => $donHang->trangThaiDonHang,
                        'ghnStatusCode' => 'bank_webhook_paid',
                        'moTaChiTiet' => "Ngân hàng ghi nhận chuyển khoản thành công: " . number_format($amount, 0, ',', '.') . "đ. Nội dung: {$content}",
                        'nguonCapNhat' => 3, // Hệ thống / Ngân hàng
                    ]);
                } catch (\Exception $e) {}
            }

            return response()->json([
                'success' => true,
                'message' => "Đã ghi nhận thanh toán cho đơn hàng {$matchedCode}",
                'orderCode' => $matchedCode,
                'amount' => $amount
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook received but no order code matched in content.',
            'raw_content' => $content
        ]);
    }

    /**
     * Frontend polling kiểm tra xem đơn hàng đã được ngân hàng ghi nhận chuyển khoản chưa
     */
    public function checkStatus(Request $request, $orderCode)
    {
        $code = strtoupper(trim($orderCode));

        // 1. Kiểm tra cache webhook biến động số dư
        $cached = Cache::get('paid_order_' . $code);
        if ($cached && !empty($cached['paid'])) {
            return response()->json([
                'paid' => true,
                'source' => 'webhook',
                'amount' => $cached['amount'] ?? 0,
                'time' => $cached['time'] ?? now()->toIso8601String(),
                'message' => 'Ngân hàng đã xác nhận giao dịch thành công!'
            ]);
        }

        // 2. Kiểm tra trực tiếp trong DB
        $donHang = DonHang::where('maDonHang', $code)->first();
        if ($donHang && (int)$donHang->trangThaiThanhToan === 1) {
            return response()->json([
                'paid' => true,
                'source' => 'database',
                'amount' => (float)$donHang->tongThanhToan,
                'message' => 'Đơn hàng đã được xác nhận thanh toán thành công!'
            ]);
        }

        // 3. Fallback kiểm tra theo số tiền vừa chuyển khoản trong 3 phút gần nhất (hỗ trợ POS / QR khi nội dung CK bị ngân hàng rút gọn)
        $expectedAmount = (int)round((float)$request->query('amount', 0));
        if ($expectedAmount > 0) {
            $recentByAmount = Cache::get('paid_recent_amount_' . $expectedAmount);
            if ($recentByAmount && !empty($recentByAmount['paid'])) {
                $boundCode = $recentByAmount['orderCode'] ?? null;
                if (!$boundCode || strtoupper($boundCode) === $code) {
                    // Đánh dấu mã đơn hiện tại đã thanh toán và xóa cache tạm theo tiền để tránh trùng lặp
                    Cache::put('paid_order_' . $code, [
                        'paid' => true,
                        'amount' => $recentByAmount['amount'] ?? $expectedAmount,
                        'time' => $recentByAmount['time'] ?? now()->toIso8601String(),
                        'content' => $recentByAmount['content'] ?? ''
                    ], now()->addHours(1));
                    Cache::forget('paid_recent_amount_' . $expectedAmount);

                    return response()->json([
                        'paid' => true,
                        'source' => 'webhook_amount',
                        'amount' => $recentByAmount['amount'] ?? $expectedAmount,
                        'time' => $recentByAmount['time'] ?? now()->toIso8601String(),
                        'message' => 'Ngân hàng đã xác nhận giao dịch thành công!'
                    ]);
                }
            }
        }

        return response()->json([
            'paid' => false,
            'message' => 'Đang chờ giao dịch từ ngân hàng...'
        ]);
    }

    /**
     * Xác nhận hoặc giả lập thanh toán thành công ngay lập tức cho mã đơn hàng
     */
    public function simulatePayment(Request $request)
    {
        $code = strtoupper(trim((string)$request->input('orderCode')));
        $amount = (float)($request->input('amount') ?? 0);

        if (!$code) {
            return response()->json(['error' => 'Thiếu mã đơn hàng'], 400);
        }

        Cache::put('paid_order_' . $code, [
            'paid' => true,
            'amount' => $amount,
            'time' => now()->toIso8601String(),
            'content' => "Xác nhận chuyển khoản ngân hàng tự động cho {$code}"
        ], now()->addHours(1));

        $donHang = DonHang::where('maDonHang', $code)->first();
        if ($donHang) {
            $donHang->trangThaiThanhToan = 1;
            $donHang->save();
        }

        return response()->json([
            'success' => true,
            'paid' => true,
            'message' => "Đã ghi nhận chuyển khoản thành công cho đơn hàng #{$code}!",
            'orderCode' => $code
        ]);
    }
}

