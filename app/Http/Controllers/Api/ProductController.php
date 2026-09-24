<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SanPham;
use App\Models\LichSuGiaSanPham;
use App\Models\TonKhoChiNhanh;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * View product list
     */
    public function index(Request $request)
    {
        $query = SanPham::with(['loaiSanPham', 'tonKhoChiNhanhs']);

        // Keyword search
        $keyword = $request->input('keyword') ?? $request->input('search');
        if (!empty($keyword)) {
            $query->where(function($q) use ($keyword) {
                $q->where('ten_san_pham', 'like', '%' . $keyword . '%')
                  ->orWhere('id', 'like', '%' . $keyword . '%');
            });
        }

        // Category filter
        $category = $request->input('category') ?? $request->input('loai_id') ?? $request->input('MALOAI');
        if (!empty($category)) {
            $query->where('loai_id', $category);
        }

        // Status filter
        $status = $request->input('status') ?? $request->input('trang_thai');
        if ($status !== null && $status !== '') {
            if ($status === 'ngung_ban' || $status == 0) {
                $query->where('trang_thai', 0);
            } else if ($status === 'all') {
                // Không filter trang_thai
            } else {
                $query->where('trang_thai', 1);
            }
        } else {
            $query->where('trang_thai', 1);
        }

        $perPage = (int) ($request->input('size') ?? $request->input('per_page') ?? 10);
        $products = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($products);
    }

    /**
     * View single product
     */
    public function show($id)
    {
        $product = SanPham::with(['loai', 'tonkho.cuahang'])->find($id);
        if (!$product) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        return response()->json($product);
    }

    /**
     * Admin: Add new product
     */
    public function store(Request $request)
    {
        $request->merge([
            'ten_san_pham' => $request->input('ten_san_pham') ?? $request->input('TENSP') ?? $request->input('TenSP') ?? $request->input('tenSP'),
            'loai_id' => $request->input('loai_id') ?? $request->input('MALOAI') ?? $request->input('LOAISP') ?? $request->input('loaiSP'),
            'gia_ban' => $request->input('gia_ban') ?? $request->input('GIABAN') ?? $request->input('giaBan'),
            'don_vi_tinh' => $request->input('don_vi_tinh') ?? $request->input('DVT') ?? $request->input('donViTinh') ?? 'Cái',
            'mo_ta' => $request->input('mo_ta') ?? $request->input('GHICHU') ?? $request->input('moTa') ?? $request->input('GhiChu'),
        ]);

        $validated = $request->validate([
            'ten_san_pham' => 'required|string|max:150',
            'loai_id' => 'required|exists:loai_san_pham,id',
            'gia_ban' => 'required|numeric|min:0',
            'don_vi_tinh' => 'required|string',
            'mo_ta' => 'nullable|string',
            'hinh_anh' => 'nullable|string',
        ]);

        if ($request->hasFile('HINHANH') || $request->hasFile('hinh_anh')) {
            $file = $request->file('HINHANH') ?? $request->file('hinh_anh');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('assets/IMG'), $filename);
            $hinhAnhPath = '/assets/IMG/' . $filename;
        } else {
            $hinhAnhPath = $request->input('hinh_anh') ?? $request->input('HINHANH') ?? '/assets/IMG/productnew2.webp';
        }

        $slug = \Illuminate\Support\Str::slug($validated['ten_san_pham']) . '-' . time();

        $product = SanPham::create([
            'ten_san_pham' => $validated['ten_san_pham'],
            'slug' => $slug,
            'loai_id' => $validated['loai_id'],
            'gia_ban' => $validated['gia_ban'],
            'don_vi_tinh' => $validated['don_vi_tinh'],
            'mo_ta' => $validated['mo_ta'] ?? '',
            'hinh_anh' => $hinhAnhPath,
            'trang_thai' => 1,
        ]);

        // Ghi nhận lịch sử giá ban đầu
        LichSuGiaSanPham::create([
            'sanpham_id' => $product->id,
            'gia_cu' => null,
            'gia_moi' => $validated['gia_ban'],
            'phan_tram_giam' => 0,
            'ly_do' => 'Khởi tạo sản phẩm mới',
            'ngay_ap_dung' => now(),
        ]);

        return response()->json(['message' => 'Thêm sản phẩm thành công', 'data' => $product], 201);
    }

    /**
     * Admin: Update product
     */
    public function update(Request $request, $id)
    {
        $product = SanPham::find($id);
        if (!$product) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        $request->merge([
            'ten_san_pham' => $request->input('ten_san_pham') ?? $request->input('TENSP') ?? $request->input('TenSP') ?? $product->ten_san_pham,
            'loai_id' => $request->input('loai_id') ?? $request->input('MALOAI') ?? $request->input('LOAISP') ?? $product->loai_id,
            'gia_ban' => $request->input('gia_ban') ?? $request->input('GIABAN') ?? $product->gia_ban,
            'don_vi_tinh' => $request->input('don_vi_tinh') ?? $request->input('DVT') ?? $product->don_vi_tinh,
            'mo_ta' => $request->input('mo_ta') ?? $request->input('GHICHU') ?? $product->mo_ta,
        ]);

        $validated = $request->validate([
            'ten_san_pham' => 'sometimes|required|string|max:150',
            'loai_id' => 'sometimes|required|exists:loai_san_pham,id',
            'gia_ban' => 'sometimes|required|numeric|min:0',
            'don_vi_tinh' => 'sometimes|required|string',
            'mo_ta' => 'nullable|string',
            'hinh_anh' => 'nullable|string',
        ]);

        if ($request->hasFile('HINHANH') || $request->hasFile('hinh_anh')) {
            $file = $request->file('HINHANH') ?? $request->file('hinh_anh');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('assets/IMG'), $filename);
            $validated['hinh_anh'] = '/assets/IMG/' . $filename;
        }

        $oldPrice = $product->gia_ban;
        $product->update($validated);

        if (isset($validated['gia_ban']) && $validated['gia_ban'] != $oldPrice) {
            $discountPct = $oldPrice > 0 ? (($oldPrice - $validated['gia_ban']) / $oldPrice) * 100 : 0;
            LichSuGiaSanPham::create([
                'sanpham_id' => $product->id,
                'gia_cu' => $oldPrice,
                'gia_moi' => $validated['gia_ban'],
                'phan_tram_giam' => max(0, round($discountPct, 2)),
                'ly_do' => $request->ly_do_doi_gia ?? 'Điều chỉnh giá bán',
                'ngay_ap_dung' => now(),
            ]);
        }

        return response()->json(['message' => 'Cập nhật sản phẩm thành công', 'data' => $product]);
    }

    /**
     * Admin: Disable product
     */
    public function destroy($id)
    {
        $product = SanPham::find($id);
        if (!$product) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        $product->update(['trang_thai' => 0]);
        return response()->json(['message' => 'Đã ngừng kinh doanh sản phẩm']);
    }

    /**
     * Get price history
     */
    public function getPriceHistory($id)
    {
        $history = LichSuGiaSanPham::where('sanpham_id', $id)->orderBy('created_at', 'desc')->get();
        return response()->json($history);
    }
}
