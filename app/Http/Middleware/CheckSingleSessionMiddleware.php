<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckSingleSessionMiddleware
{
    /**
     * Handle an incoming request.
     * Kiểm tra phiên đăng nhập duy nhất (Single Session):
     * Nếu tài khoản đăng nhập ở nơi mới, các phiên đăng nhập cũ sẽ bị vô hiệu hóa ngay lập tức.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();
            if ($token) {
                $user = Auth::guard('api')->user();
                if ($user) {
                    $payload = JWTAuth::setToken($token)->getPayload();
                    $tokenSid = $payload->get('session_id');
                    $activeSid = Cache::get("user_active_session_{$user->id}");

                    // Nếu tài khoản đã được cấp session mới và session trong token hiện tại không khớp
                    if ($activeSid && $tokenSid && $tokenSid !== $activeSid) {
                        return response()->json([
                            'error' => 'Tài khoản của bạn đã được đăng nhập từ một thiết bị hoặc trình duyệt khác. Phiên làm việc này đã bị đăng xuất!',
                            'message' => 'Tài khoản của bạn đã được đăng nhập từ một thiết bị hoặc trình duyệt khác. Phiên làm việc này đã bị đăng xuất!',
                            'code' => 'CONCURRENT_LOGIN'
                        ], 401);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Không làm gián đoạn luồng nếu token không hợp lệ (JWT auth middleware sẽ kiểm tra sau)
        }

        return $next($request);
    }
}

