<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\NhatKyHeThong;
use App\Models\NhanVien;

class LogActivityMiddleware
{
    /**
     * Bảng ánh xạ URL + Method → Tên hành động tiếng Việt
     */
    protected array $actionMap = [
        // Nhân viên
        'POST /api/admin/staff'             => 'Tạo nhân viên mới',
        'POST /api/admin/staff/import'      => 'Import nhân viên từ CSV',
        'PUT /api/admin/staff'              => 'Cập nhật thông tin nhân viên',
        'DELETE /api/admin/staff'           => 'Vô hiệu hóa nhân viên',
        'POST /api/admin/staff/scan-cccd'   => 'Quét CCCD nhân viên',

        // Sản phẩm
        'POST /api/admin/products'          => 'Thêm sản phẩm mới',
        'PUT /api/admin/products'           => 'Cập nhật sản phẩm',
        'DELETE /api/admin/products'        => 'Vô hiệu hóa sản phẩm',

        // Danh mục
        'POST /api/admin/categories'        => 'Thêm danh mục mới',
        'PUT /api/admin/categories'         => 'Cập nhật danh mục',
        'DELETE /api/admin/categories'      => 'Xóa danh mục',

        // Khách hàng
        'POST /api/customers'               => 'Thêm khách hàng mới',
        'PUT /api/customers'                => 'Cập nhật khách hàng',
        'DELETE /api/customers'             => 'Vô hiệu hóa khách hàng',

        // Hóa đơn
        'PUT /api/hoa-don'                  => 'Cập nhật trạng thái hóa đơn',
        'POST /api/admin/pos/checkout'      => 'Tạo hóa đơn POS',

        // Cửa hàng
        'POST /api/admin/stores'            => 'Tạo chi nhánh mới',
        'PUT /api/admin/stores'             => 'Cập nhật chi nhánh',

        // Điều chuyển kho
        'POST /api/admin/transfers/requests'     => 'Tạo yêu cầu điều chuyển',
        'POST /api/admin/transfers/requests'     => 'Xử lý yêu cầu điều chuyển',
        'PUT /api/admin/transfers'               => 'Cập nhật trạng thái điều chuyển',

        // Chấm công
        'POST /api/admin/cham-cong'         => 'Chấm công nhân viên',

        // CSKH
        'POST /api/cskh/place-order'        => 'CSKH đặt hàng cho khách',

        // Giỏ hàng & Đặt hàng
        'POST /api/cart/add'                => 'Thêm sản phẩm vào giỏ',
        'PUT /api/cart/update'              => 'Cập nhật số lượng giỏ hàng',
        'DELETE /api/cart/remove'           => 'Xóa sản phẩm khỏi giỏ',
        'POST /api/cart/checkout'           => 'Đặt hàng online',

        // Auth
        'POST /api/login'                   => 'Đăng nhập',
        'POST /api/logout'                  => 'Đăng xuất',
        'POST /api/register'               => 'Đăng ký tài khoản',
    ];

    /**
     * Các trường nhạy cảm cần ẩn khỏi chi tiết log
     */
    protected array $sensitiveFields = [
        'PASSWORD', 'password', 'NEW_PASSWORD', 'OTP_CODE',
        'password_confirmation', 'token', 'access_token',
    ];

