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
        $query = TaiKhoan::with('khachhang.cuahang', 'nhanvien.cuahang', 'vaitro');

        if ($user && $user->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $user->USERNAME)->first();
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;

            $query->where(function ($q) use ($macuahang, $user) {
                // Thấy tất cả Khách hàng
                $q->where('MAROLE', 3)
                  // Thấy Nhân viên (Role 2) thuộc cùng chi nhánh
                  ->orWhere(function ($q2) use ($macuahang) {
                      $q2->where('MAROLE', 2)
                         ->whereHas('nhanvien', function ($q3) use ($macuahang) {
                             $q3->where('MACUAHANG', $macuahang);
                         });
                  })
                  // Hoặc thấy chính họ
                  ->orWhere('USERNAME', $user->USERNAME);
            });
        }

        $users = $query->get();
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
     * Báo cáo doanh thu (theo ngày hoặc tháng)
     */
    public function revenueReport(Request $request)
    {
        $type = $request->query('type', 'month'); // 'day' or 'month'

        $query = \App\Models\HdBan::whereIn('TRANGTHAITHANHTOAN', ['Đã hoàn thành', 'Đã duyệt', 'Đang giao']);

        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        if ($userLogin && $userLogin->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
            $query->where('MACUAHANG', $macuahang);
        }

        $result = [];

        if ($type === 'day') {
            // Lấy 30 ngày gần nhất
            $startDate = now()->subDays(29)->startOfDay();
            $data = $query->where('NGAYLAP', '>=', $startDate)
                ->select(DB::raw('DATE(NGAYLAP) as date'), DB::raw('SUM(TONGTIEN) as total'))
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $result[] = [
                    'date' => $date,
                    'total' => isset($data[$date]) ? $data[$date]->total : 0
                ];
            }
        } else {
            // Lấy 12 tháng gần nhất
            $startDate = now()->subMonths(11)->startOfMonth();
            $data = $query->where('NGAYLAP', '>=', $startDate)
                ->select(DB::raw('DATE_FORMAT(NGAYLAP, "%Y-%m") as date'), DB::raw('SUM(TONGTIEN) as total'))
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            for ($i = 11; $i >= 0; $i--) {
                $date = now()->subMonths($i)->format('Y-m');
                $result[] = [
                    'date' => $date,
                    'total' => isset($data[$date]) ? $data[$date]->total : 0
                ];
            }
        }

        return response()->json($result);
    }

    public function biDashboard(Request $request)
    {
        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $macuahang = null;
        if ($userLogin && $userLogin->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
        }

        $baseQuery = \App\Models\HdBan::whereIn('TRANGTHAITHANHTOAN', ['Đã hoàn thành', 'Đã duyệt', 'Đang giao']);
        if ($macuahang) {
            $baseQuery->where('MACUAHANG', $macuahang);
        }

        // Summary
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $thisYear = now()->startOfYear();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        $revenueToday = (clone $baseQuery)->where('NGAYLAP', '>=', $today)->sum('TONGTIEN');
        $revenueThisMonth = (clone $baseQuery)->where('NGAYLAP', '>=', $thisMonth)->sum('TONGTIEN');
        $revenueThisYear = (clone $baseQuery)->where('NGAYLAP', '>=', $thisYear)->sum('TONGTIEN');
        
        $revenueLastMonth = (clone $baseQuery)
            ->whereBetween('NGAYLAP', [$lastMonthStart, $lastMonthEnd])
            ->sum('TONGTIEN');

        $percentIncreaseMonth = 0;
        if ($revenueLastMonth > 0) {
            $percentIncreaseMonth = (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100;
        } else if ($revenueThisMonth > 0) {
            $percentIncreaseMonth = 100; // Increase from 0
        }

        // Revenue by store (only for role 0)
        $revenueByStore = [];
        if (!$macuahang) {
            $revenueByStore = (clone $baseQuery)
                ->join('cuahang', 'hdban.MACUAHANG', '=', 'cuahang.MACUAHANG')
                ->where('NGAYLAP', '>=', $thisMonth)
                ->select('cuahang.TENCUAHANG', DB::raw('SUM(hdban.TONGTIEN) as total'))
                ->groupBy('cuahang.MACUAHANG', 'cuahang.TENCUAHANG')
                ->get();
        }

        // Revenue by time (today)
        $revenueByTimeRaw = (clone $baseQuery)
            ->where('NGAYLAP', '>=', $today)
            ->select(DB::raw('HOUR(NGAYLAP) as hour'), DB::raw('SUM(TONGTIEN) as total'))
            ->groupBy('hour')
            ->get();

        $revenueByTime = [
            '00:00 - 08:00' => 0,
            '08:00 - 12:00' => 0,
            '12:00 - 16:00' => 0,
            '16:00 - 20:00' => 0,
            '20:00 - 24:00' => 0,
        ];

        foreach ($revenueByTimeRaw as $row) {
            $h = $row->hour;
            $total = $row->total;
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
            'revenue_by_store' => $revenueByStore,
            'revenue_by_time' => collect($revenueByTime)->map(function ($total, $time) {
                return ['time_range' => $time, 'total' => $total];
            })->values()
        ]);
    }

    /**
     * Top 5 sản phẩm bán chạy nhất
     */
    public function topProducts()
    {
        $query = \App\Models\ChiTietHdBan::join('hdban', 'chitiethdban.MAHD', '=', 'hdban.MAHD')
            ->join('sanpham', 'chitiethdban.MASP', '=', 'sanpham.MASP')
            ->whereIn('hdban.TRANGTHAITHANHTOAN', ['Đã hoàn thành', 'Đã duyệt', 'Đang giao']);

        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();
        if ($userLogin && $userLogin->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
            $query->where('hdban.MACUAHANG', $macuahang);
        }

        $data = $query->select('sanpham.TENSP', DB::raw('SUM(chitiethdban.SOLUONG) as total_sold'))
            ->groupBy('sanpham.MASP', 'sanpham.TENSP')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    public function getChamCong(Request $request)
    {
        $thang = $request->query('thang', now()->month);
        $nam = $request->query('nam', now()->year);

        $query = \App\Models\ChamCong::with('nhanvien.taikhoan')
            ->whereMonth('NGAYCHAMCONG', $thang)
            ->whereYear('NGAYCHAMCONG', $nam);

        $userLogin = \Illuminate\Support\Facades\Auth::guard('api')->user();

        if ($userLogin) {
            if ($userLogin->MAROLE == 0) {
                // Quản lý tổng (Role 0) xem chấm công của Quản lý chi nhánh (Role 1), hoặc Nhân viên trung tâm (Role 2, MACUAHANG=1)
                $query->whereHas('nhanvien.taikhoan', function ($q) {
                    $q->where('MAROLE', 1)
                      ->orWhere(function ($q2) {
                          $q2->where('MAROLE', 2)
                             ->whereHas('nhanvien', function ($q3) {
                                 $q3->where('MACUAHANG', 1);
                             });
                      });
                });
            } elseif ($userLogin->MAROLE == 1) {
                // Quản lý chi nhánh (Role 1) xem chấm công của Nhân viên (Role 2) thuộc chi nhánh HOẶC chính bản thân mình
                $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
                $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
                $username = $userLogin->USERNAME;

                $query->whereHas('nhanvien', function ($q) use ($macuahang, $username) {
                    $q->where(function ($q3) use ($macuahang, $username) {
                        $q3->where('MACUAHANG', $macuahang)
                           ->whereHas('taikhoan', function ($q2) {
                               $q2->where('MAROLE', 2);
                           });
                    })->orWhere('USERNAME', $username);
                });
            }
        }

        $data = $query->get();

        return response()->json($data);
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
        if ($userLogin && $userLogin->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
        }
        if (!$macuahang && $request->filled('macuahang')) {
            $macuahang = (int) $request->macuahang;
        }

        $thang = (int) $request->input('thang', now()->month);
        $nam   = (int) $request->input('nam', now()->year);

        // ── Doanh thu: tổng tiền hóa đơn bán đã hoàn thành ─────────────
        $doanhThuQuery = DB::table('hdban')
            ->whereIn('TRANGTHAITHANHTOAN', ['Đã hoàn thành', 'Đã duyệt', 'Đang giao'])
            ->whereMonth('NGAYLAP', $thang)
            ->whereYear('NGAYLAP', $nam);

        if ($macuahang) {
            $doanhThuQuery->where('MACUAHANG', $macuahang);
        }

        $doanhThu = $doanhThuQuery->sum('TONGTIEN');

        // ── Chi phí nhập: tổng (SL bán × đơn giá nhập lô) theo tháng ───
        // Chỉ tính các dòng chitiethdban có MALOHAN (dữ liệu mới)
        $chiPhiQuery = DB::table('chitiethdban')
            ->join('hdban', 'chitiethdban.MAHD', '=', 'hdban.MAHD')
            ->join('lo_hang', 'chitiethdban.MALOHAN', '=', 'lo_hang.MALOHAN')
            ->whereIn('hdban.TRANGTHAITHANHTOAN', ['Đã hoàn thành', 'Đã duyệt', 'Đang giao'])
            ->whereMonth('hdban.NGAYLAP', $thang)
            ->whereYear('hdban.NGAYLAP', $nam)
            ->whereNotNull('chitiethdban.MALOHAN');

        if ($macuahang) {
            $chiPhiQuery->where('hdban.MACUAHANG', $macuahang);
        }

        $chiPhi = $chiPhiQuery->sum(DB::raw('chitiethdban.SOLUONG * lo_hang.DONGIA_NHAP'));

        // ── Chi phí hủy hàng (hết hạn, hỏng) trong tháng ──────────────
        $chiPhiHuyQuery = DB::table('bien_ban_huy')
            ->join('lo_hang', 'bien_ban_huy.MALOHAN', '=', 'lo_hang.MALOHAN')
            ->whereMonth('bien_ban_huy.NGAY_HUY', $thang)
            ->whereYear('bien_ban_huy.NGAY_HUY', $nam);

        if ($macuahang) {
            $chiPhiHuyQuery->where('bien_ban_huy.MACUAHANG', $macuahang);
        }

        $chiPhiHuy = $chiPhiHuyQuery->sum(DB::raw('bien_ban_huy.SOLUONG_HUY * lo_hang.DONGIA_NHAP'));

        // ── Tổng chi phí = nhập hàng + hủy ─────────────────────────────
        $tongChiPhi = $chiPhi + $chiPhiHuy;
        $loiNhuan   = $doanhThu - $tongChiPhi;
        $biLoi      = $doanhThu > 0 ? round(($loiNhuan / $doanhThu) * 100, 2) : 0;

        // ── Lợi nhuận theo từng sản phẩm ───────────────────────────────
        $loiNhuanTheoSP = DB::table('chitiethdban')
            ->join('hdban', 'chitiethdban.MAHD', '=', 'hdban.MAHD')
            ->join('sanpham', 'chitiethdban.MASP', '=', 'sanpham.MASP')
            ->leftJoin('lo_hang', 'chitiethdban.MALOHAN', '=', 'lo_hang.MALOHAN')
            ->whereIn('hdban.TRANGTHAITHANHTOAN', ['Đã hoàn thành', 'Đã duyệt', 'Đang giao'])
            ->whereMonth('hdban.NGAYLAP', $thang)
            ->whereYear('hdban.NGAYLAP', $nam)
            ->when($macuahang, fn($q) => $q->where('hdban.MACUAHANG', $macuahang))
            ->selectRaw("
                sanpham.MASP,
                sanpham.TENSP,
                SUM(chitiethdban.SOLUONG) as tong_ban,
                SUM(chitiethdban.SOLUONG * chitiethdban.DONGIA) as doanh_thu,
                SUM(chitiethdban.SOLUONG * COALESCE(lo_hang.DONGIA_NHAP, 0)) as chi_phi,
                SUM(chitiethdban.SOLUONG * chitiethdban.DONGIA)
                  - SUM(chitiethdban.SOLUONG * COALESCE(lo_hang.DONGIA_NHAP, 0)) as loi_nhuan
            ")
            ->groupBy('sanpham.MASP', 'sanpham.TENSP')
            ->orderByDesc('loi_nhuan')
            ->limit(10)
            ->get();

        // ── Lợi nhuận theo ngày trong tháng ────────────────────────────
        $loiNhuanTheoNgay = DB::table('chitiethdban')
            ->join('hdban', 'chitiethdban.MAHD', '=', 'hdban.MAHD')
            ->leftJoin('lo_hang', 'chitiethdban.MALOHAN', '=', 'lo_hang.MALOHAN')
            ->whereIn('hdban.TRANGTHAITHANHTOAN', ['Đã hoàn thành', 'Đã duyệt', 'Đang giao'])
            ->whereMonth('hdban.NGAYLAP', $thang)
            ->whereYear('hdban.NGAYLAP', $nam)
            ->when($macuahang, fn($q) => $q->where('hdban.MACUAHANG', $macuahang))
            ->selectRaw("
                DATE(hdban.NGAYLAP) as ngay,
                SUM(chitiethdban.SOLUONG * chitiethdban.DONGIA) as doanh_thu,
                SUM(chitiethdban.SOLUONG * COALESCE(lo_hang.DONGIA_NHAP, 0)) as chi_phi
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
            'thang'          => $thang,
            'nam'            => $nam,
            'doanh_thu'      => round($doanhThu, 2),
            'chi_phi_nhap'   => round($chiPhi, 2),
            'chi_phi_huy'    => round($chiPhiHuy, 2),
            'tong_chi_phi'   => round($tongChiPhi, 2),
            'loi_nhuan'      => round($loiNhuan, 2),
            'bien_loi_nhuan' => $biLoi,         // % biên lợi nhuận
            'theo_san_pham'  => $loiNhuanTheoSP,
            'theo_ngay'      => $loiNhuanTheoNgay,
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
        if ($userLogin && $userLogin->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
        }

        $thang = (int) $request->input('thang', now()->month);
        $nam   = (int) $request->input('nam', now()->year);

        // Tổng nhập theo phiếu trong tháng
        $nhapTheoPhieu = DB::table('lo_hang')
            ->join('sanpham', 'lo_hang.MASP', '=', 'sanpham.MASP')
            ->join('hdnhap', 'lo_hang.MAHDNHAP', '=', 'hdnhap.MAHDNHAP')
            ->whereMonth('lo_hang.NGAYNHAP', $thang)
            ->whereYear('lo_hang.NGAYNHAP', $nam)
            ->when($macuahang, fn($q) => $q->where('lo_hang.MACUAHANG', $macuahang))
            ->selectRaw("
                DATE(lo_hang.NGAYNHAP) as ngay,
                COUNT(DISTINCT lo_hang.MAHDNHAP) as so_phieu,
                SUM(lo_hang.SOLUONG_NHAP) as tong_sl_nhap,
                SUM(lo_hang.SOLUONG_NHAP * lo_hang.DONGIA_NHAP) as tong_chi_phi
            ")
            ->groupBy('ngay')
            ->orderBy('ngay')
            ->get();

        // Top SP nhập nhiều nhất
        $topSPNhap = DB::table('lo_hang')
            ->join('sanpham', 'lo_hang.MASP', '=', 'sanpham.MASP')
            ->whereMonth('lo_hang.NGAYNHAP', $thang)
            ->whereYear('lo_hang.NGAYNHAP', $nam)
            ->when($macuahang, fn($q) => $q->where('lo_hang.MACUAHANG', $macuahang))
            ->selectRaw("sanpham.MASP, sanpham.TENSP,
                         SUM(lo_hang.SOLUONG_NHAP) as tong_nhap,
                         SUM(lo_hang.SOLUONG_NHAP * lo_hang.DONGIA_NHAP) as tong_chi_phi,
                         COUNT(*) as so_lo")
            ->groupBy('sanpham.MASP', 'sanpham.TENSP')
            ->orderByDesc('tong_nhap')
            ->limit(10)
            ->get();

        // Nhập theo NCC trong tháng
        $nhapTheoNCC = DB::table('lo_hang')
            ->join('hdnhap', 'lo_hang.MAHDNHAP', '=', 'hdnhap.MAHDNHAP')
            ->leftJoin('nha_cung_cap', 'hdnhap.MANCC', '=', 'nha_cung_cap.MANCC')
            ->whereMonth('lo_hang.NGAYNHAP', $thang)
            ->whereYear('lo_hang.NGAYNHAP', $nam)
            ->when($macuahang, fn($q) => $q->where('lo_hang.MACUAHANG', $macuahang))
            ->selectRaw("
                COALESCE(nha_cung_cap.TENNCC, 'Không xác định') as ten_ncc,
                hdnhap.MANCC,
                SUM(lo_hang.SOLUONG_NHAP * lo_hang.DONGIA_NHAP) as tong_tien,
                SUM(lo_hang.SOLUONG_NHAP) as tong_sl,
                COUNT(DISTINCT lo_hang.MAHDNHAP) as so_phieu
            ")
            ->groupBy('hdnhap.MANCC', 'nha_cung_cap.TENNCC')
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
        if ($userLogin && $userLogin->MAROLE == 1) {
            $nhanVienLogin = \App\Models\NhanVien::where('USERNAME', $userLogin->USERNAME)->first();
            $macuahang = $nhanVienLogin ? $nhanVienLogin->MACUAHANG : 1;
        }

        $thang = (int) $request->input('thang', now()->month);
        $nam   = (int) $request->input('nam', now()->year);

        $query = DB::table('lo_hang')
            ->join('sanpham', 'lo_hang.MASP', '=', 'sanpham.MASP')
            ->whereMonth('lo_hang.NGAYNHAP', $thang)
            ->whereYear('lo_hang.NGAYNHAP', $nam)
            ->when($macuahang, fn($q) => $q->where('lo_hang.MACUAHANG', $macuahang));

        // Tổng quan
        $tongKet = (clone $query)
            ->selectRaw("
                SUM(lo_hang.SOLUONG_NHAP) as tong_nhap,
                SUM(lo_hang.SOLUONG_BAN) as tong_ban,
                SUM(lo_hang.SOLUONG_HUY) as tong_huy,
                SUM(lo_hang.SOLUONG_CON) as tong_con,
                COUNT(*) as so_lo,
                SUM(CASE WHEN lo_hang.TRANGTHAI = 'het_han' THEN 1 ELSE 0 END) as so_lo_het_han,
                SUM(CASE WHEN lo_hang.TRANGTHAI = 'da_ban_het' THEN 1 ELSE 0 END) as so_lo_ban_het,
                SUM(CASE WHEN lo_hang.TRANGTHAI = 'bi_huy' THEN 1 ELSE 0 END) as so_lo_huy,
                SUM(lo_hang.SOLUONG_HUY * lo_hang.DONGIA_NHAP) as gia_tri_huy
            ")
            ->first();

        // Hao hụt theo sản phẩm
        $haoHutTheoSP = (clone $query)
            ->selectRaw("
                sanpham.MASP, sanpham.TENSP,
                SUM(lo_hang.SOLUONG_NHAP) as tong_nhap,
                SUM(lo_hang.SOLUONG_BAN) as tong_ban,
                SUM(lo_hang.SOLUONG_HUY) as tong_huy,
                SUM(lo_hang.SOLUONG_HUY * lo_hang.DONGIA_NHAP) as gia_tri_huy,
                ROUND(SUM(lo_hang.SOLUONG_HUY) / NULLIF(SUM(lo_hang.SOLUONG_NHAP), 0) * 100, 2) as ty_le_huy
            ")
            ->groupBy('sanpham.MASP', 'sanpham.TENSP')
            ->orderByDesc('ty_le_huy')
            ->get();

        $tongNhap = $tongKet->tong_nhap ?: 1;

        return response()->json([
            'thang'            => $thang,
            'nam'              => $nam,
            'tong_nhap'        => (int) $tongKet->tong_nhap,
            'tong_ban'         => (int) $tongKet->tong_ban,
            'tong_huy'         => (int) $tongKet->tong_huy,
            'tong_con'         => (int) $tongKet->tong_con,
            'ty_le_ban'        => round($tongKet->tong_ban / $tongNhap * 100, 2),
            'ty_le_huy'        => round($tongKet->tong_huy / $tongNhap * 100, 2),
            'gia_tri_huy'      => round($tongKet->gia_tri_huy ?? 0, 2),
            'so_lo'            => (int) $tongKet->so_lo,
            'so_lo_het_han'    => (int) $tongKet->so_lo_het_han,
            'so_lo_ban_het'    => (int) $tongKet->so_lo_ban_het,
            'so_lo_bi_huy'     => (int) $tongKet->so_lo_huy,
            'hao_hut_theo_sp'  => $haoHutTheoSP,
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
