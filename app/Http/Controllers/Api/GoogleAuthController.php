<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\TaiKhoan;
use App\Models\NguoiDung;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use GuzzleHttp\Client as GuzzleClient;

class GoogleAuthController extends Controller
{
    /**
     * Tạo driver Socialite Google đồng nhất giữa redirect và callback
     */
    private function getGoogleDriver(Request $request)
    {
        $redirectUri = config('services.google.redirect');
        if (empty($redirectUri)) {
            $redirectUri = env('GOOGLE_REDIRECT_URI');
        }
        if (empty($redirectUri)) {
            $redirectUri = url('/api/auth/google/callback');
        }

        /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
        $driver = Socialite::driver('google')->redirectUrl($redirectUri);

        if (app()->environment('local')) {
            $guzzle = new GuzzleClient(['verify' => false]);
            $driver->setHttpClient($guzzle);
        }

        return $driver->stateless();
    }

    /**
     * Chuyển hướng người dùng sang trang đăng nhập Google
     */
    public function redirect(Request $request)
    {
        $frontendUrl = $request->query('redirect_to') 
            ?? $request->query('frontend_url') 
            ?? env('FRONTEND_URL', 'http://localhost:5173');
        
        $referer = $request->headers->get('referer');
        if (!$request->has('redirect_to') && $referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['scheme']) && isset($parsed['host'])) {
                $frontendUrl = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            }
        }

        return $this->getGoogleDriver($request)
            ->with(['state' => base64_encode($frontendUrl)])
            ->redirect();
    }

    /**
     * Xử lý callback từ Google
     */
    public function callback(Request $request)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        if ($request->has('state')) {
            $decoded = base64_decode($request->state);
            if (filter_var($decoded, FILTER_VALIDATE_URL) || str_starts_with($decoded, 'frontend://') || str_starts_with($decoded, 'exp://')) {
                $frontendUrl = $decoded;
            }
        }

        $isMobileDeepLink = str_starts_with($frontendUrl, 'frontend://') 
            || str_starts_with($frontendUrl, 'exp://') 
            || str_starts_with($frontendUrl, 'givral://');

        $buildRedirect = function ($path, $params = []) use ($frontendUrl, $isMobileDeepLink) {
            if ($isMobileDeepLink) {
                $base = strtok($frontendUrl, '?');
                $query = http_build_query($params);
                return $base . ($query ? '?' . $query : '');
            }
            $base = rtrim($frontendUrl, '/');
            $url = $base . $path;
            if (!empty($params)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }
            return $url;
        };

        $respondRedirect = function ($path, $params = []) use ($buildRedirect, $isMobileDeepLink) {
            $targetUrl = $buildRedirect($path, $params);
            if ($isMobileDeepLink) {
                $html = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Givral Bakery - Xác thực Google</title>
    <meta http-equiv="refresh" content="1;url={$targetUrl}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #fff7ed 0%, #fef3c7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #ffffff;
            border-radius: 24px;
            padding: 36px 28px;
            max-width: 380px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(180, 83, 9, 0.12);
            border: 1px solid #fde68a;
        }
        .icon {
            font-size: 54px;
            margin-bottom: 16px;
            display: inline-block;
            animation: bounce 1s infinite alternate;
        }
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-8px); }
        }
        h2 {
            color: #92400e;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        p {
            color: #78350f;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 14px;
            box-shadow: 0 8px 16px rgba(217, 119, 6, 0.3);
            transition: all 0.2s ease;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid #fde68a;
            border-top: 3px solid #b45309;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 16px auto 0;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🎂</div>
        <h2>Đăng nhập Google thành công!</h2>
        <p>Hệ thống đang mở ứng dụng <b>Givral Bakery</b> để hoàn tất đăng nhập của bạn...</p>
        <a href="{$targetUrl}" class="btn" id="open-btn">Mở ứng dụng Givral Bakery</a>
        <div class="spinner"></div>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "{$targetUrl}";
        }, 200);
    </script>
