<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\TaiKhoan;
use App\Models\NhanVien;

class AdminController extends Controller
{
    /**
     * View a list of all accounts
     */
    public function listUsers()
    {
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $query = TaiKhoan::with(['nguoidung.cuahang', 'vaitro']);

        if ($user && $user->vaiTroId == 2) {
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
            $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;

            $query->where(function ($q) use ($macuahang, $user) {
                $q->where('vaiTroId', 6)
                  ->orWhere(function ($q2) use ($macuahang) {
                      $q2->whereIn('vaiTroId', [3, 4])
                         ->whereHas('nguoidung', function ($q3) use ($macuahang) {
                             $q3->where('cuaHangId', $macuahang);
                         });
                  })
                  ->orWhere('id', $user->id);
            });
        }

        $users = $query->get()->map(function ($u) {
            $nd = $u->nguoidung;
            return [
                'id' => $u->id,
                'username' => $u->username,
                'USERNAME' => $u->username,
                'email' => $u->email,
                'EMAIL' => $u->email,
                'vaiTroId' => $u->vaiTroId,
                'MAROLE' => $u->MAROLE,
                'vaitro' => $u->vaitro,
                'nguoidung' => $nd,
                'khachhang' => $nd,
                'nhanvien' => $nd,
                'createdAt' => $u->createdAt,
            ];
        });

        return response()->json($users);
    }

