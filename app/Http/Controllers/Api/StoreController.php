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

        $importedBatchesCount = \App\Models\HoaDonNhap::where('cuaHangId', $id)->count();
        $canceledBatchesCount = \App\Models\BienBanHuy::where('cuaHangId', $id)->count();

        $data = $store->toArray();
        $data['importedBatchesCount'] = $importedBatchesCount;
        $data['canceledBatchesCount'] = $canceledBatchesCount;

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết chi nhánh cửa hàng',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $tenCuaHang = $request->input('tenCuaHang') ?? $request->input('ten_cuahang') ?? $request->input('TENCUAHANG') ?? $request->input('name');
        $diaChi = $request->input('diaChiChiTiet') ?? $request->input('DIACHI') ?? $request->input('address');
        $sdt = $request->input('soDienThoai') ?? $request->input('SDT') ?? $request->input('phone');
        $ghnShopId = $request->input('ghnShopId') ?? $request->input('ghn_shop_id') ?? $request->input('GHN_SHOP_ID');

        if (!$tenCuaHang || !trim($tenCuaHang)) {
            return response()->json(['success' => false, 'message' => 'Vui lòng nhập tên chi nhánh!'], 422);
        }

        if (!$diaChi || !trim($diaChi)) {
            return response()->json(['success' => false, 'message' => 'Vui lòng nhập địa chỉ chi nhánh!'], 422);
        }

        $store = CuaHang::create([
            'tenCuaHang' => trim($tenCuaHang),
            'diaChiChiTiet' => trim($diaChi),
            'soDienThoai' => $sdt ? trim($sdt) : null,
            'ghnShopId' => ($ghnShopId !== null && trim($ghnShopId) !== '') ? (int)$ghnShopId : null,
            'maTinh' => 79,
            'maHuyen' => 760,
            'maXa' => '26740',
            'trangThai' => 1,
        ]);

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

        $updateData = [];

        $nameVal = $request->input('tenCuaHang') ?? $request->input('ten_cuahang') ?? $request->input('TENCUAHANG') ?? $request->input('name');
        if ($nameVal !== null && trim($nameVal) !== '') {
            $updateData['tenCuaHang'] = trim($nameVal);
        }

        $addressVal = $request->input('diaChiChiTiet') ?? $request->input('DIACHI') ?? $request->input('address');
        if ($addressVal !== null && trim($addressVal) !== '') {
            $updateData['diaChiChiTiet'] = trim($addressVal);
        }

        $phoneVal = $request->input('soDienThoai') ?? $request->input('SDT') ?? $request->input('phone');
        if ($phoneVal !== null) {
            $updateData['soDienThoai'] = trim($phoneVal);
        }

        $ghnVal = $request->input('ghnShopId') ?? $request->input('ghn_shop_id') ?? $request->input('GHN_SHOP_ID');
        if ($ghnVal !== null) {
            $updateData['ghnShopId'] = (trim($ghnVal) !== '') ? (int)$ghnVal : null;
        }

        $stVal = $request->input('trangThai') ?? $request->input('status');
        if ($stVal !== null) {
            $updateData['trangThai'] = ($stVal === 'Đang hoạt động' || (int)$stVal === 1) ? 1 : 0;
        }

        if (!empty($updateData)) {
            $store->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin chi nhánh thành công',
            'data' => $store->fresh(),
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

        $statusInput = $request->input('status') ?? $request->input('trangThai');
        if ($statusInput !== null) {
            $newStatus = ($statusInput === 'Đang hoạt động' || (int)$statusInput === 1) ? 1 : 0;
        } else {
            $newStatus = $store->trangThai == 1 ? 0 : 1;
        }

        $store->update(['trangThai' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật trạng thái chi nhánh thành công',
            'data' => $store->fresh(),
        ]);
    }
}