</body>
</html>
HTML;
                return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
            }
            return redirect($targetUrl);
        };

        if ($request->has('error')) {
            Log::warning('Google OAuth cancelled or returned error: ' . $request->get('error'));
            return $respondRedirect('/dang-nhap', ['error' => 'google_cancelled']);
        }

        try {
            $googleUser = $this->getGoogleDriver($request)->user();

            if (!$googleUser || !$googleUser->getEmail()) {
                Log::error('Google OAuth failed to get user email');
                return $respondRedirect('/dang-nhap', ['error' => 'google_no_email']);
            }

            // Tìm tài khoản theo googleId hoặc email
            $existingUser = TaiKhoan::where('googleId', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($existingUser) {
                // Nếu tìm thấy theo Email nhưng chưa gắn googleId
                if (!$existingUser->googleId) {
                    $existingUser->update([
                        'googleId' => $googleUser->getId(),
                    ]);
                }

                // Nếu tài khoản chưa có thông tin NguoiDung profile
                if (!$existingUser->nguoidung) {
                    NguoiDung::create([
                        'taiKhoanId'   => $existingUser->id,
                        'hoTen'        => $googleUser->getName() ?? $existingUser->username,
                        'soDienThoai' => null,
                        'gioiTinh'     => 1,
                        'loaiNguoiDung'=> 0, // Khách hàng
                    ]);
                }
            } else {
                // Tự động tạo tài khoản Khách hàng mới cho người dùng Google
                DB::beginTransaction();
                try {
                    $email = $googleUser->getEmail();
                    $name = $googleUser->getName() ?? explode('@', $email)[0];
                    $username = 'kh_' . strtolower(substr(md5($googleUser->getId() . time()), 0, 8));
                    $password = Hash::make(Str::random(16));

                    $existingUser = TaiKhoan::create([
                        'username'  => $username,
                        'email'     => $email,
                        'password'  => $password,
                        'vaiTroId' => 6, // Khách hàng (CUSTOMER)
                        'googleId' => $googleUser->getId(),
                        'trangThai'=> 1,
                    ]);

                    NguoiDung::create([
                        'taiKhoanId'   => $existingUser->id,
                        'hoTen'        => $name,
                        'soDienThoai' => null,
                        'gioiTinh'     => 1,
                        'loaiNguoiDung'=> 0, // Khách hàng
                    ]);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Auto Create Google User Failed: ' . $e->getMessage());
                    throw $e;
                }
            }

            /** @var \Tymon\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $sessionId = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\Cache::forever("user_active_session_{$existingUser->id}", $sessionId);
            $token = $guard->claims(['session_id' => $sessionId])->login($existingUser);

            return $respondRedirect('/', ['token' => $token, 'google' => 'true']);

        } catch (\Exception $e) {
            Log::error('Google Login Error: ' . $e->getMessage());
            return $respondRedirect('/dang-nhap', ['error' => 'google_login_failed']);
        }
    }

    /**
     * Frontend gọi API này để lấy thông tin session Google tạm thời
     */
    public function googleStatus(Request $request)
    {
        $googleId = $request->query('googleId') 
            ?? $request->query('google_id') 
            ?? $request->input('googleId') 
            ?? $request->input('google_id');

        if ($googleId && \Illuminate\Support\Facades\Cache::has('google_reg_' . $googleId)) {
            $googleUser = \Illuminate\Support\Facades\Cache::get('google_reg_' . $googleId);
            return response()->json([
                'status' => 'need_info',
                'name' => $googleUser['name'],
                'email' => $googleUser['email'],
                'avatar' => $googleUser['avatar'],
            ]);
        }

        return response()->json(['status' => 'failed'], 400);
    }

    /**
     * Đăng ký tài khoản Google mới khi bổ sung sđt, giới tính
     */
    public function googleRegister(Request $request)
    {
        $googleId = $request->input('googleId') 
            ?? $request->input('google_id') 
            ?? $request->query('googleId') 
            ?? $request->query('google_id');

        if (!$googleId || !\Illuminate\Support\Facades\Cache::has('google_reg_' . $googleId)) {
            return response()->json(['error' => 'Phiên đăng nhập Google đã hết hạn'], 400);
        }

        $request->validate([
            'sdt' => 'required|string|max:15',
            'gioiTinh' => 'required|string',
        ]);

        $googleUser = \Illuminate\Support\Facades\Cache::get('google_reg_' . $googleId);

        DB::beginTransaction();
        try {
            $username = 'kh_' . strtolower(substr(md5(time() . rand()), 0, 8));
            $password = Hash::make(rand(100000, 999999) . 'Aa@!');

            $taikhoan = TaiKhoan::create([
                'username'  => $username,
                'email'     => $googleUser['email'],
                'password'  => $password,
                'vaiTroId' => 6, // CUSTOMER
                'googleId' => $googleUser['id'],
                'trangThai'=> 1,
            ]);

            $gioiTinhInt = ($request->gioiTinh === 'Nam' || $request->gioiTinh === '1' || $request->gioiTinh === 1) ? 1 : 0;

            NguoiDung::create([
                'taiKhoanId'   => $taikhoan->id,
                'hoTen'        => $googleUser['name'],
                'soDienThoai' => $request->sdt,
                'gioiTinh'     => $gioiTinhInt,
                'loaiNguoiDung'=> 0, // Khách hàng
            ]);

            DB::commit();

            \Illuminate\Support\Facades\Cache::forget('google_reg_' . $googleId);

            /** @var \Tymon\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $sessionId = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\Cache::forever("user_active_session_{$taikhoan->id}", $sessionId);
            $token = $guard->claims(['session_id' => $sessionId])->login($taikhoan);

            return response()->json([
                'message' => 'Đăng ký thành công',
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $guard->factory()->getTTL() * 60,
                'user' => $guard->user()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Google Register Error: ' . $e->getMessage());
            return response()->json(['error' => 'Đăng ký thất bại: ' . $e->getMessage()], 500);
        }
    }
}

