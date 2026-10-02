<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CuaHang;

class StoreController extends Controller
{
    public function publicIndex()
    {
        $stores = CuaHang::where('trangThai', 1)->get();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách cửa hàng thành công',
            'data' => $stores,
        ]);
    }

    public function index()
    {
        $stores = CuaHang::with(['nguoiDungs', 'loHangs.sanPham'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách quản lý cửa hàng thành công',
            'data' => $stores,
        ]);
    }

    public function show($id)
    {
        $store = CuaHang::with(['nguoiDungs', 'loHangs.sanPham', 'hoaDonNhaps'])->find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy chi nhánh',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết chi nhánh cửa hàng',
            'data' => $store,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'ten_cuahang' => $request->input('ten_cuahang') ?? $request->input('TENCUAHANG'),
            'diaChiChiTiet' => $request->input('diaChiChiTiet') ?? $request->input('DIACHI') ?? $request->input('address'),
            'soDienThoai' => $request->input('soDienThoai') ?? $request->input('SDT') ?? $request->input('phone'),
            'ghn_shop_id' => $request->input('ghn_shop_id') ?? $request->input('GHN_SHOP_ID'),
        ]);

        $validated = $request->validate([
            'ten_cuahang' => 'required|string|max:150',
            'diaChiChiTiet' => 'required|string|max:255',
            'soDienThoai' => 'nullable|string|max:20',
            'maTinh' => 'nullable|integer',
            'maHuyen' => 'nullable|integer',
            'maXa' => 'nullable|string',
            'ghn_shop_id' => 'nullable|string|max:50',
        ]);

        $store = CuaHang::create(array_merge([
            'maTinh' => 79,
            'maHuyen' => 760,
            'maXa' => '26740',
            'trangThai' => 1,
        ], $validated));

        return response()->json([
            'success' => true,
            'message' => 'Tạo chi nhánh cửa hàng mới thành công',
            'data' => $store,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $store = CuaHang::find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy chi nhánh',
            ], 404);
        }

        $request->merge([
            'ten_cuahang' => $request->input('ten_cuahang') ?? $request->input('TENCUAHANG') ?? $store->ten_cuahang,
            'diaChiChiTiet' => $request->input('diaChiChiTiet') ?? $request->input('DIACHI') ?? $store->diaChiChiTiet,
            'soDienThoai' => $request->input('soDienThoai') ?? $request->input('SDT') ?? $store->soDienThoai,
            'ghn_shop_id' => $request->input('ghn_shop_id') ?? $request->input('GHN_SHOP_ID') ?? $store->ghn_shop_id,
        ]);

        $validated = $request->validate([
            'ten_cuahang' => 'sometimes|string|max:150',
            'diaChiChiTiet' => 'sometimes|string|max:255',
            'soDienThoai' => 'nullable|string|max:20',
            'ghn_shop_id' => 'nullable|string|max:50',
            'trangThai' => 'sometimes|integer|in:0,1',
        ]);

        $store->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin chi nhánh thành công',
            'data' => $store,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $store = CuaHang::find($id);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy chi nhánh',
            ], 404);
        }

        $statusStr = $request->input('status');
        $newStatus = ($statusStr === 'Đang hoạt động' || $statusStr == 1) ? 1 : 0;

        $store->update(['trangThai' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái chi nhánh thành công',
            'data' => $store,
        ]);
    }
}