    /**
     * Handle an incoming request — ghi log SAU KHI request được xử lý thành công
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Lấy user trước khi controller xử lý (quan trọng cho chức năng Đăng xuất vì token sẽ bị hủy)
        $userBefore = null;
        try {
            $userBefore = Auth::guard('api')->user();
        } catch (\Exception $e) {
        }

        $response = $next($request);

        // Chỉ ghi log cho các thao tác thay đổi dữ liệu (POST, PUT, DELETE)
        if (!in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            return $response;
        }

        // Chỉ ghi log khi response thành công (2xx)
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        try {
            $user = $userBefore ?? Auth::guard('api')->user();
            $username = null;
            $maRole = null;
            $hoTen = null;
            $maCuaHang = null;

            if ($user) {
                $username = $user->USERNAME;
                $maRole = $user->MAROLE;
            } elseif ($request->has('USERNAME')) {
                $username = $request->input('USERNAME');
                $userModel = \App\Models\TaiKhoan::where('USERNAME', $username)->first();
                if ($userModel) {
                    $maRole = $userModel->MAROLE;
                }
            }

            if ($username) {
                $nhanVien = NhanVien::where('USERNAME', $username)->first();
                if ($nhanVien) {
                    $hoTen = $nhanVien->HOTEN;
                    $maCuaHang = $nhanVien->MACUAHANG;
                } else {
                    // Nếu là khách hàng
                    $khachHang = \App\Models\KhachHang::where('USERNAME', $username)->first();
                    if ($khachHang) {
                        $hoTen = $khachHang->HOTEN;
                    }
                }
            }

            // Xác định tên hành động
            $hanhDong = $this->resolveActionName($request);

            // Gắn thêm ID đối tượng nếu có (Trừ Đăng nhập/Đăng xuất/Đăng ký)
            if (!in_array($hanhDong, ['Đăng nhập', 'Đăng xuất', 'Đăng ký tài khoản'])) {
                $objectId = $this->extractObjectId($request, $response);
                if ($objectId) {
                    $hanhDong .= ' - ' . $objectId;
                }
            }

            // Không lưu trữ chi tiết request, IP, User Agent để bảo mật thông tin
            $chiTiet = null;

            NhatKyHeThong::create([
                'USERNAME'   => $username,
                'HOTEN'      => $hoTen,
                'MAROLE'     => $maRole,
                'MACUAHANG'  => $maCuaHang,
                'HANH_DONG'  => $hanhDong,
                'CHITIET'    => null,
                'IP_ADDRESS' => $request->ip(),
            ]);
        } catch (\Exception $e) {
            // Ghi log thất bại không được ảnh hưởng đến response chính
            \Illuminate\Support\Facades\Log::error('LogActivity failed: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Map URL + Method → Tên hành động tiếng Việt
     */
    protected function resolveActionName(Request $request): string
    {
        $method = $request->method();
        $path = '/' . ltrim($request->path(), '/');

        // Handle specific actions that don't fit the base regex well
        if (str_ends_with($path, '/restore')) {
            if (str_contains($path, '/admin/products/')) {
                return 'Kích hoạt lại sản phẩm';
            }
            if (str_contains($path, '/customers/')) {
                return 'Kích hoạt lại khách hàng';
            }
        }

        // Thử khớp chính xác trước
        $key = $method . ' ' . $path;
        if (isset($this->actionMap[$key])) {
            return $this->actionMap[$key];
        }

        // Thử khớp với URL bỏ phần ID cuối (VD: /api/admin/staff/abc → /api/admin/staff)
        $basePath = preg_replace('#/[^/]+$#', '', $path);
        $baseKey = $method . ' ' . $basePath;
        if (isset($this->actionMap[$baseKey])) {
            return $this->actionMap[$baseKey];
        }

        // Fallback: trả về method + path
        return $method . ' ' . $path;
    }

    /**
     * Tạo chuỗi chi tiết log, ẩn các trường nhạy cảm
     */
    protected function buildDetails(Request $request): string
    {
        $data = $request->except($this->sensitiveFields);

        // Loại bỏ dữ liệu file upload (quá lớn) khỏi log
        foreach ($data as $key => $value) {
            if ($request->hasFile($key)) {
                $data[$key] = '[File Upload]';
            }
        }

        return json_encode([
            'method' => $request->method(),
            'url'    => $request->fullUrl(),
            'data'   => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Trích xuất ID đối tượng (Mã SP, Mã HĐ, Mã NV,...) từ Request hoặc Response
     */
    protected function extractObjectId(Request $request, Response $response): ?string
    {
        $method = $request->method();

        // 1. Phân tích Response JSON (cho POST/PUT thường trả về object vừa tạo/sửa)
        if (in_array($method, ['POST', 'PUT'])) {
            $content = $response->getContent();
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    if (isset($data['mavandon']) && is_scalar($data['mavandon'])) {
                        return 'Mã: ' . $data['mavandon'];
                    }
                    if (isset($data['MAPHIEU']) && is_scalar($data['MAPHIEU'])) {
                        return 'Mã: ' . $data['MAPHIEU'];
                    }

                    $possibleWrappers = ['', 'data', 'store', 'product', 'invoice', 'order', 'user'];
                    $possibleKeys = ['MAHD', 'MACUAHANG', 'MASP', 'USERNAME', 'MAPHIEU', 'MAKH', 'id'];

                    foreach ($possibleWrappers as $wrapper) {
                        $target = $wrapper === '' ? $data : ($data[$wrapper] ?? null);
                        if (is_array($target)) {
                            foreach ($possibleKeys as $key) {
                                if (isset($target[$key]) && is_scalar($target[$key])) {
                                    return 'Mã: ' . $target[$key];
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. Phân tích URL Parameters (Cho PUT, DELETE hoặc POST vào specific id)
        $segments = $request->segments();
        $ignoreSegments = ['api', 'admin', 'status', 'accept', 'coordinate', 'import', 'checkout', 'scan-cccd', 'remove', 'add', 'update'];
        $commonResources = ['staff', 'products', 'categories', 'customers', 'hoa-don', 'stores', 'transfers', 'requests', 'employees', 'cham-cong', 'reports', 'users', 'cart', 'conversations', 'messages', 'internal', 'cskh', 'place-order'];

        foreach (array_reverse($segments) as $segment) {
            if (!in_array($segment, $ignoreSegments) && !in_array($segment, $commonResources)) {
                return 'Mã: ' . $segment;
            }
        }

        // 3. Phân tích Request Body (Fallback)
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $possibleReqKeys = ['MASP', 'MACUAHANG', 'USERNAME', 'MAHD'];
            foreach ($possibleReqKeys as $key) {
                if ($request->has($key) && is_scalar($request->input($key))) {
                    return 'Mã: ' . $request->input($key);
                }
            }
        }

        return null;
    }
}
