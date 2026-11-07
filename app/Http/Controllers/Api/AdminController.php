<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\TaiKhoan;
use App\Models\NhanVien;
use App\Models\ChiTietDonHang;

class AdminController extends Controller
{
    /**
     * Đảm bảo các vai trò hệ thống (7: Kế toán, 8: Giám đốc) và cột ca làm việc, lương tồn tại
     */
    public static function ensureRolesAndColumnsExist()
    {
        try {
            $vaitroTable = \Illuminate\Support\Facades\Schema::hasTable('vaiTro') ? 'vaiTro' : 'vaitro';
            if (\Illuminate\Support\Facades\Schema::hasTable($vaitroTable)) {
                $tenCol = \Illuminate\Support\Facades\Schema::hasColumn($vaitroTable, 'tenVaiTro') ? 'tenVaiTro' : 'ten_vaitro';
                $moTaCol = \Illuminate\Support\Facades\Schema::hasColumn($vaitroTable, 'moTa') ? 'moTa' : 'mo_ta';
                $createdCol = \Illuminate\Support\Facades\Schema::hasColumn($vaitroTable, 'createdAt') ? 'createdAt' : 'created_at';
                $updatedCol = \Illuminate\Support\Facades\Schema::hasColumn($vaitroTable, 'updatedAt') ? 'updatedAt' : 'updated_at';

                $rolesToEnsure = [
                    1 => ['name' => 'ADMIN', 'desc' => 'Quản trị hệ thống'],
                    2 => ['name' => 'MANAGER', 'desc' => 'Quản lý chi nhánh'],
                    3 => ['name' => 'CASHIER', 'desc' => 'Nhân viên bán hàng POS'],
                    4 => ['name' => 'WAREHOUSE', 'desc' => 'Nhân viên kho'],
                    5 => ['name' => 'CSKH', 'desc' => 'Nhân viên tư vấn CSKH'],
                    6 => ['name' => 'CUSTOMER', 'desc' => 'Khách hàng mua sắm'],
                    7 => ['name' => 'ACCOUNTANT', 'desc' => 'Nhân viên kế toán chi nhánh'],
                    8 => ['name' => 'DIRECTOR', 'desc' => 'Ban Giám đốc'],
                ];

                foreach ($rolesToEnsure as $id => $info) {
                    $exists = DB::table($vaitroTable)->where('id', $id)->first();
                    if (!$exists) {
                        $insertData = [
                            'id' => $id,
                            $tenCol => $info['name'],
                            $moTaCol => $info['desc'],
                        ];
                        if (\Illuminate\Support\Facades\Schema::hasColumn($vaitroTable, $createdCol)) {
                            $insertData[$createdCol] = now();
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn($vaitroTable, $updatedCol)) {
                            $insertData[$updatedCol] = now();
                        }
                        DB::table($vaitroTable)->insert($insertData);
                    }
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('nguoidung')) {
                \Illuminate\Support\Facades\Schema::table('nguoidung', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('nguoidung', 'caLamViec')) {
                        $table->string('caLamViec', 50)->default('Ca Sáng')->nullable();
                    }
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('nguoidung', 'luong')) {
                        $table->decimal('luong', 15, 2)->default(0)->nullable();
                    }
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("ensureRolesAndColumnsExist warning: " . $e->getMessage());
        }
    }

    /**
     * View a list of all accounts
     */
    public function listUsers()
    {
        self::ensureRolesAndColumnsExist();

        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $query = TaiKhoan::with(['nguoidung.cuahang', 'nguoidung.diachi', 'vaitro']);

        if ($user && (int)$user->vaiTroId === 2) {
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
            $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;

            $query->where(function ($q) use ($macuahang, $user) {
                $q->where(function ($q2) use ($macuahang) {
                    $q2->whereIn('vaiTroId', [3, 4, 5, 7, 8])
                       ->whereHas('nguoidung', function ($q3) use ($macuahang) {
                           $q3->where('cuaHangId', $macuahang);
                       });
                })->orWhere('id', $user->id);
            });
        }

        $users = $query->get()->map(function ($u) {
            $nd = $u->nguoidung;
            $isCustomer = (int)$u->vaiTroId === 6 || ($nd && (int)$nd->loaiNguoiDung === 0);
            $isAdminSuper = strtolower($u->username) === 'admin';

            return [
                'id' => $u->id,
                'username' => $u->username,
                'USERNAME' => $u->username,
                'email' => $u->email,
                'EMAIL' => $u->email,
                'trangThai' => (int)$u->trangThai,
                'TRANGTHAI' => (int)$u->trangThai === 1 ? 'Đang làm việc' : 'Nghỉ việc',
                'vaiTroId' => $u->vaiTroId,
                'MAROLE' => $u->MAROLE,
                'vaitro' => $u->vaitro,
                'nguoidung' => $nd,
                'khachhang' => $isCustomer ? $nd : null,
                'nhanvien' => (!$isCustomer && !$isAdminSuper) ? $nd : null,
                'createdAt' => $u->createdAt,
            ];
        });

        return response()->json($users);
    }

    public function createStaff(Request $request)
    {
        self::ensureRolesAndColumnsExist();

        $request->validate([
            'USERNAME' => 'nullable|string',
            'PASSWORD' => 'nullable|string',
            'HOTEN'    => 'required|string',
            'CHUCVU'   => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $cccd = trim($request->CCCD ?? $request->cccd ?? '');
            $username = trim($request->USERNAME ?? $request->userName ?? '') ?: ($cccd ?: 'NV' . sprintf('%06d', rand(1, 999999)));
            $password = trim($request->PASSWORD ?? $request->password ?? '') ?: ($cccd ?: $username);
            $email = trim($request->EMAIL ?? $request->email ?? '') ?: ($username . '@dolabakery.com');
            $marole = (int)($request->MAROLE ?? $request->maRole ?? $request->vaiTroId ?? 3); // 3 = Thu ngân/Bán hàng mặc định

            $isBranchManager = $userLogin && ((int)$userLogin->vaiTroId === 2 || (int)$userLogin->MAROLE === 1);
            if ($isBranchManager && !in_array($marole, [3, 4, 5, 7], true)) {
                $marole = 3;
            }

            // Nếu username đã tồn tại (ví dụ quét lại cùng thẻ CCCD demo), tự động tạo username duy nhất
            if (TaiKhoan::where('username', $username)->exists()) {
                $username = $username . '_' . rand(10, 99);
            }

            if (TaiKhoan::where('email', $email)->exists()) {
                $email = $username . '_' . rand(100, 999) . '@dolabakery.com';
            }

            $tk = TaiKhoan::create([
                'username' => $username,
                'password' => $password,
                'email'    => $email,
                'vaiTroId' => $marole,
                'trangThai' => 1,
            ]);

            $macuahang = $request->MACUAHANG ?? $request->macuahang ?? 1;
            if ($isBranchManager) {
                $profileLogin = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
                if ($profileLogin && $profileLogin->cuaHangId) {
                    $macuahang = $profileLogin->cuaHangId;
                }
            }

            $caLamViec = $request->input('CALAMVIEC', $request->input('caLamViec', 'Ca Sáng'));
            $luong = (float)$request->input('LUONG', $request->input('luong', 0));
            $gioiTinhInput = $request->input('GioiTinh', $request->input('gioiTinh', 'Nam'));
            $gioiTinhVal = ($gioiTinhInput === 'Nữ' || $gioiTinhInput === 0 || $gioiTinhInput === '0') ? 0 : 1;

            // Tránh lỗi unique constraint trên cột cccdNumber nếu thẻ CCCD đã được quét trước đó
            $cccdToSave = $cccd ?: null;
            if ($cccdToSave && \App\Models\NguoiDung::where('cccdNumber', $cccdToSave)->exists()) {
                $cccdToSave = $cccdToSave . '_' . rand(10, 99);
            }

            $cccdImageUrl = null;
            if ($request->hasFile('CCCD_TRUOC') && $request->file('CCCD_TRUOC')->isValid()) {
                $file = $request->file('CCCD_TRUOC');
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $fname = 'cccd_' . $tk->id . '_' . time() . '.' . $ext;
                $dest = public_path('assets/cccd');
                if (!file_exists($dest)) {
                    @mkdir($dest, 0755, true);
                }
                $file->move($dest, $fname);
                $cccdImageUrl = '/assets/cccd/' . $fname;
            }

            $nd = \App\Models\NguoiDung::create([
                'taiKhoanId' => $tk->id,
                'cuaHangId'  => $macuahang,
                'hoTen'      => $request->HOTEN ?? $request->hoTen,
                'soDienThoai' => $request->SDT ?? $request->sdt ?? null,
                'gioiTinh'   => $gioiTinhVal,
                'ngaySinh'   => ($request->NGAYSINH ?? $request->ngaySinh) ?: null,
                'cccdNumber' => $cccdToSave,
                'cccdImageUrl' => $cccdImageUrl,
                'loaiNguoiDung' => 1, // Staff
                'caLamViec'  => $caLamViec,
                'luong'      => $luong,
            ]);

            $diaChiText = trim((string)($request->input('DIACHI') ?? $request->input('diaChi') ?? ''));
            if ($diaChiText !== '' && $nd) {
                try {
                    $nd->diachi()->create([
                        'hoTenNguoiNhan' => $nd->hoTen,
                        'soDienThoai' => $nd->soDienThoai ?: '0900000000',
                        'diaChiChiTiet' => $diaChiText,
                        'maTinh' => 202,
                        'maHuyen' => 1442,
                        'maXa' => '20101',
                        'isDefault' => 1,
                    ]);
                } catch (\Throwable $eAddr) {}
            }

            DB::commit();
            return response()->json(['message' => 'Tạo tài khoản và thông tin nhân viên thành công!'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error("createStaff error: " . $e->getMessage());
            return response()->json(['message' => 'Tạo nhân viên thất bại: ' . $e->getMessage()], 500);
        }
    }

    public function importStaff(Request $request)
    {
        self::ensureRolesAndColumnsExist();

        $request->validate([
            'file' => 'required|file'
        ]);

        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahangDefault = 1;
        if ($userLogin && (int)$userLogin->MAROLE === 2) {
            $profileLogin = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            if ($profileLogin && $profileLogin->cuaHangId) {
                $macuahangDefault = $profileLogin->cuaHangId;
            }
        }

        $file = $request->file('file');
        $rawContent = file_get_contents($file->getRealPath());
        // Remove BOM and carriage returns
        $rawContent = trim($rawContent, "\xEF\xBB\xBF");
        $rawContent = str_replace("\r", "", $rawContent);

        $lines = array_filter(explode("\n", $rawContent), 'strlen');
        $rows = array_map('str_getcsv', $lines);
        $header = array_shift($rows);

        $successCount = 0;
        $errorCount = 0;

        foreach ($rows as $row) {
            if (empty($row) || !isset($row[0])) {
                continue;
            }

            $username = trim($row[0] ?? '');
            $hoTen = trim($row[1] ?? '');
            $chucVu = trim($row[2] ?? 'Nhân viên bán hàng');
            $luong = floatval($row[3] ?? 0);
            $sdt = trim($row[4] ?? '');
            $email = trim($row[5] ?? '');
            $macuahang = intval($row[6] ?? $macuahangDefault);
            $caLamViec = trim($row[7] ?? '') ?: 'Ca Sáng';

            if (!$username || !$hoTen || strtolower($username) === 'cccd' || strtolower($username) === 'username') {
                continue; // Skip header or invalid empty row
            }

            // Map ChucVu string to MAROLE ID
            $roleId = 3; // Sales default
            $lowerChucVu = mb_strtolower($chucVu);
            if (str_contains($lowerChucVu, 'quản lý') || str_contains($lowerChucVu, 'manager')) {
                $roleId = 2;
            } else if (str_contains($lowerChucVu, 'kho')) {
                $roleId = 4;
            } else if (str_contains($lowerChucVu, 'chăm sóc') || str_contains($lowerChucVu, 'cskh')) {
                $roleId = 5;
            } else if (str_contains($lowerChucVu, 'kế toán') || str_contains($lowerChucVu, 'ketoan') || str_contains($lowerChucVu, 'accountant')) {
                $roleId = 7;
            } else if (str_contains($lowerChucVu, 'giám đốc') || str_contains($lowerChucVu, 'giam doc') || str_contains($lowerChucVu, 'director')) {
                $roleId = 8;
            } else if (str_contains($lowerChucVu, 'quản trị') || str_contains($lowerChucVu, 'admin')) {
                $roleId = 1;
            }

            DB::beginTransaction();
            try {
                $tk = TaiKhoan::where('username', $username)->first();
                if (!$tk) {
                    $tk = TaiKhoan::create([
                        'username' => $username,
                        'password' => $username, // Default pass = CCCD/username
                        'email'    => $email ?: ($username . '@dolabakery.com'),
                        'vaiTroId' => $roleId,
                        'trangThai' => 1,
                    ]);
                } else {
                    // Update role and status if exists
                    $tk->update([
                        'vaiTroId' => $roleId,
                        'trangThai' => 1,
                        'email' => $email ?: $tk->email,
                    ]);
                }

                \App\Models\NguoiDung::updateOrCreate(
                    ['taiKhoanId' => $tk->id],
                    [
                        'cuaHangId'  => $macuahang ?: 1,
                        'hoTen'      => $hoTen,
                        'soDienThoai' => $sdt ?: null,
                        'cccdNumber' => preg_match('/^\d{9,12}$/', $username) ? $username : null,
                        'loaiNguoiDung' => 1,
                        'caLamViec'  => $caLamViec,
                        'luong'      => $luong,
                    ]
                );

                DB::commit();
                $successCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                \Illuminate\Support\Facades\Log::error("Import Staff Row Error [{$username}]: " . $e->getMessage());
                $errorCount++;
            }
        }

        return response()->json([
            'message' => "Import hoàn tất. Thành công: $successCount, Lỗi: $errorCount"
        ]);
    }

    public function updateStaff(Request $request, $username)
    {
        self::ensureRolesAndColumnsExist();

        $nhanVien = \App\Models\NhanVien::findByUsername($username);
        if (!$nhanVien) {
            return response()->json(['error' => 'Không tìm thấy nhân viên'], 404);
        }

        $oldTrangThai = $nhanVien->TRANGTHAI;

        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahangToUpdate = $request->input('MACUAHANG', $request->input('macuahang', $request->input('cuaHangId', $nhanVien->cuaHangId)));

        // Nếu là Quản lý chi nhánh (Role 2), không cho phép sửa/đổi chi nhánh của nhân viên ở chi nhánh khác
        if ($userLogin && ((int)$userLogin->MAROLE === 2 || (int)$userLogin->vaiTroId === 2)) {
            $profileLogin = $userLogin->nguoidung;
            if ($profileLogin && $nhanVien->cuaHangId != $profileLogin->cuaHangId) {
                return response()->json(['message' => 'Bạn không có quyền sửa nhân viên của chi nhánh khác'], 403);
            }
            $macuahangToUpdate = $nhanVien->cuaHangId;
        }

        $hoTen = $request->input('HOTEN', $request->input('hoTen', $nhanVien->hoTen));
        $sdt = $request->input('SDT', $request->input('soDienThoai', $request->input('sdt', $nhanVien->soDienThoai)));
        $ngaySinh = $request->input('NGAYSINH', $request->input('ngaySinh', $nhanVien->ngaySinh));
        $cccd = $request->input('CCCD', $request->input('cccdNumber', $request->input('cccd', $nhanVien->cccdNumber)));
        $gioiTinhRaw = $request->input('GioiTinh', $request->input('gioiTinh', $nhanVien->gioiTinh));
        
        $gioiTinh = 0;
        if ($gioiTinhRaw === 'Nam' || $gioiTinhRaw === 1 || $gioiTinhRaw === '1') {
            $gioiTinh = 1;
        }

        $caLamViec = $request->input('CALAMVIEC', $request->input('caLamViec', $nhanVien->caLamViec ?? 'Ca Sáng'));
        $luong = $request->input('LUONG', $request->input('luong', $nhanVien->luong ?? 0));

        $nhanVien->update([
            'hoTen'       => $hoTen,
            'soDienThoai' => $sdt,
            'ngaySinh'    => $ngaySinh,
            'cccdNumber'  => $cccd,
            'gioiTinh'    => $gioiTinh,
            'cuaHangId'   => $macuahangToUpdate ?: 1,
            'caLamViec'   => $caLamViec,
            'luong'       => (float)$luong,
        ]);

        $maroleToUpdate = $request->input('MAROLE', $request->input('maRole', $request->input('vaiTroId')));
        if ($userLogin && ((int)$userLogin->MAROLE === 2 || (int)$userLogin->vaiTroId === 2)) {
            $maroleToUpdate = null; // Quản lý chi nhánh không được phép đổi chức vụ hệ thống
        }

        $tk = $nhanVien->taikhoan;
        if (!$tk) {
            $tk = \App\Models\TaiKhoan::where('username', $username)->first();
        }

        if ($tk) {
            $tkUpdate = [];

            $email = $request->input('EMAIL', $request->input('email'));
            if ($email) {
                $tkUpdate['email'] = $email;
            }

            if ($maroleToUpdate !== null) {
                $tkUpdate['vaiTroId'] = (int)$maroleToUpdate;
            }

            $trangThaiInput = $request->input('TRANGTHAI', $request->input('trangThai'));
            if ($trangThaiInput !== null) {
                $tkUpdate['trangThai'] = ($trangThaiInput === 'Đang làm việc' || (int)$trangThaiInput === 1) ? 1 : 0;
            }

            if (!empty($tkUpdate)) {
                $tk->update($tkUpdate);
            }

            // Nếu nhân viên được khôi phục từ trạng thái "Nghỉ việc", đặt lại mật khẩu mặc định là Username
            if ($oldTrangThai === 'Nghỉ việc' && ($trangThaiInput === 'Đang làm việc' || (int)$trangThaiInput === 1)) {
                $tk->password = \Illuminate\Support\Facades\Hash::make($tk->username);
                $tk->save();
            }
        }

        return response()->json(['message' => 'Cập nhật nhân viên thành công']);
    }

    public function deleteStaff($username)
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();

        if ($userLogin && $userLogin->username === $username) {
            return response()->json(['message' => 'Bạn không thể tự vô hiệu hóa tài khoản của chính mình'], 403);
        }

        $nhanVien = \App\Models\NhanVien::findByUsername($username);
        $tk = $nhanVien?->taikhoan;

        if (!$tk) {
            $tk = \App\Models\TaiKhoan::where('username', $username)->orWhere('id', $username)->first();
        }

        if ($tk) {
            $tk->update(['trangThai' => 0]);
            return response()->json(['message' => 'Đã vô hiệu hóa tài khoản nhân viên']);
        }

        return response()->json(['error' => 'Không tìm thấy tài khoản nhân viên'], 404);
    }

    public function toggleUserStatus(Request $request, $id)
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        if (!$userLogin || ($userLogin->MAROLE !== 0 && (int)$userLogin->vaiTroId !== 1)) {
            return response()->json(['error' => 'Chỉ Admin mới có quyền khóa/mở khóa tài khoản'], 403);
        }

        $tk = \App\Models\TaiKhoan::find($id);
        if (!$tk) return response()->json(['error' => 'Tài khoản không tồn tại'], 404);

        if ($tk->id === $userLogin->id) {
            return response()->json(['error' => 'Không thể tự khóa tài khoản của chính mình'], 400);
        }

        $newStatus = $tk->trangThai == 1 ? 0 : 1;
        $tk->update(['trangThai' => $newStatus]);

        return response()->json([
            'message' => ($newStatus == 1 ? 'Đã kích hoạt' : 'Đã khóa') . ' tài khoản thành công',
            'data' => $tk
        ]);
    }

    public function updateUserRole(Request $request, $id)
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        if (!$userLogin || ($userLogin->MAROLE !== 0 && (int)$userLogin->vaiTroId !== 1)) {
            return response()->json(['error' => 'Chỉ Admin mới có quyền thay đổi vai trò (Role)'], 403);
        }

        $tk = \App\Models\TaiKhoan::find($id);
        if (!$tk) return response()->json(['error' => 'Tài khoản không tồn tại'], 404);

        $roleId = $request->input('vaiTroId') ?? $request->input('role_id') ?? $request->input('MAROLE');
        if ($roleId !== null) {
            $tk->update(['vaiTroId' => (int)$roleId]);
        }

        return response()->json([
            'message' => 'Cập nhật vai trò tài khoản thành công',
            'data' => $tk->load('vaitro')
        ]);
    }
    /**
     * Customer ranking logic based on accumulated points.
     */
    public function promoteCustomer($makh)
    {
        $khachHang = \App\Models\KhachHang::find($makh);

        if (!$khachHang) {
            return response()->json(['error' => 'Không tìm thấy khách hàng'], 404);
        }

        $diem = $khachHang->DIEMTICHLUY ?? 0;
        $hang = 'Thường';

        if ($diem >= 10000) {
            $hang = 'Kim Cương';
        } elseif ($diem >= 5000) {
            $hang = 'Vàng';
        } elseif ($diem >= 1000) {
            $hang = 'Bạc';
        }

        return response()->json([
            'MAKH' => $khachHang->MAKH,
            'HOTEN' => $khachHang->HOTEN,
            'DIEMTICHLUY' => $diem,
            'HANG_HIEN_TAI' => $hang,
            'message' => 'Logic thăng hạng hoạt động bình thường'
        ]);
    }

    /**
     * Báo cáo doanh thu (theo ngày hoặc tháng cho Biểu đồ) - Query từ SQL VIEW v_thong_ke_doanh_thu
     */
    public function revenueReport(Request $request)
    {
        $type = $request->query('type', 'month'); // 'day' or 'month'

        $baseQuery = DB::table('v_thong_ke_doanh_thu');

        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        if ($userLogin && $userLogin->vaiTroId == 2) { // MANAGER
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
            $baseQuery->where('cuaHangId', $macuahang);
        }

        $result = [];

        if ($type === 'day') {
            $startDate = now()->subDays(29)->startOfDay();
            $data = (clone $baseQuery)->where('createdAt', '>=', $startDate)
                ->select(DB::raw('DATE(createdAt) as date'), DB::raw('SUM(tongThanhToan) as total'))
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $result[] = [
                    'date' => $date,
                    'total' => isset($data[$date]) ? (float)$data[$date]->total : 0
                ];
            }
        } else {
            $startDate = now()->subMonths(11)->startOfMonth();
            $data = (clone $baseQuery)->where('createdAt', '>=', $startDate)
                ->select(DB::raw('DATE_FORMAT(createdAt, "%Y-%m") as date'), DB::raw('SUM(tongThanhToan) as total'))
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            for ($i = 11; $i >= 0; $i--) {
                $date = now()->subMonths($i)->format('Y-m');
                $result[] = [
                    'date' => $date,
                    'total' => isset($data[$date]) ? (float)$data[$date]->total : 0
                ];
            }
        }

        return response()->json($result);
    }

