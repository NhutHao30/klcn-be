<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\LoHang;
use App\Models\HdNhap;
use App\Models\ChiTietHdNhap;
use App\Models\NhanVien;
use App\Services\LoHangService;
use Carbon\Carbon;

class LoHangController extends Controller
{
    protected LoHangService $loHangService;

    public function __construct(LoHangService $loHangService)
    {
        $this->loHangService = $loHangService;
    }

    /**
     * Danh sách lô hàng với đầy đủ filter
     *
     * Query params:
     *   masp, macuahang, trangthai, hsd_from, hsd_to,
     *   sap_het_han (ngày), search (malohan/tensp)
     */
    public function index(Request $request)
    {
        try {
            $this->loHangService->autoExpireLo();
        } catch (\Exception $e) {}

        $user = Auth::guard('api')->user();
        $maCuaHang = $this->getMaCuaHangFromUser($user);

        $query = LoHang::with(['sanpham:MASP,TENSP,HINHANH,MALOAI', 'cuahang:MACUAHANG,TENCUAHANG', 'hdnhap.nhacungcap'])
            ->withCount(['bienBanHuys']);

        // Filter cửa hàng
        if ($maCuaHang) {
            $query->where('MACUAHANG', $maCuaHang);
        } elseif ($request->filled('macuahang')) {
            $query->where('MACUAHANG', $request->macuahang);
        }

        // Filter sản phẩm
        if ($request->filled('masp')) {
            $query->where('MASP', $request->masp);
        }

        // Filter trạng thái
        if ($request->filled('trangthai')) {
            $query->where('TRANGTHAI', $request->trangthai);
        }

        // Filter theo HSD range
        if ($request->filled('hsd_from')) {
            $query->where('HSD', '>=', $request->hsd_from);
        }
        if ($request->filled('hsd_to')) {
            $query->where('HSD', '<=', $request->hsd_to);
        }

        // Filter sắp hết hạn trong N ngày
        if ($request->filled('sap_het_han')) {
            $days = (int) $request->sap_het_han;
            $query->where('TRANGTHAI', 'con_hang')
                  ->where('HSD', '>=', Carbon::today())
                  ->where('HSD', '<=', Carbon::today()->addDays($days));
        }

        // Search theo mã lô hoặc tên sản phẩm
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('MALOHAN', 'like', "%{$search}%")
                  ->orWhereHas('sanpham', fn($q2) => $q2->where('TENSP', 'like', "%{$search}%"));
            });
        }

        // Sắp xếp
        $sortBy = $request->input('sort_by', 'HSD');
        $direction = $request->input('direction', 'asc');
        $allowedSorts = ['HSD', 'NGAYNHAP', 'SOLUONG_CON', 'SOLUONG_NHAP', 'created_at'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'HSD';

        $query->orderBy($sortBy, $direction);

        $perPage = (int) $request->input('per_page', 50);
        $results = $query->paginate($perPage);

        // Append computed attributes
        $results->getCollection()->transform(function ($lo) {
            return $lo->append(['so_ngay_con_lai', 'muc_canh_bao', 'tong_chi_phi', 'phan_tram_ban']);
        });

        return response()->json(['status' => true, 'data' => $results]);
    }

    /**
     * Chi tiết 1 lô hàng
     */
    public function show(string $malohan)
    {
        $lo = LoHang::with([
            'sanpham:MASP,TENSP,HINHANH,GIABAN',
            'cuahang:MACUAHANG,TENCUAHANG',
            'hdnhap.nhacungcap',
            'bienBanHuys.taikhoan',
            'chiTietHdBans.hdban:MAHD,NGAYLAP,TRANGTHAITHANHTOAN',
        ])
        ->where('MALOHAN', $malohan)
        ->first();

        if (!$lo) {
            return response()->json(['error' => 'Không tìm thấy lô hàng'], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $lo->append(['so_ngay_con_lai', 'muc_canh_bao', 'tong_chi_phi', 'phan_tram_ban'])
        ]);
    }

    /**
     * Lấy lịch sử lô của 1 sản phẩm
     */
    public function loHangSanPham(string $masp, Request $request)
    {
        $user = Auth::guard('api')->user();
        $maCuaHang = $this->getMaCuaHangFromUser($user);

        $query = LoHang::with(['cuahang:MACUAHANG,TENCUAHANG', 'hdnhap:MAHDNHAP,NGAYLAP,MANCC,MACUAHANG_NHAP'])
            ->where('MASP', $masp);

        if ($maCuaHang) {
            $query->where('MACUAHANG', $maCuaHang);
        }

        if ($request->filled('trangthai')) {
            $query->where('TRANGTHAI', $request->trangthai);
        }

        $results = $query->orderByDesc('NGAYNHAP')->paginate(20);
        $results->getCollection()->transform(fn($lo) => $lo->append(['so_ngay_con_lai', 'muc_canh_bao', 'tong_chi_phi', 'phan_tram_ban']));

        return response()->json(['status' => true, 'data' => $results]);
    }

    /**
     * Dashboard cảnh báo HSD
     */
    public function canhBao(Request $request)
    {
        try {
            $this->loHangService->autoExpireLo();
        } catch (\Exception $e) {}

        $user = Auth::guard('api')->user();
        $maCuaHang = $this->getMaCuaHangFromUser($user);

        // Cho phép role 0 (admin tổng) xem theo cửa hàng cụ thể
        if (!$maCuaHang && $request->filled('macuahang')) {
            $maCuaHang = (int) $request->macuahang;
        }

        $alerts = $this->loHangService->getExpiryAlerts($maCuaHang);
        return response()->json(['status' => true, 'data' => $alerts]);
    }

    /**
     * Nhập lô hàng mới.
     *
     * Tạo phiếu nhập (HdNhap) + chi tiết + lô hàng (LoHang) trong 1 transaction.
     *
     * Body JSON:
     * {
     *   "MAHDNHAP": "NH001",          // optional — tự sinh nếu không có
     *   "MANCC": "NCC001",            // optional
     *   "MACUAHANG_NHAP": 1,          // chi nhánh nhận
     *   "GHICHU": "...",              // optional
     *   "chi_tiet": [
     *     {
     *       "MASP": "SP001",
     *       "SOLUONGTCT": 30,         // số lượng theo ct
     *       "SOLUONGTN": 30,          // số lượng thực nhận
     *       "DONGIANHAP": 5000,
     *       "NGAYSX": "2026-09-01",   // optional
     *       "HSD_LO": "2026-09-05",   // HSD lô
     *       "GHICHU": "..."           // optional
     *     }
     *   ]
     * }
     */
    public function nhapLo(Request $request)
    {
        $user = Auth::guard('api')->user();
        $maCuaHang = $this->getMaCuaHangFromUser($user) ?? $request->input('MACUAHANG_NHAP', 1);

        // Hỗ trợ tự động đóng gói nếu client gửi request dạng đơn lẻ (không có mảng chi_tiet)
        if (!$request->has('chi_tiet') && $request->filled('MASP')) {
            $soluong = (int) ($request->input('SOLUONG') ?? $request->input('SOLUONGTN', 1));
            $request->merge([
                'MACUAHANG_NHAP' => $request->input('MACUAHANG_NHAP') ?? $request->input('MACUAHANG'),
                'chi_tiet' => [
                    [
                        'MASP'       => $request->input('MASP'),
                        'SOLUONGTCT' => (int) ($request->input('SOLUONGTCT') ?? $soluong),
                        'SOLUONGTN'  => $soluong,
                        'DONGIANHAP' => (float) $request->input('DONGIANHAP', 0),
                        'NGAYSX'     => $request->input('NGAYSX'),
                        'HSD_LO'     => $request->input('HSD_LO'),
                        'GHICHU'     => $request->input('GHICHU'),
                    ]
                ]
            ]);
        }

        $request->validate([
            'MANCC'           => 'nullable|string|exists:nha_cung_cap,MANCC',
            'MACUAHANG_NHAP'  => 'nullable|integer|exists:cuahang,MACUAHANG',
            'GHICHU'          => 'nullable|string',
            'chi_tiet'        => 'required|array|min:1',
            'chi_tiet.*.MASP'        => 'required|string|exists:sanpham,MASP',
            'chi_tiet.*.SOLUONGTCT'  => 'required|integer|min:1',
            'chi_tiet.*.SOLUONGTN'   => 'required|integer|min:0',
            'chi_tiet.*.DONGIANHAP'  => 'required|numeric|min:0',
            'chi_tiet.*.HSD_LO'      => 'required|date|after_or_equal:today|after_or_equal:chi_tiet.*.NGAYSX',
            'chi_tiet.*.NGAYSX'      => 'nullable|date|before_or_equal:today',
            'chi_tiet.*.GHICHU'      => 'nullable|string',
        ], [
            'chi_tiet.*.HSD_LO.after_or_equal' => 'Hạn sử dụng lô bánh không được nhỏ hơn ngày sản xuất hoặc nhỏ hơn ngày hiện tại!'
        ]);

        try {
            $result = DB::transaction(function () use ($request, $user, $maCuaHang) {
                // Sinh mã phiếu nhập tự động
                $lastHdNhap = HdNhap::orderByDesc('MAHDNHAP')->first();
                $seq = $lastHdNhap ? ((int) ltrim(substr($lastHdNhap->MAHDNHAP, 2), '0') + 1) : 1;
                $maHdNhap = 'NH' . str_pad($seq, 6, '0', STR_PAD_LEFT);

                $tongtienNhap = collect($request->chi_tiet)->sum(fn($ct) => $ct['SOLUONGTN'] * $ct['DONGIANHAP']);

                // Chữ ký số người lập / thủ kho & NCC
                $nhanVien = \App\Models\NhanVien::where('USERNAME', $user?->USERNAME)->first();
                $kyThuKho = $nhanVien?->CHU_KY;
                $kyNcc = $request->input('ky_ncc', $request->input('KY_NCC'));

                // Tạo phiếu nhập
                $hdNhap = HdNhap::create([
                    'MAHDNHAP'         => $maHdNhap,
                    'NGAYLAP'          => now(),
                    'USERNAME'         => $user?->USERNAME,
                    'MANCC'            => $request->MANCC,
                    'MACUAHANG_NHAP'   => $maCuaHang,
                    'TONGTIEN_NHAP'    => $tongtienNhap,
                    'GHICHU'           => $request->GHICHU,
                    'KY_THU_KHO'       => $kyThuKho,
                    'KY_NCC'           => $kyNcc,
                    'TRANGTHAI_KY'     => ($kyThuKho && $kyNcc) ? 'da_ky_mot_phan' : 'cho_ky',
                ]);

                $loHangs = [];
                foreach ($request->chi_tiet as $ct) {
                    // Lưu chi tiết phiếu nhập
                    ChiTietHdNhap::create([
                        'MAHDNHAP'   => $maHdNhap,
                        'MASP'       => $ct['MASP'],
                        'SOLUONGTCT' => $ct['SOLUONGTCT'],
                        'SOLUONGTN'  => $ct['SOLUONGTN'],
                        'DONGIANHAP' => $ct['DONGIANHAP'],
                        'MACUAHANG'  => $maCuaHang,
                        'NGAYSX'     => $ct['NGAYSX'] ?? null,
                        'HSD_LO'     => $ct['HSD_LO'],
                        'GHICHU'     => $ct['GHICHU'] ?? null,
                    ]);

                    // Tạo lô hàng
                    $loHangs[] = $this->loHangService->createLoHang([
                        'MAHDNHAP'    => $maHdNhap,
                        'MASP'        => $ct['MASP'],
                        'MACUAHANG'   => $maCuaHang,
                        'NGAYNHAP'    => now()->toDateString(),
                        'NGAYSX'      => $ct['NGAYSX'] ?? null,
                        'HSD'         => $ct['HSD_LO'],
                        'SOLUONG_NHAP' => $ct['SOLUONGTN'],
                        'DONGIA_NHAP'  => $ct['DONGIANHAP'],
                        'GHICHU'      => $ct['GHICHU'] ?? null,
                    ], $user?->USERNAME);
                }

                try {
                    \Illuminate\Support\Facades\Cache::tags(['products'])->flush();
                } catch (\Exception $e) {}

                return [
                    'MAHDNHAP'  => $maHdNhap,
                    'MALOHAN'   => $loHangs[0]->MALOHAN ?? null,
                    'hdnhap'    => $hdNhap->load('nhacungcap'),
                    'lo_hangs'  => $loHangs,
                    'tong_lo'   => count($loHangs),
                    'tongtien'  => $tongtienNhap,
                ];
            });

            return response()->json(['status' => true, 'message' => 'Nhập hàng thành công', 'data' => $result], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'error' => 'Lỗi nhập hàng: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cập nhật trạng thái lô (hủy thủ công)
     */
    public function updateTrangThai(Request $request, string $malohan)
    {
        $lo = LoHang::find($malohan);
        if (!$lo) {
            return response()->json(['error' => 'Không tìm thấy lô hàng'], 404);
        }

        $trangthai = strtolower($request->input('trangthai', $request->input('TRANGTHAI', 'bi_huy')));
        if (!in_array($trangthai, ['bi_huy', 'het_han'])) {
            $trangthai = 'bi_huy';
        }

        $lyDo = $request->input('ly_do', $request->input('LY_DO', $request->input('cancelReason', 'hong_hong')));
        if ($lyDo === 'hu_hong') $lyDo = 'hong_hong';
        if (!in_array($lyDo, ['het_han', 'hong_hong', 'thu_hoi', 'khac'])) {
            $lyDo = 'hong_hong';
        }

        $ghiChu = $request->input('ghichu', $request->input('ghi_chu', $request->input('GHICHU', '')));

        if ($lo->TRANGTHAI === 'bi_huy') {
            return response()->json(['error' => 'Lô hàng này đã được lập biên bản hủy trước đó!'], 422);
        }

        $user = Auth::guard('api')->user();

        DB::transaction(function () use ($lo, $lyDo, $ghiChu, $user) {
            $qty = $lo->SOLUONG_CON;

            $userNv = \App\Models\NhanVien::where('USERNAME', $user?->USERNAME)->first();
            $kyThuKho = $userNv?->CHU_KY;

            // 1. Tạo biên bản hủy
            $bbh = \App\Models\BienBanHuy::create([
                'MALOHAN'      => $lo->MALOHAN,
                'MACUAHANG'    => $lo->MACUAHANG,
                'USERNAME'     => $user?->USERNAME,
                'SOLUONG_HUY'  => $qty,
                'LY_DO'        => $lyDo,
                'GHICHU'       => $ghiChu,
                'NGAY_HUY'     => now(),
                'KY_THU_KHO'    => $kyThuKho,
                'TRANGTHAI_KY' => $kyThuKho ? 'da_ky_mot_phan' : 'cho_ky',
            ]);

            // 2. Cập nhật lô hàng sang trạng thái 'bi_huy'
            $lo->update([
                'TRANGTHAI'   => 'bi_huy',
                'SOLUONG_HUY' => $lo->SOLUONG_HUY + $qty,
                'SOLUONG_CON' => 0,
            ]);

            // 3. Trừ tồn kho tonkho_cuahang + ghi log inventory_movements qua Trigger
            DB::statement("SET @inv_type = 'DAMAGE', @inv_ref_type = 'BIEN_BAN_HUY', @inv_ref_id = ?, @inv_username = ?", [
                $bbh->MABBHUY ?? $lo->MALOHAN, $user?->USERNAME
            ]);

            DB::table('tonkho_cuahang')
                ->where('MACUAHANG', $lo->MACUAHANG)
                ->where('MASP', $lo->MASP)
                ->update([
                    'SOLUONG_TON' => DB::raw("GREATEST(0, SOLUONG_TON - {$qty})"),
                    'updated_at'  => now(),
                ]);

            DB::statement("SET @inv_type = NULL, @inv_ref_type = NULL, @inv_ref_id = NULL, @inv_username = NULL");

            // 4. Đồng bộ số lượng tồn kho trên bảng sanpham (Quản Lý Sản Phẩm)
            $totalStock = DB::table('tonkho_cuahang')->where('MASP', $lo->MASP)->sum('SOLUONG_TON');
            DB::table('sanpham')->where('MASP', $lo->MASP)->update(['SOLUONG' => max(0, $totalStock)]);
        });

        return response()->json([
            'status'  => true,
            'message' => 'Đã hủy lô hàng và tạo biên bản hủy thành công'
        ]);
    }

    // ─────────────────────── Helpers ────────────────────────────────────────

    private function getMaCuaHangFromUser($user): ?int
    {
        if ($user && in_array($user->MAROLE, [1, 2])) {
            $nv = NhanVien::where('USERNAME', $user->USERNAME)->first();
            return $nv?->MACUAHANG ? (int) $nv->MACUAHANG : null;
        }
        return null;
    }
}
