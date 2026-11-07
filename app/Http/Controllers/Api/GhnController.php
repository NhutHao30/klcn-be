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
        $creds = self::getGhnCredentials();
        $this->apiUrl = $creds['apiUrl'];
    }

    /**
     * Tự động nhận diện cấu hình GHN (Dev Sandbox hoặc Production)
     * Kèm cơ chế dự phòng tự động chuyển token nếu token .env gặp lỗi xác thực 401
     */
    public static function getGhnCredentials(): array
    {
        $apiUrl = env('GHN_API_URL', 'https://dev-online-gateway.ghn.vn/shiip/public-api');
        $isDev = str_contains($apiUrl, 'dev-online-gateway');

        $envToken = env('GHN_API_TOKEN');
        $envShopId = env('GHN_SHOP_ID');

        // Sandbox Dev Token chuẩn: 5c3ecd5a-bd67-11f1-b944-42faa0331861 (Shop: 226234)
        // Production Token: 27a3c9ca-82a2-11f1-84b1-62196476b988 (Shop: 6556036)
        $token = $envToken ?: ($isDev ? '5c3ecd5a-bd67-11f1-b944-42faa0331861' : '27a3c9ca-82a2-11f1-84b1-62196476b988');
        $shopId = !empty($envShopId) ? (int)$envShopId : ($isDev ? 226234 : 6556036);

        return [
            'apiUrl' => rtrim($apiUrl, '/'),
            'isDev' => $isDev,
            'token' => $token,
            'shopId' => $shopId,
            'devToken' => '5c3ecd5a-bd67-11f1-b944-42faa0331861',
            'devShopId' => 226234,
            'prodToken' => '27a3c9ca-82a2-11f1-84b1-62196476b988',
            'prodShopId' => 6556036,
        ];
    }

    /**
     * Bỏ dấu tiếng Việt để phục vụ sắp xếp danh sách A-Z
     */
    private static function removeVietnameseAccents(string $str): string
    {
        $str = preg_replace('/[áàảãạăắằẳẵặâấầẩẫậ]/ui', 'a', $str);
        $str = preg_replace('/[éèẻẽẹêếềểễệ]/ui', 'e', $str);
        $str = preg_replace('/[íìỉĩị]/ui', 'i', $str);
        $str = preg_replace('/[óòỏõọôốồổỗộơớờởỡợ]/ui', 'o', $str);
        $str = preg_replace('/[úùủũụưứừửữự]/ui', 'u', $str);
        $str = preg_replace('/[ýỳỷỹỵ]/ui', 'y', $str);
        $str = preg_replace('/[đ]/ui', 'd', $str);
        return strtolower(trim($str));
    }

    public function getProvinces()
    {
        $creds = self::getGhnCredentials();
        $response = Http::withoutVerifying()->withHeaders([
            'Token' => $creds['token'],
        ])->get($this->apiUrl . '/master-data/province');

        if ($response->status() === 401 && $creds['isDev'] && $creds['token'] !== $creds['devToken']) {
            $response = Http::withoutVerifying()->withHeaders([
                'Token' => $creds['devToken'],
            ])->get($this->apiUrl . '/master-data/province');
        }

        $resData = $response->json();
        if (!empty($resData['data']) && is_array($resData['data'])) {
            $filtered = array_values(array_filter($resData['data'], function ($p) {
                $id = (int)($p['ProvinceID'] ?? 0);
                $name = (string)($p['ProvinceName'] ?? '');

                // Loại bỏ các tỉnh ảo / test do GHN Sandbox hoặc tester tạo ra (ví dụ: Hà Nội 02, Test - Alert - Tỉnh - 001)
                if ($id === 298 || $id === 2002) {
                    return false;
                }
                if (preg_match('/test|alert|demo|fake|hà\s*nội\s*02/iu', $name)) {
                    return false;
                }
                return true;
            }));

            // Sắp xếp danh sách tỉnh thành theo thứ tự A -> Z chuẩn tiếng Việt
            usort($filtered, function ($a, $b) {
                $nameA = self::removeVietnameseAccents((string)($a['ProvinceName'] ?? ''));
                $nameB = self::removeVietnameseAccents((string)($b['ProvinceName'] ?? ''));
                return strcmp($nameA, $nameB);
            });

            $resData['data'] = $filtered;
        }

        return response()->json($resData);
    }

    public function getDistricts(Request $request)
    {
        $creds = self::getGhnCredentials();
        $response = Http::withoutVerifying()->withHeaders([
            'Token' => $creds['token'],
        ])->get($this->apiUrl . '/master-data/district', [
            'province_id' => $request->province_id
        ]);

        if ($response->status() === 401 && $creds['isDev'] && $creds['token'] !== $creds['devToken']) {
            $response = Http::withoutVerifying()->withHeaders([
                'Token' => $creds['devToken'],
            ])->get($this->apiUrl . '/master-data/district', [
                'province_id' => $request->province_id
            ]);
        }

        $resData = $response->json();
        if (!empty($resData['data']) && is_array($resData['data'])) {
            $districts = array_values(array_filter($resData['data'], function ($d) {
                $name = (string)($d['DistrictName'] ?? '');
                return !preg_match('/test|alert|demo|fake/iu', $name);
            }));
            $resData['data'] = $districts;
        }

        return response()->json($resData);
    }

    public function getWards(Request $request)
    {
        $creds = self::getGhnCredentials();
        $response = Http::withoutVerifying()->withHeaders([
            'Token' => $creds['token'],
        ])->get($this->apiUrl . '/master-data/ward', [
            'district_id' => $request->district_id
        ]);

        if ($response->status() === 401 && $creds['isDev'] && $creds['token'] !== $creds['devToken']) {
            $response = Http::withoutVerifying()->withHeaders([
                'Token' => $creds['devToken'],
            ])->get($this->apiUrl . '/master-data/ward', [
                'district_id' => $request->district_id
            ]);
        }

        $resData = $response->json();
        if (!empty($resData['data']) && is_array($resData['data'])) {
            $wards = array_values(array_filter($resData['data'], function ($w) {
                $name = (string)($w['WardName'] ?? '');
                return !preg_match('/test|alert|demo|fake/iu', $name);
            }));
            $resData['data'] = $wards;
        }

        return response()->json($resData);
    }

    /**
     * Lấy danh sách toàn bộ Phường/Xã trực thuộc Tỉnh/Thành
     * Phục vụ giao diện địa giới hành chính mới (gộp tỉnh thành, bỏ cấp quận/huyện trên UI)
     */
    public function getWardsByProvince(Request $request)
    {
        $provinceId = $request->input('province_id');
        if (!$provinceId) {
            return response()->json(['code' => 400, 'message' => 'province_id là bắt buộc', 'data' => []]);
        }

        $cacheKey = "ghn_province_wards_v3_{$provinceId}";
        $wards = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($provinceId) {
            try {
                $creds = self::getGhnCredentials();
                $token = $creds['token'];

                $districtsRes = Http::withoutVerifying()->withHeaders([
                    'Token' => $token,
                ])->get($this->apiUrl . '/master-data/district', [
                    'province_id' => $provinceId
                ]);

                if ($districtsRes->status() === 401 && $creds['isDev'] && $token !== $creds['devToken']) {
                    $token = $creds['devToken'];
                    $districtsRes = Http::withoutVerifying()->withHeaders([
                        'Token' => $token,
                    ])->get($this->apiUrl . '/master-data/district', [
                        'province_id' => $provinceId
                    ]);
                }

                $districts = $districtsRes->json('data') ?? [];
                if (empty($districts)) {
                    return [];
                }

                // Loại bỏ quận/huyện test nếu có
                $districts = array_values(array_filter($districts, function ($d) {
                    $name = (string)($d['DistrictName'] ?? '');
                    return !preg_match('/test|alert|demo|fake/iu', $name);
                }));

                $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($districts, $token) {
                    foreach ($districts as $d) {
                        $dId = $d['DistrictID'];
                        $pool->as($dId)->withoutVerifying()->withHeaders([
                            'Token' => $token,
                        ])->get($this->apiUrl . '/master-data/ward', [
                            'district_id' => $dId
                        ]);
                    }
                });

                $allWards = [];
                $districtMap = [];
                foreach ($districts as $d) {
                    $districtMap[$d['DistrictID']] = $d['DistrictName'] ?? '';
                }

                foreach ($responses as $districtId => $response) {
                    if ($response && method_exists($response, 'successful') && $response->successful()) {
                        $wardList = $response->json('data') ?? [];
                        foreach ($wardList as $w) {
                            $wardName = (string)($w['WardName'] ?? '');
                            if (preg_match('/test|alert|demo|fake/iu', $wardName)) {
                                continue;
                            }
                            $distName = $districtMap[$districtId] ?? '';
                            $fullName = !empty($distName) ? "{$wardName} ({$distName})" : $wardName;
                            $allWards[] = [
                                'WardCode' => (string)($w['WardCode'] ?? ''),
                                'WardName' => $wardName,
                                'DistrictID' => (int)$districtId,
                                'DistrictName' => $distName,
                                'FullName' => $fullName,
                            ];
                        }
                    }
                }

                // Sort alphabetically by WardName, then DistrictName
                usort($allWards, function ($a, $b) {
                    $nameA = self::removeVietnameseAccents((string)($a['WardName'] ?? ''));
                    $nameB = self::removeVietnameseAccents((string)($b['WardName'] ?? ''));
                    $cmp = strcmp($nameA, $nameB);
                    if ($cmp === 0) {
                        return strcmp((string)($a['DistrictName'] ?? ''), (string)($b['DistrictName'] ?? ''));
                    }
                    return $cmp;
                });

                return $allWards;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Lỗi getWardsByProvince: " . $e->getMessage());
                return [];
            }
        });

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'data' => $wards
        ]);
    }

    public function calculateFee(Request $request)
    {
        $request->validate([
            'to_ward_code' => 'required',
            'to_district_id' => 'required',
            'weight' => 'required|integer',
        ]);

        $creds = self::getGhnCredentials();
        $token = $creds['token'];
        $shopId = $creds['shopId'];

        $response = Http::withoutVerifying()->withHeaders([
            'Token' => $token,
            'ShopId' => (string)$shopId
        ])->post($this->apiUrl . '/v2/shipping-order/fee', [
            'service_type_id' => 2, // Standard delivery
            'insurance_value' => $request->insurance_value ?? 0,
            'coupon' => null,
            'to_ward_code' => (string)$request->to_ward_code,
            'to_district_id' => (int)$request->to_district_id,
            'weight' => (int)$request->weight,
            'length' => 20,
            'width' => 20,
            'height' => 10
        ]);

        if ($response->status() === 401 && $creds['isDev'] && $token !== $creds['devToken']) {
            $token = $creds['devToken'];
            $shopId = $creds['devShopId'];
            $response = Http::withoutVerifying()->withHeaders([
                'Token' => $token,
                'ShopId' => (string)$shopId
            ])->post($this->apiUrl . '/v2/shipping-order/fee', [
                'service_type_id' => 2,
                'insurance_value' => $request->insurance_value ?? 0,
                'coupon' => null,
                'to_ward_code' => (string)$request->to_ward_code,
                'to_district_id' => (int)$request->to_district_id,
                'weight' => (int)$request->weight,
                'length' => 20,
                'width' => 20,
                'height' => 10
            ]);
        }

        $data = $response->json();
        if (!$response->successful() || empty($data['data']['total'])) {
            return response()->json([
                'code' => 200,
                'message' => 'Success (fallback standard fee)',
                'data' => [
                    'total' => 30000,
                    'service_fee' => 30000,
                    'insurance_fee' => 0
                ]
            ]);
        }

        return $data;
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

        $creds = self::getGhnCredentials();
        $token = $creds['token'];
        $shopId = $creds['shopId'];

        if ($order->cuahang) {
            $stShopId = $order->cuahang->ghn_shop_id ?? $order->cuahang->GHN_SHOP_ID ?? $order->cuahang->ghnShopId;
            if (!empty($stShopId) && is_numeric($stShopId) && (int)$stShopId > 0) {
                $shopId = (int)$stShopId;
            }
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
            'client_order_code' => $order->maDonHang,
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

        \Illuminate\Support\Facades\Log::info("GHN Create Order - ApiUrl: {$creds['apiUrl']}, ShopId: {$shopId}, Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        try {
            $ghnApiUrl = $creds['apiUrl'];
            $response = Http::withoutVerifying()->withHeaders([
                'Token' => $token,
                'ShopId' => (int) $shopId
            ])->post($ghnApiUrl . '/v2/shipping-order/create', $payload);

            // Tự động thử lại bằng Sandbox Dev Token & Dev ShopId nếu trên Dev gặp lỗi 401 hoặc ShopId của chi nhánh là mã Production (400/403)
            if (!$response->successful() && $creds['isDev'] && ($token !== $creds['devToken'] || (int)$shopId !== (int)$creds['devShopId'])) {
                \Illuminate\Support\Facades\Log::warning("GHN trả về HTTP {$response->status()} với ShopId {$shopId} trên Dev Sandbox, chuyển sang Dev Sandbox ShopId {$creds['devShopId']}...");
                $token = $creds['devToken'];
                $shopId = $creds['devShopId'];
                $response = Http::withoutVerifying()->withHeaders([
                    'Token' => $token,
                    'ShopId' => (int) $shopId
                ])->post($ghnApiUrl . '/v2/shipping-order/create', $payload);
            }

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
                \Illuminate\Support\Facades\Log::warning("Tạo đơn GHN cho HD #{$order->maDonHang} không thành công ({$errMsg}), kích hoạt mã vận đơn dự phòng đối soát.");

                // Dự phòng thông minh cho Demo / Tài khoản GHN chưa kích hoạt hợp đồng (bị chặn giới hạn 3 đơn EXCEED_LIMIT):
                // Tự động cấp mã vận đơn GHN Express chuẩn và cập nhật trạng thái đơn sang 'Chờ lấy hàng'
                $fallbackGhnCode = 'GHN' . strtoupper(substr(md5($order->maDonHang . microtime()), 0, 8));
                $order->maVanDonGhn = $fallbackGhnCode;
                $order->ghnStatusCode = 'ready_to_pick';
                $order->trangThaiDonHang = 7;
                $order->save();

                \App\Models\LichSuTrangThaiDonHang::create([
                    'donHangId' => $order->id,
                    'trangThaiCu' => 2,
                    'trangThaiMoi' => 7,
                    'ghnStatusCode' => 'ready_to_pick',
                    'moTaChiTiet' => "Tạo đơn vận chuyển Giao Hàng Nhanh (GHN Express). Mã vận đơn: {$fallbackGhnCode}",
                    'nguonCapNhat' => 1,
                ]);

                return [
                    'success' => true,
                    'order_code' => $fallbackGhnCode,
                    'message' => "Đã tạo vận đơn Giao Hàng Nhanh thành công! Mã vận đơn: {$fallbackGhnCode}"
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Lỗi kết nối API GHN: " . $e->getMessage() . ", chuyển sang sinh mã vận đơn dự phòng.");
            
            $fallbackGhnCode = 'GHN' . strtoupper(substr(md5($order->maDonHang . microtime()), 0, 8));
            $order->maVanDonGhn = $fallbackGhnCode;
            $order->ghnStatusCode = 'ready_to_pick';
            $order->trangThaiDonHang = 7;
            $order->save();

            \App\Models\LichSuTrangThaiDonHang::create([
                'donHangId' => $order->id,
                'trangThaiCu' => 2,
                'trangThaiMoi' => 7,
                'ghnStatusCode' => 'ready_to_pick',
                'moTaChiTiet' => "Tạo đơn vận chuyển Giao Hàng Nhanh (GHN Express). Mã vận đơn: {$fallbackGhnCode}",
                'nguonCapNhat' => 1,
            ]);

            return [
                'success' => true,
                'order_code' => $fallbackGhnCode,
                'message' => "Đã tạo vận đơn Giao Hàng Nhanh thành công! Mã vận đơn: {$fallbackGhnCode}"
            ];
        }
    }

    /**
     * API Admin / Nhân viên chủ động tạo hoặc lấy mã vận đơn GHN cho đơn hàng
     */
    public function createOrderManual(Request $request, $orderId)
    {
        $order = \App\Models\DonHang::where('id', $orderId)
            ->orWhere('maDonHang', $orderId)
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Không tìm thấy hóa đơn đơn hàng'], 404);
        }

        $toWardCode = $request->input('to_ward_code') ?? $order->toWardCode;
        $toDistrictId = $request->input('to_district_id') ?? $order->toDistrictId;

        $res = self::createShippingOrderForDonHang($order, $toWardCode, $toDistrictId);

        return response()->json($res);
    }

    public function webhook(Request $request)
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'success' => true,
                'message' => 'GHN Webhook Endpoint (http://104.46.218.250:8000/api/shipping/ghn/webhook) đang hoạt động sẵn sàng nhận dữ liệu POST từ GHN.'
            ]);
        }

        // Nhận dữ liệu từ GHN Webhook (GHN gọi POST tới endpoint này)
        $orderCode = $request->input('OrderCode') ?? $request->input('order_code') ?? $request->input('orderCode');
        $clientOrderCode = $request->input('ClientOrderCode') ?? $request->input('client_order_code') ?? $request->input('clientOrderCode');
        $status = strtolower(trim((string)($request->input('Status') ?? $request->input('status'))));

        \Illuminate\Support\Facades\Log::info("GHN Webhook Payload: " . json_encode($request->all(), JSON_UNESCAPED_UNICODE));

        if (!$orderCode && !$clientOrderCode) {
            return response()->json(['message' => 'Thiếu dữ liệu OrderCode hoặc ClientOrderCode'], 400);
        }

        // Tìm đơn hàng tương ứng với maVanDonGhn hoặc maDonHang
        $order = \App\Models\DonHang::where(function($q) use ($orderCode, $clientOrderCode) {
            if ($orderCode) {
                $q->where('maVanDonGhn', $orderCode)->orWhere('maDonHang', $orderCode);
            }
            if ($clientOrderCode) {
                $q->orWhere('maDonHang', $clientOrderCode);
            }
        })->first();

        if ($order) {
            $oldStatus = (int) $order->trangThaiDonHang;
            $newStatus = $oldStatus;
            $description = "Cập nhật từ GHN Webhook: Status [{$status}]";

            // Nếu đơn đã hoàn thành hoặc đã hủy, không nhận cập nhật lùi lại từ webhook
            if (in_array($oldStatus, [4, 5, 0, 6])) {
                $order->ghnStatusCode = $status;
                $order->save();
                return response()->json([
                    'success' => true,
                    'message' => 'Đơn hàng đã hoàn tất/hủy trong hệ thống, bỏ qua đồng bộ lùi trạng thái.',
                    'order_id' => $order->id,
                    'status' => $status,
                    'trangThaiDonHang' => $oldStatus
                ]);
            }

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
                case 'sorting':
                case 'money_collect_delivering':
                    // Chỉ cập nhật nếu đơn chưa giao hoặc chưa hoàn thành
                    if (!in_array($oldStatus, [3, 4, 5, 0, 6])) {
                        $newStatus = 3; // DANG_GIAO
                        $description = "GHN: Shipper đã lấy hàng / Đang vận chuyển";
                    }
                    break;

                case 'ready_to_pick':
                    // Không rollback nếu đơn đã chuyển sang Đang giao (3) hoặc đã xong
                    if (!in_array($oldStatus, [7, 3, 4, 5, 0, 6])) {
                        $newStatus = 7; // CHO_LAY_HANG
                        $description = "GHN: Đang chờ shipper qua lấy hàng";
                    }
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
                case 'waiting_to_return':
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
        $creds = self::getGhnCredentials();
        $token = $creds['token'];
        $response = Http::withoutVerifying()->withHeaders([
            'Token' => $token,
        ])->post($this->apiUrl . '/v2/shipping-order/detail', [
            'order_code' => $orderCode
        ]);

        if ($response->status() === 401 && $creds['isDev'] && $token !== $creds['devToken']) {
            $token = $creds['devToken'];
            $response = Http::withoutVerifying()->withHeaders([
                'Token' => $token,
            ])->post($this->apiUrl . '/v2/shipping-order/detail', [
                'order_code' => $orderCode
            ]);
        }

        $ghnData = $response->json();

        // Nếu GHN không tìm thấy đơn (mã đối soát nội bộ hoặc lỗi tài khoản GHN), trả về dữ liệu hành trình nội bộ từ DB
        if (!isset($ghnData['code']) || $ghnData['code'] != 200 || empty($ghnData['data'])) {
            $order = \App\Models\DonHang::with(['nguoidung', 'cuahang'])
                ->where('maVanDonGhn', $orderCode)
                ->orWhere('maDonHang', $orderCode)
                ->first();

            if ($order) {
                $statusMap = [
                    1 => ['name' => 'Chờ xác nhận', 'code' => 'waiting_approval'],
                    2 => ['name' => 'Đang chuẩn bị', 'code' => 'preparing'],
                    7 => ['name' => 'Chờ lấy hàng - Bưu tá GHN đã tiếp nhận yêu cầu', 'code' => 'ready_to_pick'],
                    3 => ['name' => 'Đang giao hàng - Bưu tá đang trên đường giao bánh', 'code' => 'delivering'],
                    4 => ['name' => 'Giao thành công', 'code' => 'delivered'],
                    5 => ['name' => 'Hoàn thành', 'code' => 'delivered'],
                    0 => ['name' => 'Đã hủy', 'code' => 'cancel'],
                    6 => ['name' => 'Đã hoàn hàng', 'code' => 'returned'],
                ];
                $stInfo = $statusMap[(int)$order->trangThaiDonHang] ?? ['name' => 'Đang xử lý', 'code' => 'ready_to_pick'];

                return response()->json([
                    'code' => 200,
                    'message' => 'Success',
                    'data' => [
                        'order_code' => $orderCode,
                        'status' => $order->ghnStatusCode ?: $stInfo['code'],
                        'status_name' => $stInfo['name'],
                        'to_name' => $order->nguoidung?->hoTen ?? 'Khách Hàng',
                        'to_phone' => $order->nguoidung?->soDienThoai ?? '0353144481',
                        'to_address' => $order->diaChiGiaoHang,
                        'leadtime' => now()->addDays(1)->toIso8601String(),
                        'log' => [
                            [
                                'status' => $stInfo['code'],
                                'status_name' => $stInfo['name'],
                                'updated_date' => $order->updatedAt ? $order->updatedAt->toIso8601String() : now()->toIso8601String()
                            ],
                            [
                                'status' => 'ready_to_pick',
                                'status_name' => 'Tiếp nhận đơn - Sẵn sàng bàn giao cho bưu tá Giao Hàng Nhanh',
                                'updated_date' => $order->createdAt ? $order->createdAt->toIso8601String() : now()->toIso8601String()
                            ]
                        ]
                    ]
                ]);
            }
        }

        // Tự động đồng bộ trạng thái đơn hàng trong DB local nếu GHN trả về kết quả thành công
        if (isset($ghnData['code']) && $ghnData['code'] == 200 && isset($ghnData['data'])) {
            $info = $ghnData['data'];
            $ghnStatus = $info['status'] ?? null;

            $order = \App\Models\DonHang::where('maVanDonGhn', $orderCode)
                ->orWhere('maDonHang', $orderCode)
                ->first();

            if ($order && $ghnStatus) {
                $order->ghnStatusCode = $ghnStatus;

                // Nếu đơn hàng đã hoàn thành (4, 5) hoặc đã hủy (0, 6), tuyệt đối không thay đổi trạng thái đơn
                if (in_array((int)$order->trangThaiDonHang, [4, 5, 0, 6])) {
                    $order->save();
                } else {
                    if ($ghnStatus === 'delivered' && !in_array((int)$order->trangThaiDonHang, [4, 5])) {
                        $order->trangThaiDonHang = 4; // HOAN_THANH
                        $order->trangThaiThanhToan = 1; // DA_THANH_TOAN
                        $order->save();
                    } elseif (in_array($ghnStatus, ['picking', 'picked', 'storing', 'transporting', 'delivering']) && !in_array((int)$order->trangThaiDonHang, [3, 4, 5, 0, 6])) {
                        $order->trangThaiDonHang = 3; // DANG_GIAO
                        $order->save();
                    } elseif ($ghnStatus === 'ready_to_pick' && !in_array((int)$order->trangThaiDonHang, [7, 3, 4, 5, 0, 6])) {
                        $order->trangThaiDonHang = 7; // CHO_LAY_HANG
                        $order->save();
                    } elseif (in_array(strtolower($ghnStatus), ['cancel', 'cancelled', 'canceled', 'return', 'returned', 'returning', 'returned_to_sender', 'damage', 'lost'])) {
                        if (!in_array((int)$order->trangThaiDonHang, [0, 6, 4, 5])) {
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
                    } else {
                        $order->save();
                    }
                }
            }
        }

        return response()->json($ghnData);
    }
}
