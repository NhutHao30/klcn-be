<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Services\UserPresenceService;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserPresenceMiddleware
{
    /**
     * Tự động ghi nhận sự hiện diện (Online Presence) của người dùng trên Web và Mobile
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = null;

            // 1. Kiểm tra JWT Token trong Header nếu có
            $authHeader = $request->header('Authorization', '');
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);

                // Ưu tiên lấy user từ guard nếu DB hoạt động
                try {
                    $user = Auth::guard('api')->user();
                } catch (\Throwable $eDb) {
                    // Nếu MySQL tắt, giải mã token tĩnh không cần DB
                    try {
                        $payload = JWTAuth::setToken($token)->getPayload();
                        $sub = $payload->get('sub');
                        if ($sub) {
                            $user = (object)[
                                'id' => (int)$sub,
                                'username' => $payload->get('username', 'Tài khoản #' . $sub),
                                'vaiTroId' => (int)$payload->get('vaiTroId', 6),
                            ];
                        }
                    } catch (\Throwable $eJwt) {}
                }
            }

            if ($user) {
                UserPresenceService::record($user, $request);
            }
        } catch (\Throwable $e) {
            // Không bao giờ để lỗi presence làm gián đoạn luồng chính của website/app
        }

        return $next($request);
    }
}