    public function createStaff(Request $request)
    {
        $request->validate([
            'USERNAME' => 'required|string|unique:taikhoan,USERNAME',
            'PASSWORD' => 'required|string|min:6',
            'EMAIL'    => 'nullable|email|unique:taikhoan,EMAIL',
            'HOTEN'    => 'required|string',
            'CHUCVU'   => 'required|string',
            'LUONG'    => 'nullable|numeric',
            'CCCD_TRUOC' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'CCCD_SAU' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $marole = $request->MAROLE ?? 2;

            // Quản lý chi nhánh chỉ được tạo Nhân viên (Role 2)
            if ($userLogin && $userLogin->MAROLE == 1) {
                $marole = 2;
            }

            if ($marole == 2) {
                \App\Models\VaiTro::firstOrCreate(['MAROLE' => 2], ['MOTA' => 'Nhân viên (Bán hàng, Khách hàng)']);
            }
            if ($marole == 1) {
                \App\Models\VaiTro::firstOrCreate(['MAROLE' => 1], ['MOTA' => 'Quản lý chi nhánh']);
            }

            TaiKhoan::create([
                'USERNAME' => $request->USERNAME,
                'PASSWORD' => $request->PASSWORD,
                'MAROLE'   => $marole,
                'EMAIL'    => $request->EMAIL ?: $request->USERNAME . '@dolabakery.com',
            ]);

            // Xử lý upload ảnh CCCD lên MinIO
            $cccdTruocPath = null;
            $cccdSauPath = null;

            if ($request->hasFile('CCCD_TRUOC')) {
                $file = $request->file('CCCD_TRUOC');
                $filename = 'cccd_truoc_' . $request->USERNAME . '_' . time() . '.' . $file->getClientOriginalExtension();
                // Lưu lên s3 (MinIO) ở thư mục cccd/
                \Illuminate\Support\Facades\Storage::disk('s3')->putFileAs('cccd', $file, $filename);
                // Trả về URL để FontEnd hiển thị
                $cccdTruocPath = \Illuminate\Support\Facades\Storage::disk('s3')->url('cccd/' . $filename);
            }

            if ($request->hasFile('CCCD_SAU')) {
                $file = $request->file('CCCD_SAU');
                $filename = 'cccd_sau_' . $request->USERNAME . '_' . time() . '.' . $file->getClientOriginalExtension();
                \Illuminate\Support\Facades\Storage::disk('s3')->putFileAs('cccd', $file, $filename);
                $cccdSauPath = \Illuminate\Support\Facades\Storage::disk('s3')->url('cccd/' . $filename);
            }

            $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $macuahang = $request->MACUAHANG ?? 1;
            if ($userLogin && $userLogin->MAROLE == 1) {
                $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
                if ($nhanVienLogin) {
                    $macuahang = $nhanVienLogin->MACUAHANG;
                }
            }

            NhanVien::create([
                'USERNAME' => $request->USERNAME,
                'HOTEN'    => $request->HOTEN,
                'CHUCVU'   => $request->CHUCVU,
                'LUONG'    => $request->LUONG ?: 0,
                'SDT'      => $request->SDT ?? null,
                'DIACHI'   => $request->DIACHI ?? null,
                'GioiTinh' => $request->GioiTinh ?? null,
                'NGAYSINH' => $request->NGAYSINH ?? null,
                'CCCD'     => $request->CCCD ?? null,
                'CCCD_TRUOC' => $cccdTruocPath,
                'CCCD_SAU' => $cccdSauPath,
                'TRANGTHAI' => $request->TRANGTHAI ?? 'Đang làm việc',
                'CALAMVIEC' => $request->CALAMVIEC ?? 'Ca Sáng',
                'MACUAHANG' => $macuahang,
            ]);

            DB::commit();
            return response()->json(['message' => 'Tạo tài khoản nhân viên thành công'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Tạo nhân viên thất bại: ' . $e->getMessage()], 500);
        }
    }



    public function importStaff(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahangDefault = 1;
        if ($userLogin && $userLogin->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            if ($nhanVienLogin) {
                $macuahangDefault = $nhanVienLogin->MACUAHANG;
            }
        }

        $file = $request->file('file');
        $csvData = file_get_contents($file);
        $rows = array_map("str_getcsv", explode("\n", $csvData));
        $header = array_shift($rows);

        // Loại bỏ ký tự BOM nếu có
        $header[0] = trim($header[0], "\xEF\xBB\xBF");

        $successCount = 0;
        $errorCount = 0;

        foreach ($rows as $row) {
            if (empty(array_filter($row))) {
                continue;
            } // Bỏ qua dòng trống

            // Dữ liệu CSV mong đợi: Username, HoTen, ChucVu, Luong, SDT
            $username = $row[0] ?? null;
            $hoTen = $row[1] ?? null;
            $chucVu = $row[2] ?? 'Nhân viên';
            $luong = $row[3] ?? 0;
            $sdt = $row[4] ?? null;

            if (!$username || !$hoTen) {
                $errorCount++;
                continue;
            }

            // Kiểm tra username trùng
            if (TaiKhoan::where('USERNAME', $username)->exists()) {
                $errorCount++;
                continue;
            }

            DB::beginTransaction();
            try {
                TaiKhoan::create([
                    'USERNAME' => $username,
                    'PASSWORD' => $username, // Mặc định pass = username
                    'MAROLE'   => 1,
                    'EMAIL'    => $username . '@dolabakery.com',
                ]);

                NhanVien::create([
                    'USERNAME' => $username,
                    'HOTEN'    => $hoTen,
                    'CHUCVU'   => $chucVu,
                    'LUONG'    => $luong,
                    'SDT'      => $sdt,
                    'MACUAHANG' => $macuahangDefault,
                ]);

                DB::commit();
                $successCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                $errorCount++;
            }
        }

        return response()->json([
            'message' => "Import hoàn tất. Thành công: $successCount, Lỗi/Trùng: $errorCount"
        ]);
    }

    public function updateStaff(Request $request, $username)
    {
        $nhanVien = \App\Models\NhanVien::where('USERNAME', $username)->first();
        if (!$nhanVien) {
            return response()->json(['error' => 'Không tìm thấy nhân viên'], 404);
        }

        $oldTrangThai = $nhanVien->TRANGTHAI;

        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahangToUpdate = $request->MACUAHANG ?? $nhanVien->MACUAHANG;

        // Nếu là Quản lý (Role 1), không cho phép đổi chi nhánh của nhân viên
        if ($userLogin && $userLogin->MAROLE == 1) {
            $macuahangToUpdate = $nhanVien->MACUAHANG;

            // Đồng thời chặn không cho Quản lý sửa nhân viên của chi nhánh khác
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            if ($nhanVienLogin && $nhanVien->MACUAHANG != $nhanVienLogin->MACUAHANG) {
                return response()->json(['message' => 'Bạn không có quyền sửa nhân viên của chi nhánh khác'], 403);
            }
        }

        $nhanVien->update([
            'HOTEN'    => $request->HOTEN ?? $nhanVien->HOTEN,
            'CHUCVU'   => $request->CHUCVU ?? $nhanVien->CHUCVU,
            'LUONG'    => $request->LUONG ?? $nhanVien->LUONG,
            'SDT'      => $request->SDT ?? $nhanVien->SDT,
            'DIACHI'   => $request->DIACHI ?? $nhanVien->DIACHI,
            'GioiTinh' => $request->GioiTinh ?? $nhanVien->GioiTinh,
            'NGAYSINH' => $request->NGAYSINH ?? $nhanVien->NGAYSINH,
            'CCCD'     => $request->CCCD ?? $nhanVien->CCCD,
            'TRANGTHAI' => $request->TRANGTHAI ?? $nhanVien->TRANGTHAI,
            'CALAMVIEC' => $request->CALAMVIEC ?? $nhanVien->CALAMVIEC,
            'MACUAHANG' => $macuahangToUpdate,
        ]);

        $maroleToUpdate = $request->MAROLE;
        if ($userLogin && $userLogin->MAROLE == 1) {
            $maroleToUpdate = null; // Quản lý chi nhánh không được phép đổi chức vụ hệ thống (Role)
        }

        if ($maroleToUpdate !== null) {
            if ($maroleToUpdate == 2) {
                \App\Models\VaiTro::firstOrCreate(['MAROLE' => 2], ['MOTA' => 'Nhân viên (Bán hàng, Khách hàng)']);
            }
            if ($maroleToUpdate == 1) {
                \App\Models\VaiTro::firstOrCreate(['MAROLE' => 1], ['MOTA' => 'Quản lý chi nhánh']);
            }
        }

        if ($request->has('EMAIL') || $maroleToUpdate !== null) {
            $tkUpdate = [];
            if ($request->has('EMAIL')) {
                $tkUpdate['EMAIL'] = $request->EMAIL;
            }
            if ($maroleToUpdate !== null) {
                $tkUpdate['MAROLE'] = $maroleToUpdate;
            }
            \App\Models\TaiKhoan::where('USERNAME', $username)->update($tkUpdate);
        }

        // Nếu nhân viên được khôi phục từ trạng thái "Nghỉ việc", đặt lại mật khẩu mặc định là Username
        if ($oldTrangThai === 'Nghỉ việc' && $nhanVien->TRANGTHAI !== 'Nghỉ việc') {
            $tk = \App\Models\TaiKhoan::where('USERNAME', $username)->first();
            if ($tk) {
                $tk->PASSWORD = $username;
                $tk->save(); // Dùng save() để kích hoạt tính năng tự động hash mật khẩu của Eloquent
            }
        }

        return response()->json(['message' => 'Cập nhật thành công']);
    }

    public function deleteStaff($username)
    {
        $nhanVien = \App\Models\NhanVien::where('USERNAME', $username)->first();
        if ($nhanVien) {
            $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();

            if ($userLogin && $userLogin->USERNAME === $username) {
                return response()->json(['message' => 'Bạn không thể tự vô hiệu hóa tài khoản của chính mình'], 403);
            }

            if ($userLogin && $userLogin->MAROLE == 1) {
                $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
                if ($nhanVienLogin && $nhanVien->MACUAHANG != $nhanVienLogin->MACUAHANG) {
                    return response()->json(['message' => 'Bạn không có quyền thay đổi trạng thái nhân viên của chi nhánh khác'], 403);
                }
            }
            $nhanVien->update(['TRANGTHAI' => 'Nghỉ việc']);
        }

        // Ghi chú: Không cần thay đổi mật khẩu vì logic đăng nhập trong AuthController
        // đã tự động từ chối các tài khoản có TRANGTHAI == 'Nghỉ việc'.

        return response()->json(['message' => 'Đã vô hiệu hóa tài khoản nhân viên']);
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

        return response()->json([
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

            return response()->json($query->get());
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
            $targetTaiKhoan = \App\Models\TaiKhoan::with('nhanvien')->where('USERNAME', $targetUsername)->first();
            if (!$targetTaiKhoan) {
                return response()->json(['error' => 'Không tìm thấy nhân viên'], 404);
            }

            if ($userLogin->MAROLE == 0) {
                // Quản lý tổng (Role 0) được chấm công cho Quản lý chi nhánh (Role 1), hoặc Nhân viên trung tâm (Role 2, MACUAHANG=1)
                $isBranchManager = ($targetTaiKhoan->MAROLE == 1);
                $isCentralStaff = ($targetTaiKhoan->MAROLE == 2 && $targetTaiKhoan->nhanvien && $targetTaiKhoan->nhanvien->MACUAHANG == 1);

                if (!$isBranchManager && !$isCentralStaff) {
                    return response()->json(['error' => 'Quản lý tổng chỉ chấm công cho Quản lý chi nhánh và Nhân viên trung tâm. Hãy để Quản lý chi nhánh tự chấm công cho nhân viên của họ.'], 403);
                }
            } elseif ($userLogin->MAROLE == 1) {
                $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
                $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;

                // Được phép chấm công nếu là Nhân viên (Role 2) cùng chi nhánh, HOẶC là chính bản thân mình
                $isSelf = ($targetUsername === $userLogin->USERNAME);
                $isOwnStaff = ($targetTaiKhoan->MAROLE == 2 && $targetTaiKhoan->nhanvien && $targetTaiKhoan->nhanvien->MACUAHANG == $macuahang);

                if (!$isSelf && !$isOwnStaff) {
                    return response()->json(['error' => 'Bạn chỉ có quyền chấm công cho bản thân hoặc nhân viên thuộc chi nhánh của mình'], 403);
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
        if (\Illuminate\Support\Facades\Schema::hasTable('bienbanhuy')) {
            $chiPhiHuyQuery = DB::table('bienbanhuy')
                ->leftJoin('chitietbienbanhuy', 'bienbanhuy.id', '=', 'chitietbienbanhuy.bienBanHuyId')
                ->leftJoin('lohang', 'chitietbienbanhuy.loHangId', '=', 'lohang.id')
                ->whereMonth('bienbanhuy.createdAt', $thang)
                ->whereYear('bienbanhuy.createdAt', $nam);

            if ($macuahang) {
                $chiPhiHuyQuery->where('bienbanhuy.cuaHangId', $macuahang);
            }

            $chiPhiHuy = (float) $chiPhiHuyQuery->sum(DB::raw('COALESCE(chitietbienbanhuy.soLuongHuy, 0) * COALESCE(lohang.giaNhap, 0)'));
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
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * THỐNG KÊ NHẬP HÀNG: Chi phí & Tần suất nhập theo tháng
     * ─────────────────────────────────────────────────────────────────────
     */
    public function thongKeNhapHang(Request $request)
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahang = null;
        if ($userLogin && $userLogin->vaiTroId == 2) {
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
        }

        $thang = (int) $request->input('thang', now()->month);
        $nam   = (int) $request->input('nam', now()->year);

        // Tổng nhập theo phiếu trong tháng
        $nhapTheoPhieu = DB::table('lohang')
            ->join('sanpham', 'lohang.sanPhamId', '=', 'sanpham.id')
            ->leftJoin('hoadonnhap', 'lohang.cuaHangId', '=', 'hoadonnhap.cuaHangId')
            ->whereMonth('lohang.createdAt', $thang)
            ->whereYear('lohang.createdAt', $nam)
            ->when($macuahang, fn($q) => $q->where('lohang.cuaHangId', $macuahang))
            ->selectRaw("
                DATE(lohang.createdAt) as ngay,
                COUNT(DISTINCT lohang.id) as so_phieu,
                SUM(lohang.soLuongNhap) as tong_sl_nhap,
                SUM(lohang.soLuongNhap * lohang.giaNhap) as tong_chi_phi
            ")
            ->groupBy('ngay')
            ->orderBy('ngay')
            ->get();

        // Top SP nhập nhiều nhất
        $topSPNhap = DB::table('lohang')
            ->join('sanpham', 'lohang.sanPhamId', '=', 'sanpham.id')
            ->whereMonth('lohang.createdAt', $thang)
            ->whereYear('lohang.createdAt', $nam)
            ->when($macuahang, fn($q) => $q->where('lohang.cuaHangId', $macuahang))
            ->selectRaw("sanpham.id as MASP, sanpham.tenSanPham as TENSP,
                         SUM(lohang.soLuongNhap) as tong_nhap,
                         SUM(lohang.soLuongNhap * lohang.giaNhap) as tong_chi_phi,
                         COUNT(*) as so_lo")
            ->groupBy('sanpham.id', 'sanpham.tenSanPham')
            ->orderByDesc('tong_nhap')
            ->limit(10)
            ->get();

        // Nhập theo NCC trong tháng
        $nhapTheoNCC = DB::table('lohang')
            ->leftJoin('nhacungcap', 'lohang.nhaCungCapId', '=', 'nhacungcap.id')
            ->whereMonth('lohang.createdAt', $thang)
            ->whereYear('lohang.createdAt', $nam)
            ->when($macuahang, fn($q) => $q->where('lohang.cuaHangId', $macuahang))
            ->selectRaw("
                COALESCE(nhacungcap.tenNcc, 'Không xác định') as ten_ncc,
                lohang.nhaCungCapId as MANCC,
                SUM(lohang.soLuongNhap * lohang.giaNhap) as tong_tien,
                SUM(lohang.soLuongNhap) as tong_sl,
                COUNT(DISTINCT lohang.id) as so_phieu
            ")
            ->groupBy('lohang.nhaCungCapId', 'nhacungcap.tenNcc')
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
    }

    /**
     * ─────────────────────────────────────────────────────────────────────
     * PHÂN TÍCH HAO HỤT: % lô bị hủy vs đã bán — đánh giá quản lý tồn kho
     * ─────────────────────────────────────────────────────────────────────
     */
    public function thongKeHaoHut(Request $request)
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahang = null;
        if ($userLogin && $userLogin->vaiTroId == 2) {
            $staffProfile = \App\Models\NguoiDung::where('taiKhoanId', $userLogin->id)->first();
            $macuahang = $staffProfile ? $staffProfile->cuaHangId : 1;
        }

        $thang = (int) $request->input('thang', now()->month);
        $nam   = (int) $request->input('nam', now()->year);

        $query = DB::table('lohang')
            ->join('sanpham', 'lohang.sanPhamId', '=', 'sanpham.id')
            ->whereMonth('lohang.createdAt', $thang)
            ->whereYear('lohang.createdAt', $nam)
            ->when($macuahang, fn($q) => $q->where('lohang.cuaHangId', $macuahang));

        // Tổng quan
        $tongKet = (clone $query)
            ->selectRaw("
                SUM(lohang.soLuongNhap) as tong_nhap,
                SUM(lohang.soLuongCon) as tong_con,
                COUNT(*) as so_lo,
                SUM(CASE WHEN lohang.trangThai = 2 THEN 1 ELSE 0 END) as so_lo_het_han,
                SUM(CASE WHEN lohang.trangThai = 1 THEN 1 ELSE 0 END) as so_lo_ban_het,
                SUM(CASE WHEN lohang.trangThai = 3 THEN 1 ELSE 0 END) as so_lo_huy,
                SUM(CASE WHEN lohang.trangThai = 3 THEN lohang.soLuongCon * lohang.giaNhap ELSE 0 END) as gia_tri_huy
            ")
            ->first();

        // Hao hụt theo sản phẩm
        $haoHutTheoSP = (clone $query)
            ->selectRaw("
                sanpham.id as MASP, sanpham.tenSanPham as TENSP,
                SUM(lohang.soLuongNhap) as tong_nhap,
                SUM(lohang.soLuongCon) as tong_con,
                SUM(CASE WHEN lohang.trangThai = 3 THEN lohang.soLuongCon ELSE 0 END) as tong_huy,
                SUM(CASE WHEN lohang.trangThai = 3 THEN lohang.soLuongCon * lohang.giaNhap ELSE 0 END) as gia_tri_huy,
                ROUND(SUM(CASE WHEN lohang.trangThai = 3 THEN lohang.soLuongCon ELSE 0 END) / NULLIF(SUM(lohang.soLuongNhap), 0) * 100, 2) as ty_le_huy
            ")
            ->groupBy('sanpham.id', 'sanpham.tenSanPham')
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
        if ($userLogin && $userLogin->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
        }

        $loHangService = app(\App\Services\LoHangService::class);
        return response()->json($loHangService->getExpiryAlerts($macuahang));
    }
}
