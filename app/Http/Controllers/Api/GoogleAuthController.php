<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\TaiKhoan;
use App\Models\KhachHang;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    /**
     * Chuyển hướng người dùng sang trang đăng nhập Google
     */
    public function redirect(Request $request)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        
        $referer = $request->headers->get('referer');
        if ($referer) {
            $parsed = parse_url($referer);
            if (isset($parsed['scheme']) && isset($parsed['host'])) {
                $frontendUrl = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            }
        }

        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => base64_encode($frontendUrl)])
            ->redirect();
    }

    /**
     * Xử lý callback từ Google
     */
    public function callback(Request $request)
    {
        try {
            // Chỉ bypass SSL ở môi trường local (môi trường dev thường bị thiếu cert)
            $isLocal = app()->environment('local');
            if ($isLocal) {
                $guzzle = new \GuzzleHttp\Client(['verify' => false]);
                $googleUser = Socialite::driver('google')->setHttpClient($guzzle)->stateless()->user();
            } else {
                $googleUser = Socialite::driver('google')->stateless()->user();
            }

            // Tìm tài khoản theo GOOGLE_ID
            $existingUser = TaiKhoan::where('GOOGLE_ID', $googleUser->getId())->first();

            if (!$existingUser) {
                // Thử tìm theo Email, nếu có thì liên kết Google ID
                $existingUser = TaiKhoan::where('EMAIL', $googleUser->getEmail())->first();
                if ($existingUser) {
                    $existingUser->update([
                        'GOOGLE_ID' => $googleUser->getId(),
                    ]);
                }
            }

            if ($existingUser) {
                // Đăng nhập luôn
                /** @var \Tymon\JWTAuth\JWTGuard $guard */
                $guard = Auth::guard('api');
                $token = $guard->login($existingUser);

                // Trả về Frontend kèm token
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                if ($request->has('state')) {
                    $decoded = base64_decode($request->state);
                    if (filter_var($decoded, FILTER_VALIDATE_URL)) {
                        $frontendUrl = $decoded;
                    }
                }
                return redirect($frontendUrl . '/?token=' . $token . '&google=true');
            } else {
                // Chưa có tài khoản, lưu thông tin vào Cache (vì API route không có Session)
                $googleId = $googleUser->getId();
                \Illuminate\Support\Facades\Cache::put('google_reg_' . $googleId, [
                    'id' => $googleId,
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'avatar' => $googleUser->getAvatar()
                ], now()->addMinutes(15));

                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                if ($request->has('state')) {
                    $decoded = base64_decode($request->state);
                    if (filter_var($decoded, FILTER_VALIDATE_URL)) {
                        $frontendUrl = $decoded;
                    }
                }
                return redirect($frontendUrl . '/dang-ky-google?google_id=' . $googleId);
            }

        } catch (\Exception $e) {
            Log::error('Google Login Error: ' . $e->getMessage());
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            if ($request->has('state')) {
                $decoded = base64_decode($request->state);
                if (filter_var($decoded, FILTER_VALIDATE_URL)) {
                    $frontendUrl = $decoded;
                }
            }
            return redirect($frontendUrl . '/dang-nhap?error=google_login_failed');
        }
    }

    /**
     * Frontend gọi API này để lấy thông tin session
     */
    public function googleStatus(Request $request)
    {
        $googleId = $request->query('google_id') ?? $request->input('google_id');
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
     * Đăng ký tài khoản Google mới khi đã có thông tin sđt, giới tính
     */
    public function googleRegister(Request $request)
    {
        $googleId = $request->input('google_id');

        if (!$googleId || !\Illuminate\Support\Facades\Cache::has('google_reg_' . $googleId)) {
            return response()->json(['error' => 'Phiên đăng nhập Google đã hết hạn'], 400);
        }

        $request->validate([
            'sdt' => 'required|string|size:10',
            'gioiTinh' => 'required|string|in:Nam,Nữ',
        ]);

        $googleUser = \Illuminate\Support\Facades\Cache::get('google_reg_' . $googleId);

        DB::beginTransaction();
        try {
            // Tạo tài khoản (Khách hàng mặc định mật khẩu random)
            $username = 'kh_' . strtolower(substr(md5(time()), 0, 8)); // Generate a random username
            $password = bcrypt(rand(100000, 999999) . 'Aa@!'); // Random password

            $taikhoan = TaiKhoan::create([
                'USERNAME' => $username,
                'PASSWORD' => $password,
                'MAROLE'   => 3, // Customer
                'EMAIL'    => $googleUser['email'],
                'GOOGLE_ID' => $googleUser['id'],
                'AVATAR'   => $googleUser['avatar'],
            ]);

            // Tạo thông tin khách hàng
            KhachHang::create([
                'MAKH'     => 'KH' . time(),
                'HOTEN'    => $googleUser['name'],
                'SDT'      => $request->sdt,
                'GioiTinh' => $request->gioiTinh,
                'USERNAME' => $taikhoan->USERNAME,
            ]);

            DB::commit();

            // Clear cache
            \Illuminate\Support\Facades\Cache::forget('google_reg_' . $googleId);

            /** @var \Tymon\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $token = $guard->login($taikhoan);

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
            return response()->json(['error' => 'Đăng ký thất bại, vui lòng thử lại!'], 500);
        }
    }
}