    public function biDashboard(Request $request)
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahang = null;
        if ($userLogin && $userLogin->vaiTroId == 2) { // MANAGER
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
        }

        $makeQuery = function() use ($macuahang) {
            $q = DB::table('v_thong_ke_doanh_thu');
            if ($macuahang) {
                $q->where('cuaHangId', $macuahang);
            }
            return $q;
        };

        // Summary (Timezone GMT+7)
        $todayStartUtc = now()->setTimezone('Asia/Ho_Chi_Minh')->startOfDay()->setTimezone('UTC');
        $thisMonthStartUtc = now()->setTimezone('Asia/Ho_Chi_Minh')->startOfMonth()->setTimezone('UTC');
        $thisYearStartUtc = now()->setTimezone('Asia/Ho_Chi_Minh')->startOfYear()->setTimezone('UTC');
        $lastMonthStartUtc = now()->setTimezone('Asia/Ho_Chi_Minh')->subMonth()->startOfMonth()->setTimezone('UTC');
        $lastMonthEndUtc = now()->setTimezone('Asia/Ho_Chi_Minh')->subMonth()->endOfMonth()->setTimezone('UTC');

        $revenueToday = (float) $makeQuery()->where('createdAt', '>=', $todayStartUtc)->sum('tongThanhToan');
        $revenueThisMonth = (float) $makeQuery()->where('createdAt', '>=', $thisMonthStartUtc)->sum('tongThanhToan');
        $revenueThisYear = (float) $makeQuery()->where('createdAt', '>=', $thisYearStartUtc)->sum('tongThanhToan');
        
