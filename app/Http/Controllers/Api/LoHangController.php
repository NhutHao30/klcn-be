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
        $query = LoHang::with(['sanPham', 'cuaHang', 'nhaCungCap']);

        if ($request->has('cuahang_id')) {
            $query->where('cuahang_id', $request->cuahang_id);
        }

        if ($request->has('sanpham_id')) {
            $query->where('sanpham_id', $request->sanpham_id);
        }

        if ($request->has('trang_thai')) {
            $query->where('trang_thai', $request->trang_thai);
        }

        $batches = $query->latest()->paginate($request->get('per_page', 15));

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
            ->where('so_luong_con', '>', 0)
            ->where('trang_thai', '!=', 3) // Chưa hủy
            ->where('hsd', '<', $today)
            ->get();

        // 2. Sắp hết hạn (Near expiry)
        $nearExpiry = LoHang::with(['sanPham', 'cuaHang', 'nhaCungCap'])
            ->where('so_luong_con', '>', 0)
            ->where('trang_thai', '!=', 3)
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
        $validated = $request->validate([
            'ma_lo_hang' => 'required|string|max:50|unique:lo_hang,ma_lo_hang',
            'sanpham_id' => 'required|exists:san_pham,id',
            'cuahang_id' => 'required|exists:cuahang,id',
            'nhacungcap_id' => 'nullable|exists:nha_cung_cap,id',
            'so_luong_nhap' => 'required|integer|min:1',
            'ngay_san_xuat' => 'nullable|date',
            'hsd' => 'required|date',
            'gia_nhap' => 'nullable|numeric|min:0',
        ]);

        $batch = LoHang::create(array_merge($validated, [
            'so_luong_con' => $validated['so_luong_nhap'],
            'trang_thai' => 0, // Con_hang
        ]));

        $tonKho = \App\Models\TonKhoChiNhanh::firstOrCreate(
            ['cuahang_id' => $validated['cuahang_id'], 'sanpham_id' => $validated['sanpham_id']],
            ['so_luong_ton' => 0]
        );
        $tonKho->increment('so_luong_ton', $validated['so_luong_nhap']);

        \App\Models\NhatKyKho::create([
            'cuahang_id' => $validated['cuahang_id'],
            'sanpham_id' => $validated['sanpham_id'],
            'loai_bien_dong' => 4,
            'so_luong_thay_doi' => $validated['so_luong_nhap'],
            'so_luong_ton_sau' => $tonKho->so_luong_ton,
            'ma_tham_chieu' => $validated['ma_lo_hang'],
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
}
