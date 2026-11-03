<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoHang;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LoHangController extends Controller
{
    public function index(Request $request)
    {
        $query = LoHang::with(['sanPham', 'cuaHang', 'nhaCungCap', 'hoaDonNhap']);

        if ($request->filled('cuaHangId')) {
            $query->where('cuaHangId', $request->cuaHangId);
        }

        if ($request->filled('sanPhamId')) {
            $query->where('sanPhamId', $request->sanPhamId);
        }

        if ($request->filled('trangThai')) {
            $query->where('trangThai', $request->trangThai);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('maLoHang', 'like', "%{$search}%")
                  ->orWhereHas('sanPham', function ($sp) use ($search) {
                      $sp->where('tenSanPham', 'like', "%{$search}%");
                  })
                  ->orWhereHas('hoaDonNhap', function ($hd) use ($search) {
                      $hd->where('maHoaDonNhap', 'like', "%{$search}%");
                  });
            });
        }

        $batches = $query->latest('id')->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách lô hàng thành công',
            'data' => $batches,
        ]);
    }

    public function getExpiredBatches(Request $request)
    {
        $daysThreshold = $request->get('days', 3);
        $today = Carbon::now()->toDateString();
        $thresholdDate = Carbon::now()->addDays($daysThreshold)->toDateString();

        // 1. Quá hạn sử dụng (Expired)
        $expired = LoHang::with(['sanPham', 'cuaHang', 'nhaCungCap'])
            ->where('soLuongCon', '>', 0)
            ->where('trangThai', '!=', 3) // Chưa hủy
            ->where('hsd', '<', $today)
            ->get();

        // 2. Sắp hết hạn (Near expiry)
        $nearExpiry = LoHang::with(['sanPham', 'cuaHang', 'nhaCungCap'])
            ->where('soLuongCon', '>', 0)
            ->where('trangThai', '!=', 3)
            ->whereBetween('hsd', [$today, $thresholdDate])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Báo cáo hạn sử dụng (HSD) các lô hàng',
            'data' => [
                'quoc_han' => $expired,
                'sap_het_han' => $nearExpiry,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();
        $isDirectorOrAdmin = $user && (
            in_array((int)$user->vaiTroId, [1, 8]) ||
            in_array((int)$user->MAROLE, [0, 5]) ||
            strtolower($user->username ?? '') === 'admin'
        );

        if (!$isDirectorOrAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Ban Giám Đốc mới có quyền tạo lô hàng mới cho các chi nhánh! Chi nhánh không thể tự ý nhập lô hàng mới, vui lòng gửi yêu cầu điều chuyển để được chi nhánh khác hỗ trợ hoặc Giám đốc phê duyệt.',
            ], 403);
        }

        $validated = $request->validate([
            'maLoHang' => 'required|string|max:50|unique:loHang,ma_lo_hang',
            'sanPhamId' => 'required|exists:sanPham,id',
            'cuaHangId' => 'required|exists:cuahang,id',
            'nhaCungCapId' => 'nullable|exists:nhaCungCap,id',
            'soLuongNhap' => 'required|integer|min:1',
            'ngaySanXuat' => 'nullable|date',
            'hsd' => 'required|date',
            'giaNhap' => 'nullable|numeric|min:0',
        ]);

        $batch = LoHang::create(array_merge($validated, [
            'soLuongCon' => $validated['soLuongNhap'],
            'trangThai' => 0, // Con_hang
        ]));

        $tonKho = \App\Models\TonKhoChiNhanh::firstOrCreate(
            ['cuaHangId' => $validated['cuaHangId'], 'sanPhamId' => $validated['sanPhamId']],
            ['soLuongTon' => 0]
        );
        $tonKho->increment('soLuongTon', $validated['soLuongNhap']);

        \App\Models\NhatKyKho::create([
            'cuaHangId' => $validated['cuaHangId'],
            'sanPhamId' => $validated['sanPhamId'],
            'loaiBienDong' => 4,
            'soLuongThayDoi' => $validated['soLuongNhap'],
            'soLuongTonSau' => $tonKho->soLuongTon,
            'maThamChieu' => $validated['maLoHang'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Khởi tạo lô hàng mới thành công',
            'data' => $batch->load(['sanPham', 'cuaHang', 'nhaCungCap']),
        ], 201);
    }

    public function show($id)
    {
        $batch = LoHang::with(['sanPham', 'cuaHang', 'nhaCungCap'])->find($id);

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông tin lô hàng',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết lô hàng',
            'data' => $batch,
        ]);
    }

    public function updateTrangThai(Request $request, $id)
    {
        $batch = is_numeric($id) ? LoHang::find($id) : LoHang::where('maLoHang', $id)->first();
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy lô hàng'], 404);
        }

        $trangthai = $request->input('trangthai') ?? $request->input('trangThai');
        $lydo = $request->input('ly_do') ?? $request->input('lyDo') ?? 'Hủy lô hàng';

        // Nếu trạng thái là hủy lô hàng
        if ($trangthai === 'bi_huy' || $trangthai === 3 || $trangthai === '3') {
            $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $nguoiLapId = 1;
            $prof = null;
            if ($user) {
                $vaiTroId = (int) ($user->vaiTroId ?? 0);
                $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;
                $username = strtolower((string) ($user->username ?? $user->USERNAME ?? ''));
                if ($vaiTroId === 1 || $maRole === 0 || $username === 'admin') {
                    return response()->json([
                        'success' => false,
                        'status' => false,
                        'message' => 'Tài khoản Quản trị viên (Admin) không được phép tham gia ký duyệt biên bản hủy!',
                    ], 403);
                }
                $prof = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
                $nguoiLapId = $prof?->id ?? $user->id;
            }

            $items = [
                [
                    'lohang_id' => $batch->id,
                    'so_luong_huy' => $batch->soLuongCon > 0 ? $batch->soLuongCon : 1,
                ]
            ];

            // Quản lý chi nhánh (vaiTroId = 2 hoặc MAROLE = 1): không cần chờ ký và trừ tồn kho ngay lập tức
            // Nhân viên kho: tạo biên bản ở trạng thái chờ ký (autoSign = 0) chuyển tới Duyệt & Ký số
            $isBranchManager = $user && (
                (int) ($user->vaiTroId ?? 0) === 2 ||
                (int) ($user->MAROLE ?? -1) === 1
            );
            $autoSign = $isBranchManager ? 1 : 0;

            \Illuminate\Support\Facades\DB::statement('CALL sp_create_bien_ban_huy(?, ?, ?, ?, ?, @bienban_id, @ma_bien_ban, @error)', [
                $batch->cuaHangId,
                $nguoiLapId,
                $lydo,
                $autoSign,
                json_encode($items)
            ]);

            $res = \Illuminate\Support\Facades\DB::select('SELECT @bienban_id AS bienban_id, @ma_bien_ban AS ma_bien_ban, @error AS error')[0];

            $message = $isBranchManager
                ? "Quản lý chi nhánh đã duyệt lập Biên bản hủy ({$res->ma_bien_ban}) cho lô {$batch->maLoHang}! Số lượng tồn kho đã được trừ ngay lập tức."
                : "Đã lập Biên bản hủy ({$res->ma_bien_ban}) cho lô {$batch->maLoHang}! Chứng từ đang chờ Quản lý chi nhánh phê duyệt tại mục Duyệt & Ký số.";

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => $message,
            ]);
        }

        $statusCode = is_numeric($trangthai) ? (int)$trangthai : 0;
        $batch->update(['trangThai' => $statusCode]);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Cập nhật trạng thái lô hàng thành công',
            'data' => $batch
        ]);
    }
}