        $revenueLastMonth = (float) $makeQuery()
            ->whereBetween('createdAt', [$lastMonthStartUtc, $lastMonthEndUtc])
            ->sum('tongThanhToan');

        $percentIncreaseMonth = 0;
        if ($revenueLastMonth > 0) {
            $percentIncreaseMonth = (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100;
        } else if ($revenueThisMonth > 0) {
            $percentIncreaseMonth = 100;
        }

        // Order count & revenue breakdown by channel (POS at counter vs Online delivery)
        $channelStats = [
            'pos' => [
                'count' => (int) $makeQuery()->where('loaiDonHang', 1)->count(),
                'revenue' => (float) $makeQuery()->where('loaiDonHang', 1)->sum('tongThanhToan'),
            ],
            'online' => [
                'count' => (int) $makeQuery()->where('loaiDonHang', 0)->count(),
                'revenue' => (float) $makeQuery()->where('loaiDonHang', 0)->sum('tongThanhToan'),
            ]
        ];

        // Revenue by store (For Director / General Manager)
        $revenueByStore = [];
        if (!$macuahang) {
            $revenueByStore = $makeQuery()
                ->join('cuaHang', 'v_thong_ke_doanh_thu.cuaHangId', '=', 'cuaHang.id')
                ->where('v_thong_ke_doanh_thu.createdAt', '>=', $thisMonthStartUtc)
                ->select(DB::raw("COALESCE(cuaHang.tenCuaHang, 'Cửa hàng chính') as TENCUAHANG"), DB::raw('SUM(v_thong_ke_doanh_thu.tongThanhToan) as total'))
                ->groupBy('cuaHang.id', 'cuaHang.tenCuaHang')
                ->get();
        }

        // Top địa bàn giao hàng nhóm theo Tỉnh / Thành Phố
        $topLocationsQuery = DB::table('v_top_dia_ban_giao_hang');
        if ($macuahang) {
            $topLocationsQuery->where('cuaHangId', $macuahang);
        }
        $rawLocations = $topLocationsQuery
            ->select('diaChiGiaoHang', DB::raw('SUM(total_orders) as total_orders'), DB::raw('SUM(total_revenue) as total_revenue'))
            ->groupBy('diaChiGiaoHang')
            ->get();

        $provinceMap = [];
        foreach ($rawLocations as $row) {
            $province = $this->extractProvinceName($row->diaChiGiaoHang);
            if (!isset($provinceMap[$province])) {
                $provinceMap[$province] = [
                    'diaChiGiaoHang' => $province,
                    'total_orders' => 0,
                    'total_revenue' => 0,
                ];
            }
            $provinceMap[$province]['total_orders'] += (int) $row->total_orders;
            $provinceMap[$province]['total_revenue'] += (float) $row->total_revenue;
        }

        $topLocations = collect($provinceMap)
            ->sortByDesc('total_orders')
            ->take(5)
            ->values();

        // Phân bổ doanh thu theo khung giờ (Hôm nay - Giờ Việt Nam GMT+7)
        $revenueByTimeRaw = $makeQuery()
            ->where('createdAt', '>=', $todayStartUtc)
            ->select('createdAt', 'tongThanhToan')
            ->get();

        $revenueByTime = [
            '00:00 - 08:00' => 0,
            '08:00 - 12:00' => 0,
            '12:00 - 16:00' => 0,
            '16:00 - 20:00' => 0,
            '20:00 - 24:00' => 0,
        ];

        foreach ($revenueByTimeRaw as $row) {
            $h = \Carbon\Carbon::parse($row->createdAt, 'UTC')->setTimezone('Asia/Ho_Chi_Minh')->hour;
            $total = (float) $row->tongThanhToan;
            if ($h < 8) $revenueByTime['00:00 - 08:00'] += $total;
            else if ($h < 12) $revenueByTime['08:00 - 12:00'] += $total;
            else if ($h < 16) $revenueByTime['12:00 - 16:00'] += $total;
            else if ($h < 20) $revenueByTime['16:00 - 20:00'] += $total;
            else $revenueByTime['20:00 - 24:00'] += $total;
        }

        $totalOrders = (int) $makeQuery()->count();
        $totalRevenue = (float) $makeQuery()->sum('tongThanhToan');

        return response()->json([
            'tong_doanh_thu' => $revenueThisMonth > 0 ? $revenueThisMonth : $totalRevenue,
            'tong_don_hang' => $totalOrders,
            'doanh_thu_hom_nay' => $revenueToday,
            'summary' => [
                'today' => $revenueToday,
                'this_month' => $revenueThisMonth,
                'this_year' => $revenueThisYear,
                'last_month' => $revenueLastMonth,
                'percent_increase_month' => round($percentIncreaseMonth, 1)
            ],
            'channel_stats' => $channelStats,
            'top_locations' => $topLocations,
            'revenue_by_store' => $revenueByStore,
            'revenue_by_time' => collect($revenueByTime)->map(function ($total, $time) {
                return ['time_range' => $time, 'total' => $total];
            })->values()
        ]);
    }

