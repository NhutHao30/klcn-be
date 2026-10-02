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

        if ($account->trangThai === 0) {
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
        // Chuẩn hóa param đầu vào (hỗ trợ cả chữ thường và chữ hoa từ Frontend)
        $username = $request->input('username', $request->input('USERNAME'));
        $password = $request->input('password', $request->input('PASSWORD'));
        $email = $request->input('email', $request->input('EMAIL'));
        $hoTen = $request->input('hoTen', $request->input('HOTEN', $request->input('hoTen')));
        $sdt = $request->input('soDienThoai', $request->input('SDT', $request->input('sdt')));
        $gioiTinhRaw = $request->input('gioiTinh', $request->input('GioiTinh', 0));
        
        $gioiTinh = 0;
        if ($gioiTinhRaw === 'Nam' || $gioiTinhRaw === 1 || $gioiTinhRaw === '1') {
            $gioiTinh = 1;
        }

        $request->merge([
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'hoTen' => $hoTen,
            'soDienThoai' => $sdt,
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
            'ho_ten.required' => 'Vui lòng nhập họ và tên!',
            'so_dien_thoai.required' => 'Vui lòng nhập số điện thoại!',
        ]);

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
            $user->load(['vaitro', 'nguoidung.cuahang', 'khachhang.cuahang', 'nhanvien.cuahang']);
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

        $nguoidung = $user->nguoidung;
        if ($nguoidung) {
            $updateData = [];
            if (!is_null($hoTen)) $updateData['hoTen'] = $hoTen;
            if (!is_null($sdt)) $updateData['soDienThoai'] = $sdt;
            if (!is_null($gioiTinh)) $updateData['gioiTinh'] = $gioiTinh;
            if (!is_null($ngaySinh)) $updateData['ngaySinh'] = $ngaySinh;

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
