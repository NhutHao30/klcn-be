<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\TaiKhoan;
use App\Models\NguoiDung;

class AuthController extends Controller
{
    /**
     * API Login
     */
    public function login(Request $request)
    {
        $loginInput = $request->input('username') 
            ?? $request->input('USERNAME') 
            ?? $request->input('email') 
            ?? $request->input('EMAIL')
            ?? $request->input('login');
            
        $password = $request->input('password') 
            ?? $request->input('PASSWORD');

        if (!$loginInput || !$password) {
            return response()->json(['error' => 'Vui lòng nhập tên đăng nhập và mật khẩu'], 422);
        }

        // Tìm tài khoản theo username hoặc email
        $account = TaiKhoan::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        if (!$account || !Hash::check($password, $account->password)) {
            return response()->json(['error' => 'Tài khoản hoặc mật khẩu không chính xác'], 401);
        }

        if ($account->trangThai === 0) {
            return response()->json(['error' => 'Tài khoản của bạn đã bị khóa'], 403);
        }

        // Kiểm tra chi nhánh tạm đóng cửa đối với tài khoản Nhân sự (Role !== 1 & not admin)
        if ((int)$account->vaiTroId !== 1 && strtolower($account->username) !== 'admin') {
            $profile = \App\Models\NguoiDung::where('taiKhoanId', $account->id)->first();
            if ($profile && $profile->cuaHangId) {
                $store = \App\Models\CuaHang::find($profile->cuaHangId);
                if ($store && (int)$store->trangThai === 0) {
                    $storeName = $store->tenCuaHang ?: "Chi nhánh #{$store->id}";
                    return response()->json([
                        'error' => "Chi nhánh \"{$storeName}\" hiện đang tạm đóng cửa. Bạn không thể đăng nhập cho đến khi được điều chuyển sang chi nhánh khác!"
                    ], 403);
                }
            }
        }

        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');
        
        // Single Session: Tạo session id mới và lưu vào Cache để đá các phiên đăng nhập cũ
        $sessionId = (string) \Illuminate\Support\Str::uuid();
        Cache::forever("user_active_session_{$account->id}", $sessionId);
        $token = $guard->claims(['session_id' => $sessionId])->login($account);

        // Ghi nhận trực tuyến cho hệ thống giám sát & phân tích người dùng
        \App\Services\UserPresenceService::record($account, $request);

        // Ghi nhận trực tuyến nếu là nhân viên/CSKH/Admin (vai trò khác khách hàng)
        if ((int)$account->vaiTroId !== 6) {
            \App\Services\StaffPresenceService::recordOnline((int)$account->id);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Gửi mã OTP xác thực về Gmail trước khi đăng ký tài khoản
     */
    public function sendRegisterOtp(Request $request)
    {
        $username = trim((string)($request->input('username') ?? $request->input('USERNAME') ?? ''));
        $password = (string)($request->input('password') ?? $request->input('PASSWORD') ?? '');
        $email = strtolower(trim((string)($request->input('email') ?? $request->input('EMAIL') ?? '')));
        $hoTen = trim((string)($request->input('hoTen') ?? $request->input('ho_ten') ?? $request->input('HOTEN') ?? $request->input('name') ?? ''));
        $sdt = trim((string)($request->input('soDienThoai') ?? $request->input('so_dien_thoai') ?? $request->input('SDT') ?? $request->input('sdt') ?? $request->input('phone') ?? ''));

        $request->merge([
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'hoTen' => $hoTen,
            'soDienThoai' => $sdt,
        ]);

        $request->validate([
            'username' => 'required|string|unique:taikhoan,username',
            'password' => [
                'required',
                'string',
                'min:10',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
            'email' => 'required|email|unique:taikhoan,email',
            'hoTen' => 'required|string',
            'soDienThoai' => 'required|string|max:15',
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập!',
            'username.unique' => 'Tên đăng nhập này đã tồn tại!',
            'password.required' => 'Vui lòng nhập mật khẩu!',
            'password.min' => 'Mật khẩu phải có ít nhất 10 ký tự!',
            'password.regex' => 'Mật khẩu phải bao gồm ít nhất 1 chữ cái viết hoa, 1 chữ số và 1 ký tự đặc biệt!',
            'email.required' => 'Vui lòng nhập địa chỉ email!',
            'email.email' => 'Địa chỉ email không đúng định dạng!',
            'email.unique' => 'Email này đã được đăng ký cho tài khoản khác!',
            'hoTen.required' => 'Vui lòng nhập họ và tên!',
            'soDienThoai.required' => 'Vui lòng nhập số điện thoại!',
        ]);

        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = 'register_otp_' . md5($email);

        Cache::store('file')->put($cacheKey, [
            'otp' => $otp,
            'email' => $email,
            'username' => $username,
            'created_at' => now()->toDateTimeString(),
        ], 600); // Hết hạn sau 10 phút

        $htmlContent = "
            <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; border: 1px solid #e5d5c5; border-radius: 12px; overflow: hidden;'>
                <div style='background: #5c3d2e; color: #fff; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 22px;'>GIVRAL BAKERY - DOLA BAKERY</h2>
                    <p style='margin: 6px 0 0; font-size: 13px; color: #f3e5d8;'>Xác thực đăng ký tài khoản thành viên</p>
                </div>
                <div style='padding: 24px; background: #ffffff; color: #333;'>
                    <p>Xin chào <b>" . htmlspecialchars($hoTen) . "</b>,</p>
                    <p>Cảm ơn bạn đã đăng ký tài khoản tại <b>Givral Bakery</b> với tên đăng nhập <b>" . htmlspecialchars($username) . "</b>.</p>
                    <p>Vui lòng nhập mã OTP gồm 6 chữ số dưới đây để hoàn tất kích hoạt tài khoản của bạn:</p>
                    <div style='text-align: center; margin: 24px 0;'>
                        <span style='display: inline-block; background: #fffbeb; border: 2px dashed #d97706; color: #b45309; font-size: 30px; font-weight: bold; letter-spacing: 8px; padding: 14px 28px; border-radius: 10px;'>
                            {$otp}
                        </span>
                    </div>
                    <p style='font-size: 13px; color: #666;'>Mã OTP này có hiệu lực trong vòng <b>10 phút</b>. Tuyệt đối không chia sẻ mã này cho bất kỳ ai.</p>
                </div>
                <div style='background: #f8fafc; padding: 14px; text-align: center; font-size: 12px; color: #64748b;'>
                    © " . date('Y') . " Givral Bakery. Mọi quyền được bảo lưu.
                </div>
            </div>
        ";

        try {
            Mail::html($htmlContent, function ($message) use ($email, $hoTen, $otp) {
                $message->to($email, $hoTen)
                    ->subject("[Givral Bakery] Mã xác thực OTP đăng ký tài khoản: {$otp}");
            });
        } catch (\Throwable $mailErr) {
            Log::error('Send Register OTP Mail Error: ' . $mailErr->getMessage());
            return response()->json([
                'error' => 'Không thể gửi email OTP tới địa chỉ ' . $email . '. Vui lòng kiểm tra lại cấu hình mail hoặc địa chỉ Gmail! (' . $mailErr->getMessage() . ')'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Đã gửi mã xác thực OTP gồm 6 chữ số tới Gmail {$email}. Vui lòng kiểm tra hộp thư đến (hoặc thư rác/Spam)!",
            'expires_in' => 600,
        ]);
    }

    /**
     * Customer Registration API (Yêu cầu xác thực OTP đã gửi về Gmail)
     */
    public function register(Request $request)
    {
        $username = trim((string)($request->input('username') ?? $request->input('USERNAME') ?? ''));
        $password = (string)($request->input('password') ?? $request->input('PASSWORD') ?? '');
        $email = strtolower(trim((string)($request->input('email') ?? $request->input('EMAIL') ?? '')));
        $hoTen = trim((string)($request->input('hoTen') ?? $request->input('ho_ten') ?? $request->input('HOTEN') ?? $request->input('name') ?? ''));
        $sdt = trim((string)($request->input('soDienThoai') ?? $request->input('so_dien_thoai') ?? $request->input('SDT') ?? $request->input('sdt') ?? $request->input('phone') ?? ''));
        $gioiTinhRaw = $request->input('gioiTinh') ?? $request->input('GioiTinh') ?? $request->input('gioi_tinh', 0);
        $otpInput = trim((string)($request->input('otp') ?? $request->input('OTP') ?? $request->input('OTP_CODE') ?? ''));
        
        $gioiTinh = 0;
        if ($gioiTinhRaw === 'Nam' || $gioiTinhRaw === 1 || $gioiTinhRaw === '1') {
            $gioiTinh = 1;
        }

        $request->merge([
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'hoTen' => $hoTen,
            'ho_ten' => $hoTen,
            'soDienThoai' => $sdt,
            'so_dien_thoai' => $sdt,
            'gioiTinh' => $gioiTinh,
        ]);

        $request->validate([
            'username' => 'required|string|unique:taikhoan,username',
            'password' => [
                'required',
                'string',
                'min:10',
                'regex:/[A-Z]/',        // ít nhất 1 chữ hoa
                'regex:/[0-9]/',        // ít nhất 1 chữ số
                'regex:/[^A-Za-z0-9]/', // ít nhất 1 ký tự đặc biệt
            ],
            'email'    => 'required|email|unique:taikhoan,email',
            'hoTen'   => 'required|string',
            'soDienThoai' => 'required|string|max:15',
        ], [
            'username.required' => 'Vui lòng nhập tên đăng nhập!',
            'username.unique' => 'Tên đăng nhập này đã tồn tại!',
            'password.required' => 'Vui lòng nhập mật khẩu!',
            'password.min' => 'Mật khẩu phải có ít nhất 10 ký tự!',
            'password.regex' => 'Mật khẩu phải bao gồm ít nhất 1 chữ cái viết hoa, 1 chữ số và 1 ký tự đặc biệt (VD: @, $, !, %, #)!',
            'email.required' => 'Vui lòng nhập địa chỉ email!',
            'email.unique' => 'Email này đã được sử dụng!',
            'hoTen.required' => 'Vui lòng nhập họ và tên!',
            'ho_ten.required' => 'Vui lòng nhập họ và tên!',
            'soDienThoai.required' => 'Vui lòng nhập số điện thoại!',
            'so_dien_thoai.required' => 'Vui lòng nhập số điện thoại!',
        ]);

        // Kiểm tra mã OTP đã gửi về Gmail
        if (!$otpInput) {
            return response()->json([
                'error' => 'Vui lòng nhập mã xác thực OTP được gửi về Gmail của bạn!',
                'require_otp' => true,
            ], 422);
        }

        $cacheKey = 'register_otp_' . md5($email);
        $cachedData = Cache::store('file')->get($cacheKey);
        if (!$cachedData || !isset($cachedData['otp']) || (string)$cachedData['otp'] !== $otpInput) {
            return response()->json([
                'error' => 'Mã xác thực OTP không chính xác hoặc đã hết hạn (10 phút)! Vui lòng kiểm tra lại Gmail hoặc bấm Gửi lại mã.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Tạo Tài khoản (Vai trò 6: CUSTOMER)
            $taikhoan = TaiKhoan::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($password),
                'vaiTroId' => 6, // CUSTOMER
                'trangThai' => 1,
            ]);

            // 2. Tạo Hồ sơ Người dùng (Khách hàng)
            NguoiDung::create([
                'taiKhoanId' => $taikhoan->id,
                'hoTen' => $hoTen,
                'soDienThoai' => $sdt,
                'gioiTinh' => $gioiTinh, // 0: Nữ, 1: Nam
                'loaiNguoiDung' => 0, // 0: Khách hàng
            ]);

            DB::commit();

            // Xóa mã OTP sau khi đăng ký thành công
            Cache::store('file')->forget($cacheKey);

            /** @var \Tymon\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $token = $guard->login($taikhoan);

            return $this->respondWithToken($token);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Đăng ký thất bại: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Quên mật khẩu - gửi OTP về email
     */
    public function forgotPassword(Request $request)
    {
        $email = strtolower(trim((string)($request->input('EMAIL') ?? $request->input('email') ?? '')));
        if (!$email) {
            return response()->json(['error' => 'Vui lòng nhập địa chỉ email!'], 422);
        }

        $account = TaiKhoan::where('email', $email)->first();
        if (!$account) {
            return response()->json(['error' => 'Không tìm thấy tài khoản nào đăng ký với địa chỉ email này!'], 404);
        }

        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = 'forgot_otp_' . md5($email);
        Cache::store('file')->put($cacheKey, [
            'otp' => $otp,
            'email' => $email,
        ], 600);

        $htmlContent = "
            <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; border: 1px solid #e5d5c5; border-radius: 12px; overflow: hidden;'>
                <div style='background: #5c3d2e; color: #fff; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 22px;'>GIVRAL BAKERY</h2>
                    <p style='margin: 6px 0 0; font-size: 13px; color: #f3e5d8;'>Mã OTP khôi phục mật khẩu</p>
                </div>
                <div style='padding: 24px; background: #ffffff; color: #333;'>
                    <p>Xin chào <b>{$account->username}</b>,</p>
                    <p>Mã OTP để đặt lại mật khẩu của bạn là:</p>
                    <div style='text-align: center; margin: 24px 0;'>
                        <span style='display: inline-block; background: #fffbeb; border: 2px dashed #d97706; color: #b45309; font-size: 30px; font-weight: bold; letter-spacing: 8px; padding: 14px 28px; border-radius: 10px;'>
                            {$otp}
                        </span>
                    </div>
                    <p style='font-size: 13px; color: #666;'>Mã OTP có hiệu lực trong 10 phút.</p>
                </div>
            </div>
        ";

        try {
            Mail::html($htmlContent, function ($message) use ($email, $otp) {
                $message->to($email)->subject("[Givral Bakery] Mã OTP đặt lại mật khẩu: {$otp}");
            });
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Lỗi gửi email: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Đã gửi mã OTP khôi phục mật khẩu tới email của bạn!']);
    }

    /**
     * Đặt lại mật khẩu với OTP
     */
    public function resetPassword(Request $request)
    {
        $email = strtolower(trim((string)($request->input('EMAIL') ?? $request->input('email') ?? '')));
        $otp = trim((string)($request->input('OTP_CODE') ?? $request->input('otp') ?? ''));
        $newPassword = (string)($request->input('NEW_PASSWORD') ?? $request->input('new_password') ?? '');

        if (!$email || !$otp || !$newPassword) {
            return response()->json(['error' => 'Vui lòng nhập đầy đủ email, mã OTP và mật khẩu mới!'], 422);
        }

        $cacheKey = 'forgot_otp_' . md5($email);
        $cached = Cache::store('file')->get($cacheKey);
        if (!$cached || (string)($cached['otp'] ?? '') !== $otp) {
            return response()->json(['error' => 'Mã OTP không chính xác hoặc đã hết hạn!'], 422);
        }

        $account = TaiKhoan::where('email', $email)->first();
        if (!$account) {
            return response()->json(['error' => 'Không tìm thấy tài khoản!'], 404);
        }

        $account->password = Hash::make($newPassword);
        $account->save();
        Cache::store('file')->forget($cacheKey);

        return response()->json(['message' => 'Đặt lại mật khẩu thành công! Vui lòng đăng nhập lại.']);
    }

    /**
     * API to retrieve current user profile.
     */
    public function me()
    {
        /** @var \App\Models\TaiKhoan $user */
        $user = Auth::guard('api')->user();
        if ($user) {
            $user->load(['vaitro', 'nguoidung.cuahang']);
            if ($user->nguoidung && method_exists($user->nguoidung, 'syncLoyaltyPoints')) {
                $user->nguoidung->syncLoyaltyPoints();
            }
            $user->setRelation('khachhang', $user->nguoidung);
            $user->setRelation('nhanvien', $user->nguoidung);
        }

        return response()->json($user);
    }

    /**
     * API to update current user's profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $hoTen = $request->input('hoTen') ?? $request->input('HOTEN');
        $sdt = $request->input('soDienThoai') ?? $request->input('SDT');
        $gioiTinhRaw = $request->input('gioiTinh') ?? $request->input('GioiTinh');
        $ngaySinh = $request->input('ngaySinh') ?? $request->input('NGAYSINH');
        $diaChi = $request->input('dia_chi') ?? $request->input('DIACHI');
        $email = $request->input('email') ?? $request->input('EMAIL');

        $gioiTinh = null;
        if (!is_null($gioiTinhRaw)) {
            if (is_numeric($gioiTinhRaw)) {
                $gioiTinh = (int)$gioiTinhRaw;
            } else if (is_string($gioiTinhRaw)) {
                $gtStr = mb_strtolower(trim($gioiTinhRaw));
                if ($gtStr === 'nam') $gioiTinh = 1;
                else if ($gtStr === 'nữ' || $gtStr === 'nu') $gioiTinh = 0;
                else $gioiTinh = 2;
            }
        }

        // Xử lý upload avatar (file hoặc chuỗi URL)
        $avatarUrl = null;
        $avatarFile = $request->file('avatar') ?? $request->file('AVATAR') ?? $request->file('HINHANH') ?? $request->file('hinhAnh');
        if ($avatarFile && $avatarFile->isValid()) {
            $extension = $avatarFile->getClientOriginalExtension() ?: 'png';
            $filename = 'avatar_' . $user->id . '_' . time() . '_' . \Illuminate\Support\Str::random(6) . '.' . $extension;
            $destDir = public_path('assets/avatars');
            if (!file_exists($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $avatarFile->move($destDir, $filename);
            $avatarUrl = '/assets/avatars/' . $filename;
        } elseif ($request->filled('avatar') || $request->filled('AVATAR') || $request->filled('hinhAnh') || $request->filled('HINHANH')) {
            $rawUrl = $request->input('avatar') ?? $request->input('AVATAR') ?? $request->input('hinhAnh') ?? $request->input('HINHANH');
            if (is_string($rawUrl) && trim($rawUrl) !== '') {
                $avatarUrl = trim($rawUrl);
            }
        }

        if ($avatarUrl) {
            $user->update(['avatar' => $avatarUrl]);
        }

        if ($email && $email !== $user->email) {
            $existingEmail = TaiKhoan::where('email', $email)->where('id', '!=', $user->id)->first();
            if (!$existingEmail) {
                $user->update(['email' => $email]);
            }
        }

        $nguoidung = $user->nguoidung;
        if ($nguoidung) {
            $updateData = [];
            if (!is_null($hoTen)) $updateData['hoTen'] = $hoTen;
            if (!is_null($sdt)) $updateData['soDienThoai'] = $sdt;
            if (!is_null($gioiTinh)) $updateData['gioiTinh'] = $gioiTinh;
            if (!is_null($ngaySinh)) $updateData['ngaySinh'] = $ngaySinh;
            if ($avatarUrl) $updateData['avatar'] = $avatarUrl;

            if (!empty($updateData)) {
                $nguoidung->update($updateData);
            }

            if (!empty($diaChi)) {
                $nguoidung->diachi()->updateOrCreate(
                    ['isDefault' => 1],
                    ['diaChiChiTiet' => $diaChi, 'hoTenNguoiNhan' => $nguoidung->hoTen, 'soDienThoai' => $nguoidung->soDienThoai]
                );
            }
        }

        $user->load(['vaitro', 'nguoidung.cuahang']);
        if ($user->nguoidung && method_exists($user->nguoidung, 'syncLoyaltyPoints')) {
            $user->nguoidung->syncLoyaltyPoints();
        }
        $user->setRelation('khachhang', $user->nguoidung);
        $user->setRelation('nhanvien', $user->nguoidung);

        return response()->json([
            'message' => 'Cập nhật thông tin thành công', 
            'user' => $user,
            'nguoidung' => $user->nguoidung,
            'avatar' => $avatarUrl ?? $user->avatar,
            'AVATAR' => $avatarUrl ?? $user->avatar,
        ]);
    }

    /**
     * API Logout
     */
    public function logout()
    {
        $user = Auth::guard('api')->user();
        if ($user && (int)$user->vaiTroId !== 6) {
            \App\Services\StaffPresenceService::recordOffline((int)$user->id);
        }

        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');
        $guard->logout();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    /**
     * API Refresh Token
     */
    public function refresh()
    {
        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');

        return $this->respondWithToken($guard->refresh());
    }

    protected function respondWithToken($token)
    {
        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard->factory()->getTTL() * 60,
            'user' => $guard->user()->load(['vaitro', 'nguoidung'])
        ]);
    }
}
