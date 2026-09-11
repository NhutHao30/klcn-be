<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NhaCungCap;
use Illuminate\Support\Facades\Cache;

class NhaCungCapController extends Controller
{
    /**
     * Danh sách nhà cung cấp (có tìm kiếm, lọc trạng thái)
     */
    public function index(Request $request)
    {
        $query = NhaCungCap::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('TENNCC', 'like', "%{$search}%")
                  ->orWhere('MANCC', 'like', "%{$search}%")
                  ->orWhere('DIENTHOAI', 'like', "%{$search}%");
            });
        }

        if ($trangthai = $request->input('trangthai')) {
            $query->where('TRANGTHAI', $trangthai);
        }

        $list = $query->orderBy('TENNCC')->get();

        return response()->json([
            'status' => true,
            'data'   => $list
        ]);
    }

    /**
     * Chi tiết 1 nhà cung cấp (kèm lịch sử nhập hàng)
     */
    public function show(string $id)
    {
        $ncc = NhaCungCap::with(['hdnhaps' => fn($q) => $q->latest()->limit(20)])
                         ->find($id);

        if (!$ncc) {
            return response()->json(['status' => false, 'error' => 'Không tìm thấy nhà cung cấp'], 404);
        }

        return response()->json(['status' => true, 'data' => $ncc]);
    }

    /**
     * Thêm nhà cung cấp mới
     */
    public function store(Request $request)
    {
        // Tự động sinh mã MANCC nếu người dùng không nhập
        if (!$request->filled('MANCC')) {
            $count = NhaCungCap::count() + 1;
            $nextCode = 'NCC' . str_pad($count, 5, '0', STR_PAD_LEFT);
            while (NhaCungCap::where('MANCC', $nextCode)->exists()) {
                $count++;
                $nextCode = 'NCC' . str_pad($count, 5, '0', STR_PAD_LEFT);
            }
            $request->merge(['MANCC' => $nextCode]);
        }

        $validated = $request->validate([
            'MANCC'         => 'required|string|max:10|unique:nha_cung_cap,MANCC',
            'TENNCC'        => 'required|string|max:150',
            'DIENTHOAI'     => 'nullable|string|max:20',
            'EMAIL'         => 'nullable|email|max:100',
            'DIACHI'        => 'nullable|string|max:255',
            'NGUOIDAIDIEN'  => 'nullable|string|max:100',
            'MASOTHUE'      => 'nullable|string|max:20',
            'GHICHU'        => 'nullable|string',
        ]);

        $ncc = NhaCungCap::create($validated);

        return response()->json([
            'status'  => true,
            'message' => 'Thêm nhà cung cấp thành công',
            'data'    => $ncc
        ], 201);
    }

    /**
     * Cập nhật nhà cung cấp
     */
    public function update(Request $request, string $id)
    {
        $ncc = NhaCungCap::find($id);
        if (!$ncc) {
            return response()->json(['error' => 'Không tìm thấy nhà cung cấp'], 404);
        }

        $request->validate([
            'TENNCC'       => 'required|string|max:150',
            'DIENTHOAI'    => 'nullable|string|max:20',
            'EMAIL'        => 'nullable|email|max:100',
            'DIACHI'       => 'nullable|string|max:255',
            'NGUOIDAIDIEN' => 'nullable|string|max:100',
            'MASOTHUE'     => 'nullable|string|max:20',
            'TRANGTHAI'    => 'nullable|in:Đang hợp tác,Ngừng hợp tác',
            'GHICHU'       => 'nullable|string',
        ]);

        $ncc->update($request->only([
            'TENNCC', 'DIENTHOAI', 'EMAIL', 'DIACHI',
            'NGUOIDAIDIEN', 'MASOTHUE', 'TRANGTHAI', 'GHICHU',
        ]));

        return response()->json(['message' => 'Cập nhật thành công', 'data' => $ncc]);
    }

    /**
     * Xóa nhà cung cấp (chỉ khi chưa có phiếu nhập)
     */
    public function destroy(string $id)
    {
        $ncc = NhaCungCap::find($id);
        if (!$ncc) {
            return response()->json(['error' => 'Không tìm thấy nhà cung cấp'], 404);
        }

        if ($ncc->hdnhaps()->exists()) {
            return response()->json([
                'error' => 'Không thể xóa nhà cung cấp đã có lịch sử nhập hàng. Hãy đổi trạng thái thành "Ngừng hợp tác".'
            ], 422);
        }

        $ncc->delete();
        return response()->json(['message' => 'Đã xóa nhà cung cấp']);
    }
}
