<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GhnController extends Controller
{
    private $apiUrl;

    public function __construct()
    {
        $this->apiUrl = env('GHN_API_URL', 'https://online-gateway.ghn.vn/shiip/public-api');
    }

    public function getProvinces()
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Token' => env('GHN_API_TOKEN'),
        ])->get($this->apiUrl . '/master-data/province');

        return $response->json();
    }

    public function getDistricts(Request $request)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Token' => env('GHN_API_TOKEN'),
        ])->get($this->apiUrl . '/master-data/district', [
            'province_id' => $request->province_id
        ]);

        return $response->json();
    }

    public function getWards(Request $request)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Token' => env('GHN_API_TOKEN'),
        ])->get($this->apiUrl . '/master-data/ward', [
            'district_id' => $request->district_id
        ]);

        return $response->json();
    }

    public function calculateFee(Request $request)
    {
        $request->validate([
            'to_ward_code' => 'required',
            'to_district_id' => 'required',
            'weight' => 'required|integer',
        ]);

        $response = Http::withoutVerifying()->withHeaders([
            'Token' => env('GHN_API_TOKEN'),
            'ShopId' => env('GHN_SHOP_ID')
        ])->post($this->apiUrl . '/v2/shipping-order/fee', [
            'service_type_id' => 2, // Standard delivery
            'insurance_value' => $request->insurance_value ?? 0,
            'coupon' => null,
            'to_ward_code' => $request->to_ward_code,
            'to_district_id' => $request->to_district_id,
            'weight' => $request->weight,
            'length' => 20,
            'width' => 20,
            'height' => 10
        ]);

        return $response->json();
    }

    /**
     * Tự động tạo đơn hàng vận chuyển trên GHN Express cho đơn hàng (DonHang)
     */
    public static function createShippingOrderForDonHang(\App\Models\DonHang $order, $toWardCode = null, $toDistrictId = null)
    {
        if (!empty($order->maVanDonGhn)) {
            return [
                'success' => true,
                'order_code' => $order->maVanDonGhn,
                'message' => 'Đơn hàng đã có mã vận đơn GHN: ' . $order->maVanDonGhn
            ];
        }

        $token = env('GHN_API_TOKEN');
        $envShopId = env('GHN_SHOP_ID');
        $shopId = !empty($envShopId) ? (int)$envShopId : 0;

        if (empty($shopId) && $order->cuahang) {
            $stShopId = $order->cuahang->ghn_shop_id ?? $order->cuahang->GHN_SHOP_ID ?? $order->cuahang->ghnShopId;
            if (!empty($stShopId) && is_numeric($stShopId) && (int)$stShopId > 0) {
                $shopId = (int)$stShopId;
            }
        }
        if (empty($shopId)) {
            $shopId = 226234; // Default fallback shop ID
        }

        $customer = $order->nguoidung;
        $customerName = $customer ? ($customer->hoTen ?? 'Khách Hàng') : 'Khách Hàng';
        $rawPhone = $customer ? ($customer->soDienThoai ?? '0909123456') : '0909123456';
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (str_starts_with($cleanPhone, '84')) {
            $cleanPhone = '0' . substr($cleanPhone, 2);
        }
        if (empty($cleanPhone) || strlen($cleanPhone) < 9) {
            $cleanPhone = '0909123456';
        }
        $customerPhone = $cleanPhone;

        $isCOD = ($order->phuongThucThanhToan == 0 && $order->trangThaiThanhToan == 0);
        $paymentTypeId = $isCOD ? 2 : 1; // 2: Recipient pays/COD, 1: Sender pays/Online
        $codAmount = $isCOD ? (int) $order->tongThanhToan : 0;

        $address = $order->diaChiGiaoHang ?? 'Dola Bakery';
        // Ưu tiên tham số truyền vào, fallback về giá trị đã lưu trong order, cuối cùng mới dùng default
        $wardCode = $toWardCode ?? $order->toWardCode ?? '20314';
        $districtId = $toDistrictId ? (int)$toDistrictId : ($order->toDistrictId ? (int)$order->toDistrictId : 1444);

        $items = $order->chitiet->map(function ($it) {
            $sp = $it->sanpham;
            return [
                'name' => $sp ? $sp->tenSanPham : 'Bánh ngọt Dola Bakery',
                'quantity' => (int) $it->soLuong,
                'price' => (int) $it->donGia,
                'weight' => 500
            ];
        })->toArray();

        if (empty($items)) {
            $items = [
                [
                    'name' => 'Bánh ngọt Dola Bakery',
                    'quantity' => 1,
                    'price' => (int) $order->tongThanhToan,
                    'weight' => 500
                ]
            ];
        }

        $payload = [
            'payment_type_id' => $paymentTypeId,
            'note' => 'Đơn hàng Dola Bakery #' . $order->maDonHang,
            'required_note' => 'CHOXEMHANGKHONGTHU',
            'from_name' => $order->cuahang->tenCuaHang ?? 'DolaBakery Shop',
            'from_phone' => $order->cuahang->soDienThoai ?? '0353144481',
            'from_address' => $order->cuahang->diaChi ?? '140 Lê Trọng Tấn, Tây Thạnh, Tân Phú',
            'from_ward_code' => '21511',
            'from_district_id' => 1456,
            'return_phone' => '0353144481',
            'return_address' => 'Dola Bakery, TP.HCM',
            'to_name' => $customerName,
            'to_phone' => $customerPhone,
            'to_address' => $address,
            'to_ward_code' => (string) $wardCode,
            'to_district_id' => (int) $districtId,
            'cod_amount' => $codAmount,
            'weight' => 500,
            'length' => 20,
            'width' => 20,
            'height' => 10,
            'service_type_id' => 2,
            'items' => $items
        ];

        \Illuminate\Support\Facades\Log::info("GHN Create Order - ShopId: {$shopId}, Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        try {
            $ghnApiUrl = env('GHN_API_URL', 'https://online-gateway.ghn.vn/shiip/public-api');
            $response = Http::withoutVerifying()->withHeaders([
                'Token' => $token,
                'ShopId' => (int) $shopId
            ])->post($ghnApiUrl . '/v2/shipping-order/create', $payload);

            $data = $response->json();

            \Illuminate\Support\Facades\Log::info("GHN Response for HD #{$order->maDonHang}: HTTP {$response->status()} - " . json_encode($data, JSON_UNESCAPED_UNICODE));

            if ($response->successful() && isset($data['code']) && $data['code'] == 200 && isset($data['data']['order_code'])) {
                $ghnCode = $data['data']['order_code'];
                $order->maVanDonGhn = $ghnCode;
                $order->ghnStatusCode = 'ready_to_pick';
                $order->trangThaiDonHang = 7; // 7: CHO_LAY_HANG (Chờ lấy hàng)
                $order->save();

                \App\Models\LichSuTrangThaiDonHang::create([
                    'donHangId' => $order->id,
                    'trangThaiCu' => 2,
                    'trangThaiMoi' => 7,
                    'ghnStatusCode' => 'ready_to_pick',
                    'moTaChiTiet' => "Tự động tạo đơn GHN thành công. Mã vận đơn: {$ghnCode}",
                    'nguonCapNhat' => 1,
                ]);

                return [
                    'success' => true,
                    'order_code' => $ghnCode,
                    'message' => "Tự động tạo đơn vận chuyển GHN thành công! Mã vận đơn: {$ghnCode}"
                ];
            } else {
                $errMsg = $data['message'] ?? $data['code_message_value'] ?? 'Lỗi tạo đơn GHN';
                \Illuminate\Support\Facades\Log::warning("Tạo đơn GHN cho HD #{$order->maDonHang} thất bại: {$errMsg}");

                return [
                    'success' => false,
                    'message' => $errMsg
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Lỗi kết nối API GHN: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi kết nối hệ thống GHN: ' . $e->getMessage()
            ];
        }
    }

    public function webhook(Request $request)
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'success' => true,
                'message' => 'GHN Webhook Endpoint đang hoạt động sẵn sàng nhận dữ liệu POST từ GHN.'
            ]);
        }

        // Nhận dữ liệu từ GHN Webhook (GHN gọi POST tới endpoint này)
        $orderCode = $request->input('OrderCode') ?? $request->input('order_code');
        $status = $request->input('Status') ?? $request->input('status'); // 'ready_to_pick', 'picking', 'storing', 'delivering', 'delivered', 'cancel', 'return'...

        if (!$orderCode || !$status) {
            return response()->json(['message' => 'Thiếu dữ liệu OrderCode hoặc Status'], 400);
        }

        // Tìm đơn hàng tương ứng với maVanDonGhn hoặc maDonHang
        $order = \App\Models\DonHang::where('maVanDonGhn', $orderCode)
            ->orWhere('maDonHang', $orderCode)
            ->first();

        if ($order) {
            $oldStatus = $order->trangThaiDonHang;
            $newStatus = $oldStatus;
            $description = "Cập nhật từ GHN Webhook: Status [{$status}]";

            // Ánh xạ trạng thái GHN sang trạng thái hệ thống
            switch ($status) {
                case 'delivered':
                    $newStatus = 4; // HOAN_THANH
                    $order->trangThaiThanhToan = 1; // DA_THANH_TOAN
                    $description = "GHN: Giao hàng thành công";
                    break;

                case 'delivering':
                case 'storing':
                case 'transporting':
                case 'picking':
                case 'picked':
                    $newStatus = 3; // DANG_GIAO (Tự động cập nhật thành đang giao khi Shipper đã lấy hàng)
                    $description = "GHN: Shipper đã lấy hàng / Đang vận chuyển";
                    break;

                case 'ready_to_pick':
                    $newStatus = 7; // CHO_LAY_HANG
                    $description = "GHN: Đang chờ shipper qua lấy hàng";
                    break;

                case 'cancel':
                case 'cancelled':
                case 'canceled':
                    $newStatus = 0; // DA_HUY
                    $description = "GHN Webhook: Đơn hàng bị hủy trước khi giao ({$status})";

                    // Hủy trước khi giao -> chưa xuất bánh -> hoàn trả số lượng kho
                    if (!in_array($oldStatus, [0, 6])) {
                        foreach ($order->chitiet as $item) {
                            $tonKho = \App\Models\TonKhoChiNhanh::where('cuaHangId', $order->cuaHangId)
                                ->where('sanPhamId', $item->sanPhamId)
                                ->first();
                            if ($tonKho) {
                                $tonKho->increment('soLuongTon', $item->soLuong);
                            }
                        }
                    }
                    break;

                case 'return':
                case 'returned':
                case 'returning':
                case 'returned_to_sender':
                case 'damage':
                case 'lost':
                    $newStatus = 6; // HOAN_HANG (Chờ nhân viên kiểm định chất lượng bánh)
                    $description = "GHN Webhook: Đơn hàng bị hoàn/trả về shop ({$status}). Chờ nhân viên kiểm định chất lượng bánh.";
                    // KHÔNG tự động cộng lại kho ở đây. Cần nhân viên bấm xác nhận kiểm định ở trang Admin.
                    break;
            }

            $order->trangThaiDonHang = $newStatus;
            $order->ghnStatusCode = $status;
            $order->save();

            // Ghi vết vào lịch sử trạng thái
            \App\Models\LichSuTrangThaiDonHang::create([
                'donHangId' => $order->id,
                'trangThaiCu' => $oldStatus,
                'trangThaiMoi' => $newStatus,
                'ghnStatusCode' => $status,
                'moTaChiTiet' => $description,
                'nguonCapNhat' => 0, // GHN Webhook
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xử lý webhook GHN thành công. Đã tự động cập nhật trạng thái.',
                'order_id' => $order->id,
                'status' => $status,
                'trangThaiDonHang' => $newStatus
            ]);
        }

        return response()->json(['message' => 'Không tìm thấy đơn hàng với mã vận đơn GHN này'], 404);
    }

    /**
     * Tra cứu chi tiết hành trình đơn hàng từ GHN API
     */
    public function getTrackingDetail($orderCode)
    {
        $response = Http::withoutVerifying()->withHeaders([
            'Token' => env('GHN_API_TOKEN'),
        ])->post($this->apiUrl . '/v2/shipping-order/detail', [
            'order_code' => $orderCode
        ]);

        $ghnData = $response->json();

        // Tự động đồng bộ trạng thái đơn hàng trong DB local nếu GHN trả về kết quả thành công
        if (isset($ghnData['code']) && $ghnData['code'] == 200 && isset($ghnData['data'])) {
            $info = $ghnData['data'];
            $ghnStatus = $info['status'] ?? null;

            $order = \App\Models\DonHang::where('maVanDonGhn', $orderCode)
                ->orWhere('maDonHang', $orderCode)
                ->first();

            if ($order && $ghnStatus) {
                $order->ghnStatusCode = $ghnStatus;
                if ($ghnStatus === 'delivered' && $order->trangThaiDonHang != 4) {
                    $order->trangThaiDonHang = 4; // HOAN_THANH
                    $order->trangThaiThanhToan = 1; // DA_THANH_TOAN
                    $order->save();
                } elseif (in_array($ghnStatus, ['picking', 'picked', 'storing', 'transporting', 'delivering']) && $order->trangThaiDonHang != 3) {
                    $order->trangThaiDonHang = 3; // DANG_GIAO
                    $order->save();
                } elseif ($ghnStatus === 'ready_to_pick' && $order->trangThaiDonHang != 7) {
                    $order->trangThaiDonHang = 7; // CHO_LAY_HANG
                    $order->save();
                } elseif (in_array(strtolower($ghnStatus), ['cancel', 'cancelled', 'canceled', 'return', 'returned', 'returning', 'returned_to_sender', 'damage', 'lost'])) {
                    if (!in_array($order->trangThaiDonHang, [0, 6])) {
                        $oldStatus = $order->trangThaiDonHang;
                        $order->trangThaiDonHang = 0; // DA_HUY
                        $order->ghnStatusCode = $ghnStatus;
                        $order->save();

                        foreach ($order->chitiet as $item) {
                            $tonKho = \App\Models\TonKhoChiNhanh::where('cuaHangId', $order->cuaHangId)
                                ->where('sanPhamId', $item->sanPhamId)
                                ->first();
                            if ($tonKho) {
                                $tonKho->increment('soLuongTon', $item->soLuong);
                            }
                        }

                        \App\Models\LichSuTrangThaiDonHang::create([
                            'donHangId' => $order->id,
                            'trangThaiCu' => $oldStatus,
                            'trangThaiMoi' => 0,
                            'ghnStatusCode' => $ghnStatus,
                            'moTaChiTiet' => "Đồng bộ từ GHN API: Đơn hàng đã bị hủy trên GHN ({$ghnStatus})",
                            'nguonCapNhat' => 0,
                        ]);
                    }
                }
            }
        }

        return response()->json($ghnData);
    }
}