    /**
     * Helper bóc tách Tỉnh/Thành phố từ địa chỉ giao hàng
     */
    private function extractProvinceName(?string $address): string
    {
        if (!$address) return 'Khác';
        $parts = explode(',', $address);
        $lastPart = trim(end($parts));
        if (!$lastPart) return 'Khác';

        if (preg_match('/(Hồ Chí Minh|HCM|HCMC|TP\.?HCM|TP\.?Hồ Chí Minh)/i', $lastPart)) {
            return 'TP. Hồ Chí Minh';
        }
        if (preg_match('/(Hà Nội|HN|TP\.?Hà Nội)/i', $lastPart)) {
            return 'TP. Hà Nội';
        }

        $province = preg_replace('/^(Tỉnh|Thành phố|TP\.?)\s+/i', '', $lastPart);
        return trim($province) ?: 'Khác';
    }


    /**
     * Top 5 sản phẩm bán chạy nhất (Query từ SQL VIEW v_top_san_pham_ban_chay)
     */
    public function topProducts()
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahang = null;
        if ($userLogin && $userLogin->vaiTroId == 2) { // MANAGER
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
        }

        $query = DB::table('v_top_san_pham_ban_chay');
        if ($macuahang) {
            $query->where('cuaHangId', $macuahang);
        }

