<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\BienBanHuy;
use App\Models\NhanVien;

class BienBanHuyController extends Controller
{
    /**
     * Danh sách biên bản hủy (có filter)
     *
     * Query params: macuahang, ly_do, ngay_from, ngay_to, search (malohan/tensp)
     */
    public function index(Request $request)
    {
        $user = Auth::guard('api')->user();
        $maCuaHang = $this->getMaCuaHangFromUser($user);

        $query = BienBanHuy::with([
            'loHang.sanpham:MASP,TENSP,HINHANH',
            'cuahang:MACUAHANG,TENCUAHANG',
            'taikhoan:USERNAME,EMAIL',
        ]);

        // Filter cửa hàng
        if ($maCuaHang) {
            $query->where('MACUAHANG', $maCuaHang);
        } elseif ($request->filled('macuahang')) {
            $query->where('MACUAHANG', $request->macuahang);
        }

        // Filter lý do hủy
        if ($request->filled('ly_do')) {
            $query->where('LY_DO', $request->ly_do);
        }

        // Filter thời gian
        if ($request->filled('ngay_from')) {
            $query->where('NGAY_HUY', '>=', $request->ngay_from);
        }
        if ($request->filled('ngay_to')) {
            $query->where('NGAY_HUY', '<=', $request->ngay_to . ' 23:59:59');
        }

        // Tìm kiếm theo mã lô
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('MALOHAN', 'like', "%{$search}%")
                  ->orWhereHas('loHang.sanpham', fn($q2) => $q2->where('TENSP', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $results = $query->orderByDesc('NGAY_HUY')->paginate($perPage);

        // Append label tiếng Việt
        $results->getCollection()->transform(fn($bbh) => $bbh->append('ly_do_label'));

        return response()->json(['status' => true, 'data' => $results]);
    }

    /**
     * Chi tiết biên bản hủy
     */
    public function show(int $id)
    {
        $bbh = BienBanHuy::with([
            'loHang.sanpham',
            'loHang.hdnhap.nhacungcap',
            'cuahang',
            'taikhoan',
        ])->find($id);

        if (!$bbh) {
            return response()->json(['error' => 'Không tìm thấy biên bản hủy'], 404);
        }

        return response()->json(['status' => true, 'data' => $bbh->append('ly_do_label')]);
    }

    /**
     * Tổng hợp thống kê hủy hàng
     *
     * Trả về:
     *  - Tổng SL hủy theo tháng
     *  - Hủy theo lý do
     *  - Top sản phẩm bị hủy nhiều nhất
     *  - Tổng giá trị hủy (SL * đơn giá nhập)
     */
    public function thongKe(Request $request)
    {
        $user = Auth::guard('api')->user();
        $maCuaHang = $this->getMaCuaHangFromUser($user);

        if (!$maCuaHang && $request->filled('macuahang')) {
            $maCuaHang = (int) $request->macuahang;
        }

        $thang = $request->input('thang', now()->month);
        $nam   = $request->input('nam', now()->year);

        $baseQuery = BienBanHuy::with('loHang')
            ->whereMonth('NGAY_HUY', $thang)
            ->whereYear('NGAY_HUY', $nam);

        if ($maCuaHang) {
            $baseQuery->where('MACUAHANG', $maCuaHang);
        }

        $records = $baseQuery->get();

        // Tổng số lượng và giá trị hủy
        $tongSoLuongHuy = $records->sum('SOLUONG_HUY');
        $tongGiaTriHuy  = $records->sum(fn($bbh) => $bbh->SOLUONG_HUY * ($bbh->loHang?->DONGIA_NHAP ?? 0));

        // Phân loại theo lý do
        $theoLyDo = $records->groupBy('LY_DO')->map(fn($g) => [
            'so_bien_ban' => $g->count(),
            'so_luong'    => $g->sum('SOLUONG_HUY'),
            'gia_tri'     => $g->sum(fn($bbh) => $bbh->SOLUONG_HUY * ($bbh->loHang?->DONGIA_NHAP ?? 0)),
        ]);

        // Top SP bị hủy nhiều nhất (qua lô)
        $topSanPham = BienBanHuy::join('lo_hang', 'bien_ban_huy.MALOHAN', '=', 'lo_hang.MALOHAN')
            ->join('sanpham', 'lo_hang.MASP', '=', 'sanpham.MASP')
            ->whereMonth('bien_ban_huy.NGAY_HUY', $thang)
            ->whereYear('bien_ban_huy.NGAY_HUY', $nam)
            ->when($maCuaHang, fn($q) => $q->where('bien_ban_huy.MACUAHANG', $maCuaHang))
            ->selectRaw('sanpham.MASP, sanpham.TENSP, SUM(bien_ban_huy.SOLUONG_HUY) as tong_huy,
                         SUM(bien_ban_huy.SOLUONG_HUY * lo_hang.DONGIA_NHAP) as gia_tri_huy')
            ->groupBy('sanpham.MASP', 'sanpham.TENSP')
            ->orderByDesc('tong_huy')
            ->limit(10)
            ->get();

        return response()->json([
            'thang'           => (int) $thang,
            'nam'             => (int) $nam,
            'tong_so_luong'   => $tongSoLuongHuy,
            'tong_gia_tri'    => round($tongGiaTriHuy, 2),
            'theo_ly_do'      => $theoLyDo,
            'top_san_pham'    => $topSanPham,
        ]);
    }

    private function getMaCuaHangFromUser($user): ?int
    {
        if ($user && in_array($user->MAROLE, [1, 2])) {
            $nv = NhanVien::where('USERNAME', $user->USERNAME)->first();
            return $nv?->MACUAHANG ? (int) $nv->MACUAHANG : null;
        }
        return null;
    }
}
