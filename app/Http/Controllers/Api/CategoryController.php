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
        return response()->json(LoaiSanPham::where('trangThai', 1)->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'tenLoai' => 'required|string|max:100',
        ]);

        $category = LoaiSanPham::create([
            'tenLoai' => $request->tenLoai,
            'slug' => Str::slug($request->tenLoai) . '-' . time(),
            'hinhAnh' => $request->hinhAnh,
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

        $request->validate([
            'tenLoai' => 'required|string|max:100',
        ]);

        $category->update([
            'tenLoai' => $request->tenLoai,
            'slug' => Str::slug($request->tenLoai),
            'hinhAnh' => $request->hinhAnh ?? $category->hinhAnh,
        ]);

        return response()->json(['message' => 'Cập nhật danh mục thành công', 'data' => $category]);
    }

    public function destroy($id)
    {
        $category = LoaiSanPham::find($id);
        if (!$category) {
            return response()->json(['error' => 'Không tìm thấy danh mục'], 404);
        }

        if ($category->sanpham()->count() > 0) {
            return response()->json(['error' => 'Không thể xóa danh mục đang chứa sản phẩm'], 400);
        }

        $category->delete();
        return response()->json(['message' => 'Xóa danh mục thành công']);
    }
}