        $data = $query->select('TENSP', DB::raw('SUM(total_sold) as total_sold'))
            ->groupBy('sanPhamId', 'TENSP')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    public function getChamCong(Request $request)
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('chamcong') && !\Illuminate\Support\Facades\Schema::hasTable('cham_cong')) {
                return response()->json([]);
            }
            $thang = $request->query('thang', now()->month);
            $nam = $request->query('nam', now()->year);

            $query = \App\Models\ChamCong::whereMonth('NGAYCHAMCONG', $thang)
                ->whereYear('NGAYCHAMCONG', $nam);

            $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
            if ($request->query('my_only') || ($userLogin && !in_array($userLogin->MAROLE, [0, 1]) && !in_array($userLogin->vaiTroId, [1, 2, 8]))) {
                $query->where('USERNAME', $userLogin->username ?? $userLogin->USERNAME);
            } elseif ($request->has('username')) {
                $query->where('USERNAME', $request->query('username'));
            }

            return response()->json($query->orderBy('NGAYCHAMCONG', 'desc')->get());
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    /**
     * Cập nhật / Thêm mới chấm công
     */
    public function chamCong(Request $request)
    {
        $request->validate([
            'USERNAME' => 'required|string',
            'NGAYCHAMCONG' => 'required|date',
            'TRANGTHAI' => 'required|boolean'
        ]);

        $targetUsername = $request->USERNAME;
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();

        if ($userLogin) {
            $isSelf = (
                strcasecmp($targetUsername, $userLogin->username ?? '') === 0 ||
                strcasecmp($targetUsername, $userLogin->USERNAME ?? '') === 0
            );

            // Bất kỳ nhân sự nào cũng có quyền tự chấm công cho chính bản thân mình
            if (!$isSelf) {
                $targetTaiKhoan = \App\Models\TaiKhoan::with('nhanvien')
                    ->where('username', $targetUsername)
                    ->orWhere('USERNAME', $targetUsername)
                    ->first();

                if (!$targetTaiKhoan) {
                    return response()->json(['error' => 'Không tìm thấy nhân viên'], 404);
                }

                if ($userLogin->MAROLE == 0 || $userLogin->vaiTroId == 1 || $userLogin->vaiTroId == 8) {
                    $isBranchManager = ($targetTaiKhoan->MAROLE == 1 || $targetTaiKhoan->vaiTroId == 2);
                    $isCentralStaff = ($targetTaiKhoan->MAROLE == 2 && $targetTaiKhoan->nhanvien && $targetTaiKhoan->nhanvien->MACUAHANG == 1);

                    if (!$isBranchManager && !$isCentralStaff) {
                        return response()->json(['error' => 'Quản lý tổng chỉ chấm công cho Quản lý chi nhánh và Nhân viên trung tâm. Hãy để Quản lý chi nhánh tự chấm công cho nhân viên của họ.'], 403);
                    }
                } elseif ($userLogin->MAROLE == 1 || $userLogin->vaiTroId == 2) {
                    $nhanVienLogin = \App\Models\NhanVien::findByUsername($userLogin->username);
                    $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;

                    $isOwnStaff = ($targetTaiKhoan->nhanvien && $targetTaiKhoan->nhanvien->MACUAHANG == $macuahang);

                    if (!$isOwnStaff) {
                        return response()->json(['error' => 'Bạn chỉ có quyền chấm công cho bản thân hoặc nhân viên thuộc chi nhánh của mình'], 403);
                    }
                } else {
                    return response()->json(['error' => 'Bạn chỉ có quyền chấm công cho chính bản thân mình.'], 403);
                }
            }
        }

        $chamCong = \App\Models\ChamCong::updateOrCreate(
            ['USERNAME' => $targetUsername, 'NGAYCHAMCONG' => $request->NGAYCHAMCONG],
            ['TRANGTHAI' => $request->TRANGTHAI]
        );

        return response()->json(['message' => 'Chấm công thành công', 'data' => $chamCong]);
    }
    /**
     * ─────────────────────────────────────────────────────────────────────
     * THỐNG KÊ LỢI NHUẬN THỰC TẾ
     * Lợi nhuận = Doanh thu bán - Chi phí nhập hàng (theo lô)
     * ─────────────────────────────────────────────────────────────────────
     */
    public function thongKeLoiNhuan(Request $request)
    {
        try {
            $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $macuahang = null;
            if ($userLogin && $userLogin->vaiTroId == 2) {
                $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
                $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
            }
            if (!$macuahang && $request->filled('macuahang')) {
                $macuahang = (int) $request->macuahang;
            }

            $thang = (int) $request->input('thang', now()->month);
            $nam   = (int) $request->input('nam', now()->year);

            // ── Doanh thu: tổng tiền hóa đơn bán đã hoàn thành / đã thanh toán ─────────────
            $doanhThuQuery = DB::table('donHang')
                ->where(function($q) {
                    $q->where('trangThaiThanhToan', 1)->orWhereIn('trangThaiDonHang', [4, 5]);
                })
                ->whereMonth('createdAt', $thang)
                ->whereYear('createdAt', $nam);

            if ($macuahang) {
                $doanhThuQuery->where('cuaHangId', $macuahang);
            }

            $doanhThu = (float) $doanhThuQuery->sum('tongThanhToan');

            // ── Chi phí nhập: tổng (SL bán × đơn giá bán) theo tháng ───
            $chiPhiQuery = DB::table('chiTietDonHang')
                ->join('donHang', 'chiTietDonHang.donHangId', '=', 'donHang.id')
                ->leftJoin('loHang', 'chiTietDonHang.sanPhamId', '=', 'loHang.sanPhamId')
                ->where(function($q) {
                    $q->where('donHang.trangThaiThanhToan', 1)->orWhereIn('donHang.trangThaiDonHang', [4, 5]);
                })
                ->whereMonth('donHang.createdAt', $thang)
                ->whereYear('donHang.createdAt', $nam);

            if ($macuahang) {
                $chiPhiQuery->where('donHang.cuaHangId', $macuahang);
            }

            $chiPhi = (float) $chiPhiQuery->sum(DB::raw('chiTietDonHang.soLuong * COALESCE(loHang.giaNhap, 0)'));

            // ── Chi phí hủy hàng (hết hạn, hỏng) trong tháng ──────────────
            $chiPhiHuy = 0;
            $bienBanTable = \Illuminate\Support\Facades\Schema::hasTable('bienBanHuy') ? 'bienBanHuy' : (\Illuminate\Support\Facades\Schema::hasTable('bienbanhuy') ? 'bienbanhuy' : null);
            if ($bienBanTable) {
                $chiTietTable = \Illuminate\Support\Facades\Schema::hasTable('chiTietBienBanHuy') ? 'chiTietBienBanHuy' : 'chitietbienbanhuy';
                $loHangTable = \Illuminate\Support\Facades\Schema::hasTable('loHang') ? 'loHang' : 'lohang';
                $chiPhiHuyQuery = DB::table($bienBanTable)
                    ->leftJoin($chiTietTable, "{$bienBanTable}.id", '=', "{$chiTietTable}.bienBanHuyId")
                    ->leftJoin($loHangTable, "{$chiTietTable}.loHangId", '=', "{$loHangTable}.id")
                    ->whereMonth("{$bienBanTable}.createdAt", $thang)
                    ->whereYear("{$bienBanTable}.createdAt", $nam);

                if ($macuahang) {
                    $chiPhiHuyQuery->where("{$bienBanTable}.cuaHangId", $macuahang);
                }

                $chiPhiHuy = (float) $chiPhiHuyQuery->sum(DB::raw("COALESCE({$chiTietTable}.soLuongHuy, 0) * COALESCE({$loHangTable}.giaNhap, 0)"));
            }

            // ── Tổng chi phí = nhập hàng + hủy ─────────────────────────────
            $tongChiPhi = $chiPhi + $chiPhiHuy;
            $loiNhuan   = $doanhThu - $tongChiPhi;
            $biLoi      = $doanhThu > 0 ? round(($loiNhuan / $doanhThu) * 100, 2) : 0;

            // ── Lợi nhuận theo từng sản phẩm ───────────────────────────────
            $loiNhuanTheoSP = DB::table('chiTietDonHang')
                ->join('donHang', 'chiTietDonHang.donHangId', '=', 'donHang.id')
                ->join('sanPham', 'chiTietDonHang.sanPhamId', '=', 'sanPham.id')
                ->leftJoin('loHang', 'chiTietDonHang.sanPhamId', '=', 'loHang.sanPhamId')
                ->where(function($q) {
                    $q->where('donHang.trangThaiThanhToan', 1)->orWhereIn('donHang.trangThaiDonHang', [4, 5]);
                })
                ->whereMonth('donHang.createdAt', $thang)
                ->whereYear('donHang.createdAt', $nam)
                ->when($macuahang, fn($q) => $q->where('donHang.cuaHangId', $macuahang))
                ->selectRaw("
                    sanPham.id as MASP,
                    sanPham.tenSanPham as TENSP,
                    SUM(chiTietDonHang.soLuong) as tong_ban,
                    SUM(chiTietDonHang.thanhTien) as doanh_thu,
                    SUM(chiTietDonHang.soLuong * COALESCE(loHang.giaNhap, 0)) as chi_phi,
                    SUM(chiTietDonHang.thanhTien) - SUM(chiTietDonHang.soLuong * COALESCE(loHang.giaNhap, 0)) as loi_nhuan
                ")
                ->groupBy('sanPham.id', 'sanPham.tenSanPham')
                ->orderByDesc('loi_nhuan')
                ->limit(10)
                ->get();

            // ── Lợi nhuận theo ngày trong tháng ────────────────────────────
            $loiNhuanTheoNgay = DB::table('chiTietDonHang')
                ->join('donHang', 'chiTietDonHang.donHangId', '=', 'donHang.id')
                ->leftJoin('loHang', 'chiTietDonHang.sanPhamId', '=', 'loHang.sanPhamId')
                ->where(function($q) {
                    $q->where('donHang.trangThaiThanhToan', 1)->orWhereIn('donHang.trangThaiDonHang', [4, 5]);
                })
                ->whereMonth('donHang.createdAt', $thang)
                ->whereYear('donHang.createdAt', $nam)
                ->when($macuahang, fn($q) => $q->where('donHang.cuaHangId', $macuahang))
                ->selectRaw("
                    DATE(donHang.createdAt) as ngay,
                    SUM(chiTietDonHang.thanhTien) as doanh_thu,
                    SUM(chiTietDonHang.soLuong * COALESCE(loHang.giaNhap, 0)) as chi_phi,
                    SUM(chiTietDonHang.thanhTien) - SUM(chiTietDonHang.soLuong * COALESCE(loHang.giaNhap, 0)) as loi_nhuan
                ")
                ->groupBy('ngay')
                ->orderBy('ngay')
                ->get()
                ->map(fn($row) => [
                    'ngay'       => $row->ngay,
                    'doanh_thu'  => (float) $row->doanh_thu,
                    'chi_phi'    => (float) $row->chi_phi,
                    'loi_nhuan'  => round((float) $row->doanh_thu - (float) $row->chi_phi, 2),
                ]);

            return response()->json([
                'status'         => true,
                'data'           => [
                    'thang'          => $thang,
                    'nam'            => $nam,
                    'doanh_thu'      => round($doanhThu, 2),
                    'chi_phi_nhap'   => round($chiPhi, 2),
                    'chi_phi_huy'    => round($chiPhiHuy, 2),
                    'tong_chi_phi'   => round($tongChiPhi, 2),
                    'loi_nhuan'      => round($loiNhuan, 2),
                    'bien_loi_nhuan' => $biLoi,
                    'theo_san_pham'  => $loiNhuanTheoSP,
                    'theo_ngay'      => $loiNhuanTheoNgay,
                ]
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi thongKeLoiNhuan: ' . $e->getMessage());
            return response()->json([
                'status' => true,
                'data' => [
                    'thang' => (int) $request->input('thang', now()->month),
                    'nam' => (int) $request->input('nam', now()->year),
                    'doanh_thu' => 0,
                    'chi_phi_nhap' => 0,
                    'chi_phi_huy' => 0,
                    'tong_chi_phi' => 0,
                    'loi_nhuan' => 0,
                    'bien_loi_nhuan' => 0,
                    'theo_san_pham' => [],
                    'theo_ngay' => []
                ]
            ]);
        }
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * THỐNG KÊ NHẬP HÀNG: Chi phí & Tần suất nhập theo tháng
     * ─────────────────────────────────────────────────────────────────────
     */
    public function thongKeNhapHang(Request $request)
    {
        try {
            $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $macuahang = null;
            if ($userLogin && $userLogin->vaiTroId == 2) {
                $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
                $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
            }

            $thang = (int) $request->input('thang', now()->month);
            $nam   = (int) $request->input('nam', now()->year);

            $loHangTable = \Illuminate\Support\Facades\Schema::hasTable('loHang') ? 'loHang' : 'lohang';
            $sanPhamTable = \Illuminate\Support\Facades\Schema::hasTable('sanPham') ? 'sanPham' : 'sanpham';
            $hoaDonNhapTable = \Illuminate\Support\Facades\Schema::hasTable('hoaDonNhap') ? 'hoaDonNhap' : 'hoadonnhap';
            $nhaCungCapTable = \Illuminate\Support\Facades\Schema::hasTable('nhaCungCap') ? 'nhaCungCap' : 'nhacungcap';

            // Tổng nhập theo phiếu trong tháng
            $nhapTheoPhieu = DB::table($loHangTable)
                ->join($sanPhamTable, "{$loHangTable}.sanPhamId", '=', "{$sanPhamTable}.id")
                ->leftJoin($hoaDonNhapTable, "{$loHangTable}.cuaHangId", '=', "{$hoaDonNhapTable}.cuaHangId")
                ->whereMonth("{$loHangTable}.createdAt", $thang)
                ->whereYear("{$loHangTable}.createdAt", $nam)
                ->when($macuahang, fn($q) => $q->where("{$loHangTable}.cuaHangId", $macuahang))
                ->selectRaw("
                    DATE({$loHangTable}.createdAt) as ngay,
                    COUNT(DISTINCT {$loHangTable}.id) as so_phieu,
                    SUM({$loHangTable}.soLuongNhap) as tong_sl_nhap,
                    SUM({$loHangTable}.soLuongNhap * {$loHangTable}.giaNhap) as tong_chi_phi
                ")
                ->groupBy('ngay')
                ->orderBy('ngay')
                ->get();

            // Top SP nhập nhiều nhất
            $topSPNhap = DB::table($loHangTable)
                ->join($sanPhamTable, "{$loHangTable}.sanPhamId", '=', "{$sanPhamTable}.id")
                ->whereMonth("{$loHangTable}.createdAt", $thang)
                ->whereYear("{$loHangTable}.createdAt", $nam)
                ->when($macuahang, fn($q) => $q->where("{$loHangTable}.cuaHangId", $macuahang))
                ->selectRaw("{$sanPhamTable}.id as MASP, {$sanPhamTable}.tenSanPham as TENSP,
                             SUM({$loHangTable}.soLuongNhap) as tong_nhap,
                             SUM({$loHangTable}.soLuongNhap * {$loHangTable}.giaNhap) as tong_chi_phi,
                             COUNT(*) as so_lo")
                ->groupBy("{$sanPhamTable}.id", "{$sanPhamTable}.tenSanPham")
                ->orderByDesc('tong_nhap')
                ->limit(10)
                ->get();

            // Nhập theo NCC trong tháng
            $nhapTheoNCC = DB::table($loHangTable)
                ->leftJoin($nhaCungCapTable, "{$loHangTable}.nhaCungCapId", '=', "{$nhaCungCapTable}.id")
                ->whereMonth("{$loHangTable}.createdAt", $thang)
                ->whereYear("{$loHangTable}.createdAt", $nam)
                ->when($macuahang, fn($q) => $q->where("{$loHangTable}.cuaHangId", $macuahang))
                ->selectRaw("
                    COALESCE({$nhaCungCapTable}.tenNcc, 'Không xác định') as ten_ncc,
                    {$loHangTable}.nhaCungCapId as MANCC,
                    SUM({$loHangTable}.soLuongNhap * {$loHangTable}.giaNhap) as tong_tien,
                    SUM({$loHangTable}.soLuongNhap) as tong_sl,
                    COUNT(DISTINCT {$loHangTable}.id) as so_phieu
                ")
                ->groupBy("{$loHangTable}.nhaCungCapId", "{$nhaCungCapTable}.tenNcc")
                ->orderByDesc('tong_tien')
                ->get();

            return response()->json([
                'thang'          => $thang,
                'nam'            => $nam,
                'theo_ngay'      => $nhapTheoPhieu,
                'top_sp_nhap'    => $topSPNhap,
                'theo_ncc'       => $nhapTheoNCC,
                'tong_chi_phi'   => round($nhapTheoPhieu->sum('tong_chi_phi'), 2),
                'tong_phieu'     => $nhapTheoPhieu->sum('so_phieu'),
                'tong_sl_nhap'   => $nhapTheoPhieu->sum('tong_sl_nhap'),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi thongKeNhapHang: ' . $e->getMessage());
            return response()->json([
                'thang' => (int) $request->input('thang', now()->month),
                'nam' => (int) $request->input('nam', now()->year),
                'theo_ngay' => [],
                'top_sp_nhap' => [],
                'theo_ncc' => [],
                'tong_chi_phi' => 0,
                'tong_phieu' => 0,
                'tong_sl_nhap' => 0
            ]);
        }
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * PHÂN TÍCH HAO HỤT: % lô bị hủy vs đã bán — đánh giá quản lý tồn kho
     * ─────────────────────────────────────────────────────────────────────
     */
    public function thongKeHaoHut(Request $request)
    {
        try {
            $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $macuahang = null;
            if ($userLogin && $userLogin->vaiTroId == 2) {
                $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
                $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
            }

            $thang = (int) $request->input('thang', now()->month);
            $nam   = (int) $request->input('nam', now()->year);

            $loHangTable = \Illuminate\Support\Facades\Schema::hasTable('loHang') ? 'loHang' : 'lohang';
            $sanPhamTable = \Illuminate\Support\Facades\Schema::hasTable('sanPham') ? 'sanPham' : 'sanpham';

            $query = DB::table($loHangTable)
                ->join($sanPhamTable, "{$loHangTable}.sanPhamId", '=', "{$sanPhamTable}.id")
                ->whereMonth("{$loHangTable}.createdAt", $thang)
                ->whereYear("{$loHangTable}.createdAt", $nam)
                ->when($macuahang, fn($q) => $q->where("{$loHangTable}.cuaHangId", $macuahang));

            // Tổng quan
            $tongKet = (clone $query)
                ->selectRaw("
                    SUM({$loHangTable}.soLuongNhap) as tong_nhap,
                    SUM({$loHangTable}.soLuongCon) as tong_con,
                    COUNT(*) as so_lo,
                    SUM(CASE WHEN {$loHangTable}.trangThai = 2 THEN 1 ELSE 0 END) as so_lo_het_han,
                    SUM(CASE WHEN {$loHangTable}.trangThai = 1 THEN 1 ELSE 0 END) as so_lo_ban_het,
                    SUM(CASE WHEN {$loHangTable}.trangThai = 3 THEN 1 ELSE 0 END) as so_lo_huy,
                    SUM(CASE WHEN {$loHangTable}.trangThai = 3 THEN {$loHangTable}.soLuongCon * {$loHangTable}.giaNhap ELSE 0 END) as gia_tri_huy
                ")
                ->first();

            // Hao hụt theo sản phẩm
            $haoHutTheoSP = (clone $query)
                ->selectRaw("
                    {$sanPhamTable}.id as MASP, {$sanPhamTable}.tenSanPham as TENSP,
                    SUM({$loHangTable}.soLuongNhap) as tong_nhap,
                    SUM({$loHangTable}.soLuongCon) as tong_con,
                    SUM(CASE WHEN {$loHangTable}.trangThai = 3 THEN {$loHangTable}.soLuongCon ELSE 0 END) as tong_huy,
                    SUM(CASE WHEN {$loHangTable}.trangThai = 3 THEN {$loHangTable}.soLuongCon * {$loHangTable}.giaNhap ELSE 0 END) as gia_tri_huy,
                    ROUND(SUM(CASE WHEN {$loHangTable}.trangThai = 3 THEN {$loHangTable}.soLuongCon ELSE 0 END) / NULLIF(SUM({$loHangTable}.soLuongNhap), 0) * 100, 2) as ty_le_huy
                ")
                ->groupBy("{$sanPhamTable}.id", "{$sanPhamTable}.tenSanPham")
                ->orderByDesc('ty_le_huy')
                ->get();

            $tongNhap = ($tongKet->tong_nhap ?? 0) ?: 1;
            $tongHuy = (int) ($tongKet->so_lo_huy ?? 0);
            $tongCon = (int) ($tongKet->tong_con ?? 0);

            return response()->json([
                'status'           => true,
                'data'             => [
                    'thang'            => $thang,
                    'nam'              => $nam,
                    'tong_nhap'        => (int) ($tongKet->tong_nhap ?? 0),
                    'tong_ban'         => (int) (($tongKet->tong_nhap ?? 0) - $tongCon),
                    'tong_huy'         => $tongHuy,
                    'tong_con'         => $tongCon,
                    'ty_le_ban'        => round((($tongKet->tong_nhap ?? 0) - $tongCon) / $tongNhap * 100, 2),
                    'ty_le_huy'        => round($tongHuy / $tongNhap * 100, 2),
                    'gia_tri_huy'      => round($tongKet->gia_tri_huy ?? 0, 2),
                    'so_lo'            => (int) ($tongKet->so_lo ?? 0),
                    'so_lo_het_han'    => (int) ($tongKet->so_lo_het_han ?? 0),
                    'so_lo_ban_het'    => (int) ($tongKet->so_lo_ban_het ?? 0),
                    'so_lo_bi_huy'     => (int) ($tongKet->so_lo_huy ?? 0),
                    'hao_hut_theo_sp'  => $haoHutTheoSP,
                ]
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Lỗi thongKeHaoHut: ' . $e->getMessage());
            return response()->json([
                'status' => true,
                'data' => [
                    'thang' => (int) $request->input('thang', now()->month),
                    'nam' => (int) $request->input('nam', now()->year),
                    'tong_nhap' => 0,
                    'tong_ban' => 0,
                    'tong_huy' => 0,
                    'tong_con' => 0,
                    'ty_le_ban' => 0,
                    'ty_le_huy' => 0,
                    'gia_tri_huy' => 0,
                    'so_lo' => 0,
                    'so_lo_het_han' => 0,
                    'so_lo_ban_het' => 0,
                    'so_lo_bi_huy' => 0,
                    'hao_hut_theo_sp' => []
                ]
            ]);
        }
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * CẢNH BÁO HSD: Tổng hợp lô sắp/đã hết hạn cho dashboard admin
     * ─────────────────────────────────────────────────────────────────────
     */
    public function canhBaoHSD(Request $request)
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahang = null;
        if ($userLogin && ((int)$userLogin->MAROLE === 2 || (int)$userLogin->vaiTroId === 2)) {
            $nhanVienLogin = \App\Models\NhanVien::findByUsername($userLogin->username);
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
        }

        $loHangService = app(\App\Services\LoHangService::class);
        return response()->json($loHangService->getExpiryAlerts($macuahang));
    }

    public function thongKeDonHuy(Request $request)
    {
        $tuNgay = $request->input('tuNgay', now()->subDays(30)->format('Y-m-d'));
        $denNgay = $request->input('denNgay', now()->format('Y-m-d'));
        $cuaHangId = $request->input('cuaHangId');
        $lyDoFilter = $request->input('lyDoHuy');

        $startDate = $tuNgay . ' 00:00:00';
        $endDate = $denNgay . ' 23:59:59';

        $baseOnline = \App\Models\DonHang::where('loaiDonHang', 0)
            ->whereBetween('createdAt', [$startDate, $endDate]);
        if ($cuaHangId) $baseOnline->where('cuaHangId', $cuaHangId);

        $baseHuy = \App\Models\DonHang::with(['chitiet.sanpham', 'nguoidung', 'cuahang'])
            ->where('trangThaiDonHang', 0)
            ->whereBetween('createdAt', [$startDate, $endDate]);
        if ($cuaHangId) $baseHuy->where('cuaHangId', $cuaHangId);
        if ($lyDoFilter && $lyDoFilter !== 'all') $baseHuy->where('lyDoHuy', $lyDoFilter);

        $tongDonOnline = (clone $baseOnline)->count();
        $donHuyAll = (clone $baseHuy)->get();
        $tongDonHuy = $donHuyAll->count();
        $tyLeHuy = $tongDonOnline > 0 ? round($tongDonHuy / $tongDonOnline * 100, 1) : 0;
        $tongGiaTriHuy = $donHuyAll->sum('tongThanhToan');

        // Hủy theo ngày
        $huyTheoNgay = (clone $baseHuy)->selectRaw("DATE(createdAt) as ngay, COUNT(*) as soDon")
            ->groupBy('ngay')->orderBy('ngay')->get();

        // Lý do hủy
        $lyDoHuy = (clone $baseHuy)->selectRaw("COALESCE(NULLIF(lyDoHuy,''), 'Không xác định') as lyDo, COUNT(*) as soDon")
            ->groupByRaw("COALESCE(NULLIF(lyDoHuy,''), 'Không xác định')")
            ->orderByDesc('soDon')->limit(10)->get();

        // Sản phẩm trong đơn hủy
        $donHuyIds = $donHuyAll->pluck('id');
        $sanPhamHuy = \App\Models\ChiTietDonHang::with('sanpham')
            ->whereIn('donHangId', $donHuyIds)
            ->get()
            ->groupBy('sanPhamId')
            ->map(function($items) {
                $sp = $items->first()->sanpham;
                return [
                    'tenSP' => $sp ? ($sp->tenSanPham ?? 'Sản phẩm') : 'Sản phẩm',
                    'soDon' => $items->pluck('donHangId')->unique()->count(),
                    'soLuong' => $items->sum('soLuong'),
                ];
            })->sortByDesc('soDon')->take(10)->values();

        // Nguồn yêu cầu hủy
        $nguonYeuCauHuy = $donHuyAll->groupBy(function($d) {
            return ($d->lyDoHuy && trim($d->lyDoHuy)) ? 'Khách hàng' : 'Không xác định';
        })->map(function($items, $nguon) {
            return ['nguon' => $nguon, 'soDon' => $items->count()];
        })->values();

        // Chi tiết đơn hủy
        $chiTiet = $donHuyAll->map(function($d) {
            return [
                'maDonHang' => $d->maDonHang,
                'tenKH' => $d->nguoidung ? $d->nguoidung->hoTen : 'Khách hàng',
                'taiKhoanKH' => $d->nguoidung && $d->nguoidung->taikhoan ? $d->nguoidung->taikhoan->username : '',
                'cuaHang' => $d->cuahang ? ($d->cuahang->tenCuaHang ?? $d->cuahang->ten_cuahang ?? 'Dola Bakery') : 'Dola Bakery',
                'tongTien' => (float)$d->tongThanhToan,
                'lyDoHuy' => $d->lyDoHuy ?? '',
                'ghiChuHuy' => $d->ghiChuHuy ?? '',
                'nguon' => ($d->lyDoHuy && trim($d->lyDoHuy)) ? 'Khách hàng' : 'Không xác định',
                'ngayDat' => $d->createdAt ? $d->createdAt->timezone('Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y') : '',
                'ngayHuy' => $d->updatedAt ? $d->updatedAt->timezone('Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y') : '',
            ];
        });

        return response()->json([
            'tongDonOnline' => $tongDonOnline,
            'tongDonHuy' => $tongDonHuy,
            'tyLeHuy' => $tyLeHuy,
            'tongGiaTriHuy' => $tongGiaTriHuy,
            'huyTheoNgay' => $huyTheoNgay,
            'lyDoHuy' => $lyDoHuy,
            'sanPhamTrongDonHuy' => $sanPhamHuy,
            'nguonYeuCauHuy' => $nguonYeuCauHuy,
            'chiTietDonHuy' => $chiTiet,
            'capNhat' => now()->timezone('Asia/Ho_Chi_Minh')->format('H:i:s d/m/Y'),
        ]);
    }
}
