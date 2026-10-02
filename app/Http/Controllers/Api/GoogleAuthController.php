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
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        
        $referer = $request->headers->get('referer');
        if ($referer) {
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
        try {
            $googleUser = $this->getGoogleDriver($request)->user();

            // Tìm tài khoản theo google_id hoặc email
            $existingUser = TaiKhoan::where('googleId', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($existingUser) {
                // Nếu tìm thấy theo Email nhưng chưa gắn google_id
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
                        'soDienThoai' => '',
                        'gioiTinh'     => 1,
                        'loaiNguoiDung'=> 0, // Khách hàng
                    ]);
                }

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
                // Chưa có tài khoản, lưu thông tin vào Cache (hạn 15 phút)
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
     * Frontend gọi API này để lấy thông tin session Google tạm thời
     */
    public function googleStatus(Request $request)
    {
        $googleId = $request->query('googleId') ?? $request->input('googleId');
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
        $googleId = $request->input('googleId');

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
            return response()->json(['error' => 'Đăng ký thất bại: ' . $e->getMessage()], 500);
        }
    }
}
