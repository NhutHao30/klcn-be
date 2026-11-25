<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoaiSanPham;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(LoaiSanPham::where('trangThai', 1)->withCount('sanPhams')->get());
    }

    public function store(Request $request)
    {
        $request->merge([
            'tenLoai' => $request->input('tenLoai') ?? $request->input('TENLOAI') ?? $request->input('ten_loai') ?? $request->input('name'),
            'moTa' => $request->input('moTa') ?? $request->input('MO_TA') ?? $request->input('mo_ta') ?? $request->input('description'),
        ]);

        $validated = $request->validate([
            'tenLoai' => 'required|string|max:100',
            'moTa' => 'nullable|string',
            'hinhAnh' => 'nullable|string',
        ]);

        $category = LoaiSanPham::create([
            'tenLoai' => $validated['tenLoai'],
            'slug' => Str::slug($validated['tenLoai']) . '-' . time(),
            'moTa' => $validated['moTa'] ?? null,
            'hinhAnh' => $validated['hinhAnh'] ?? null,
            'trangThai' => 1,
        ]);

        return response()->json(['message' => 'Thêm danh mục thành công', 'data' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $category = LoaiSanPham::find($id);
        if (!$category) {
            return response()->json(['error' => 'Không tìm thấy danh mục'], 404);
        }

        $request->merge([
            'tenLoai' => $request->input('tenLoai') ?? $request->input('TENLOAI') ?? $request->input('ten_loai') ?? $request->input('name') ?? $category->tenLoai,
            'moTa' => $request->input('moTa') ?? $request->input('MO_TA') ?? $request->input('mo_ta') ?? $request->input('description') ?? $category->moTa,
        ]);

        $validated = $request->validate([
            'tenLoai' => 'sometimes|required|string|max:100',
            'moTa' => 'nullable|string',
            'hinhAnh' => 'nullable|string',
        ]);

        $category->update([
            'tenLoai' => $validated['tenLoai'],
            'slug' => Str::slug($validated['tenLoai']),
            'moTa' => $validated['moTa'] ?? $category->moTa,
            'hinhAnh' => $validated['hinhAnh'] ?? $category->hinhAnh,
        ]);

        return response()->json(['message' => 'Cập nhật danh mục thành công', 'data' => $category]);
    }

    public function destroy($id)
    {
        $category = LoaiSanPham::find($id);
        if (!$category) {
            return response()->json(['error' => 'Không tìm thấy danh mục'], 404);
        }

        if ($category->sanPhams()->count() > 0) {
            return response()->json(['error' => 'Không thể xóa danh mục đang chứa sản phẩm'], 400);
        }

        $category->delete();
        return response()->json(['message' => 'Xóa danh mục thành công']);
    }
}
