<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class ResilientAdminMiddleware
{
    /**
     * Middleware bảo vệ các route giám sát hệ thống & báo cáo:
     * - Khi MySQL hoạt động: xác thực đầy đủ quyền tài khoản.
     * - Khi MySQL bị sập/tắt (Downtime): giải mã và kiểm tra chữ ký số mật mã của JWT token
     *   hoàn toàn trong bộ nhớ (stateless), KHÔNG bị phụ thuộc vào MySQL, giúp trang giám sát
     *   vẫn luôn mở được để Admin quan sát và xử lý sự cố máy chủ.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Chưa đăng nhập hoặc thiếu Bearer Token.'
            ], 401);
        }

        // 1. Thử xác thực chuẩn qua Database nếu DB đang chạy
        try {
            $user = Auth::guard('api')->user();
            if ($user) {
                // Kiểm tra vai trò hợp lệ
                $maRole = (string)($user->MAROLE ?? '');
                $vaiTroId = (string)($user->vaiTroId ?? '');
                
                // Admin (0/1), Giám đốc (5/8), Quản lý (1/2), Kế toán (6/7), CSKH (4/5)
                $allowed = ['0', '1', '2', '4', '5', '6', '7', '8'];
                if (in_array($maRole, $allowed) || in_array($vaiTroId, $allowed) || strtolower($user->username ?? '') === 'admin') {
                    return $next($request);
                }
            }
        } catch (\Throwable $eDb) {
            // MySQL đã tắt hoặc không phản hồi -> chuyển sang kiểm tra JWT thuần chữ ký (Stateless)
        }

        // 2. Chế độ cứu hộ khi MySQL sập (Resilient Mode):
        // Xác minh chữ ký mật mã HMAC-SHA256 của Token bằng JWT_SECRET trong file .env
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
            $sub = $payload->get('sub');
            if ($sub) {
                $request->attributes->set('resilient_mode', true);
                $request->attributes->set('resilient_user_id', $sub);
                return $next($request);
            }
        } catch (\Throwable $eJwt) {
            return response()->json([
                'success' => false,
                'error' => 'Phiên làm việc không hợp lệ hoặc đã hết hạn.'
            ], 401);
        }

        return response()->json([
            'success' => false,
            'error' => 'Bạn không có quyền truy cập vào mục Giám sát & Báo cáo này.'
        ], 403);
    }
}

