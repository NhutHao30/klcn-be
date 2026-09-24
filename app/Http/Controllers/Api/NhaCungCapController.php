<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NhaCungCap;
use Illuminate\Http\Request;

class NhaCungCapController extends Controller
{
    public function index()
    {
        $suppliers = NhaCungCap::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách nhà cung cấp thành công',
            'data' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'ten_ncc' => $request->input('ten_ncc') ?? $request->input('TENNCC'),
            'so_dien_thoai' => $request->input('so_dien_thoai') ?? $request->input('DIENTHOAI'),
            'email' => $request->input('email') ?? $request->input('EMAIL'),
            'dia_chi' => $request->input('dia_chi') ?? $request->input('DIACHI'),
        ]);

        $validated = $request->validate([
            'ten_ncc' => 'required|string|max:150',
            'so_dien_thoai' => 'nullable|string|max:15',
            'email' => 'nullable|email|max:100',
            'dia_chi' => 'nullable|string|max:255',
        ]);

        $supplier = NhaCungCap::create(array_merge($validated, ['trang_thai' => 1]));

        return response()->json([
            'success' => true,
            'message' => 'Tạo mới nhà cung cấp thành công',
            'data' => $supplier,
        ], 201);
    }

    public function show($id)
    {
        $supplier = NhaCungCap::with(['loHangs', 'hoaDonNhaps'])->find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy nhà cung cấp',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết nhà cung cấp',
            'data' => $supplier,
        ]);
    }

    public function update(Request $request, $id)
    {
        $supplier = NhaCungCap::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy nhà cung cấp',
                'data' => null,
            ], 404);
        }

        $request->merge([
            'ten_ncc' => $request->input('ten_ncc') ?? $request->input('TENNCC'),
            'so_dien_thoai' => $request->input('so_dien_thoai') ?? $request->input('DIENTHOAI'),
            'email' => $request->input('email') ?? $request->input('EMAIL'),
            'dia_chi' => $request->input('dia_chi') ?? $request->input('DIACHI'),
        ]);

        $validated = $request->validate([
            'ten_ncc' => 'sometimes|string|max:150',
            'so_dien_thoai' => 'nullable|string|max:15',
            'email' => 'nullable|email|max:100',
            'dia_chi' => 'nullable|string|max:255',
            'trang_thai' => 'sometimes|integer|in:0,1',
        ]);

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin nhà cung cấp thành công',
            'data' => $supplier,
        ]);
    }

    public function destroy($id)
    {
        $supplier = NhaCungCap::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy nhà cung cấp',
            ], 404);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa nhà cung cấp thành công',
        ]);
    }
}
