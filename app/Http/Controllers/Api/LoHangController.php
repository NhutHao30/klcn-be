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

        if ($request->has('cuaHangId')) {
            $query->where('cuaHangId', $request->cuaHangId);
        }

        if ($request->has('sanPhamId')) {
            $query->where('sanPhamId', $request->sanPhamId);
        }

        if ($request->has('trangThai')) {
            $query->where('trangThai', $request->trangThai);
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
            'loai_bien_dong' => 4,
            'so_luong_thay_doi' => $validated['soLuongNhap'],
            'so_luong_ton_sau' => $tonKho->soLuong_ton,
            'ma_tham_chieu' => $validated['maLoHang'],
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
