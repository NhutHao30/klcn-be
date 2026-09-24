<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        if ($account->trang_thai === 0) {
            return response()->json(['error' => 'Tài khoản của bạn đã bị khóa'], 403);
        }

        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = Auth::guard('api');
        $token = $guard->login($account);

        return $this->respondWithToken($token);
    }

    /**
     * Customer Registration API
     */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:taikhoan,username',
            'password' => 'required|string|min:6',
            'email'    => 'required|email|unique:taikhoan,email',
            'ho_ten'   => 'required|string',
            'so_dien_thoai' => 'required|string|max:15',
        ]);

        DB::beginTransaction();
        try {
            // 1. Tạo Tài khoản (Vai trò 6: CUSTOMER)
            $taikhoan = TaiKhoan::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'vaitro_id' => 6, // CUSTOMER
                'trang_thai' => 1,
            ]);

            // 2. Tạo Hồ sơ Người dùng (Khách hàng)
            NguoiDung::create([
                'taikhoan_id' => $taikhoan->id,
                'ho_ten' => $request->ho_ten,
                'so_dien_thoai' => $request->so_dien_thoai,
                'gioi_tinh' => $request->gioi_tinh ?? 0, // 0: Nữ, 1: Nam
                'loai_nguoidung' => 0, // 0: Khách hàng
            ]);

            DB::commit();

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
     * API to retrieve current user profile.
     */
    public function me()
    {
        /** @var \App\Models\TaiKhoan $user */
        $user = Auth::guard('api')->user();
        if ($user) {
            $user->load(['vaitro', 'nguoidung.cuahang']);
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

        $request->validate([
            'ho_ten' => 'required|string|max:100',
            'so_dien_thoai' => 'nullable|string|max:15',
            'gioi_tinh' => 'nullable|integer|in:0,1,2',
            'ngay_sinh' => 'nullable|date',
        ]);

        $nguoidung = $user->nguoidung;
        if ($nguoidung) {
            $nguoidung->update($request->only(['ho_ten', 'so_dien_thoai', 'gioi_tinh', 'ngay_sinh']));
        }

        return response()->json(['message' => 'Cập nhật thông tin thành công', 'user' => $nguoidung]);
    }

    /**
     * API Logout
     */
    public function logout()
    {
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
