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
                $q->where('tenSanPham', 'like', '%' . $keyword . '%')
                  ->orWhere('id', 'like', '%' . $keyword . '%');
            });
        }

        // Category filter
        $category = $request->input('category') ?? $request->input('loaiId') ?? $request->input('MALOAI');
        if (!empty($category)) {
            if (is_numeric($category)) {
                $query->where('loaiId', (int)$category);
            } else {
                $map = [
                    'LSP001' => 1, // Bánh Kem
                    'LSP002' => 2, // Bánh Ngọt
                    'LSP003' => 3, // Bánh Mì
                    'LSP004' => 4, // Bánh Khô
                    'LSP005' => 5, // Bánh Tráng Miệng
                    'LSP006' => 6, // Bánh Theo Mùa
                    'LSP007' => 6,
                ];
                if (isset($map[$category])) {
                    $query->where('loaiId', $map[$category]);
                } else {
                    $query->whereHas('loaiSanPham', function($q2) use ($category) {
                        $q2->where('tenLoai', 'like', '%' . $category . '%');
                    });
                }
            }
        }

        // Status filter
        $status = $request->input('status') ?? $request->input('trangThai');
        if ($status !== null && $status !== '') {
            if ($status === 'ngung_ban' || $status == 0) {
                $query->where('trangThai', 0);
            } else if ($status === 'all') {
                // Không filter trang_thai
            } else {
                $query->where('trangThai', 1);
            }
        } else {
            $query->where('trangThai', 1);
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
        $query = SanPham::with(['loaiSanPham', 'tonKhoChiNhanhs.cuahang', 'loai', 'tonkho.cuahang']);
        $product = is_numeric($id) ? $query->find($id) : $query->where('slug', $id)->first();

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
            'tenSanPham' => $request->input('tenSanPham') ?? $request->input('TENSP') ?? $request->input('TenSP') ?? $request->input('tenSP'),
            'loaiId' => $request->input('loaiId') ?? $request->input('MALOAI') ?? $request->input('LOAISP') ?? $request->input('loaiSP'),
            'giaBan' => $request->input('giaBan') ?? $request->input('GIABAN') ?? $request->input('giaBan'),
            'donViTinh' => $request->input('donViTinh') ?? $request->input('DVT') ?? $request->input('donViTinh') ?? 'Cái',
            'moTa' => $request->input('moTa') ?? $request->input('GHICHU') ?? $request->input('moTa') ?? $request->input('GhiChu'),
        ]);

        $validated = $request->validate([
            'tenSanPham' => 'required|string|max:150',
            'loaiId' => 'required|exists:loaiSanPham,id',
            'giaBan' => 'required|numeric|min:0',
            'donViTinh' => 'required|string',
            'moTa' => 'nullable|string',
            'hinhAnh' => 'nullable|string',
        ]);

        if ($request->hasFile('HINHANH') || $request->hasFile('hinhAnh') || $request->hasFile('hinh_anh_file')) {
            $file = $request->file('HINHANH') ?? $request->file('hinhAnh') ?? $request->file('hinh_anh_file');
            try {
                $hinhAnhPath = \Illuminate\Support\Facades\Storage::disk('s3')->putFile('products', $file);
            } catch (\Exception $e) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('assets/IMG'), $filename);
                $hinhAnhPath = '/assets/IMG/' . $filename;
            }
        } else {
            $hinhAnhPath = $request->input('hinhAnh') ?? $request->input('HINHANH') ?? '/assets/IMG/productnew2.webp';
        }

        $slug = \Illuminate\Support\Str::slug($validated['tenSanPham']) . '-' . time();

        $product = SanPham::create([
            'tenSanPham' => $validated['tenSanPham'],
            'slug' => $slug,
            'loaiId' => $validated['loaiId'],
            'giaBan' => $validated['giaBan'],
            'donViTinh' => $validated['donViTinh'],
            'moTa' => $validated['moTa'] ?? '',
            'hinhAnh' => $hinhAnhPath,
            'trangThai' => 1,
        ]);

        // Ghi nhận lịch sử giá ban đầu
        LichSuGiaSanPham::create([
            'sanPhamId' => $product->id,
            'gia_cu' => null,
            'gia_moi' => $validated['giaBan'],
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
            'tenSanPham' => $request->input('tenSanPham') ?? $request->input('TENSP') ?? $request->input('TenSP') ?? $product->tenSanPham,
            'loaiId' => $request->input('loaiId') ?? $request->input('MALOAI') ?? $request->input('LOAISP') ?? $product->loaiId,
            'giaBan' => $request->input('giaBan') ?? $request->input('GIABAN') ?? $product->giaBan,
            'donViTinh' => $request->input('donViTinh') ?? $request->input('DVT') ?? $product->donViTinh,
            'moTa' => $request->input('moTa') ?? $request->input('GHICHU') ?? $product->moTa,
        ]);

        $validated = $request->validate([
            'tenSanPham' => 'sometimes|required|string|max:150',
            'loaiId' => 'sometimes|required|exists:loaiSanPham,id',
            'giaBan' => 'sometimes|required|numeric|min:0',
            'donViTinh' => 'sometimes|required|string',
            'moTa' => 'nullable|string',
            'hinhAnh' => 'nullable|string',
        ]);

        if ($request->hasFile('HINHANH') || $request->hasFile('hinhAnh') || $request->hasFile('hinh_anh_file')) {
            $file = $request->file('HINHANH') ?? $request->file('hinhAnh') ?? $request->file('hinh_anh_file');
            try {
                $validated['hinhAnh'] = \Illuminate\Support\Facades\Storage::disk('s3')->putFile('products', $file);
            } catch (\Exception $e) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('assets/IMG'), $filename);
                $validated['hinhAnh'] = '/assets/IMG/' . $filename;
            }
        }

        $oldPrice = $product->giaBan;
        $product->update($validated);

        if (isset($validated['giaBan']) && $validated['giaBan'] != $oldPrice) {
            $discountPct = $oldPrice > 0 ? (($oldPrice - $validated['giaBan']) / $oldPrice) * 100 : 0;
            LichSuGiaSanPham::create([
                'sanPhamId' => $product->id,
                'gia_cu' => $oldPrice,
                'gia_moi' => $validated['giaBan'],
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

        $product->update(['trangThai' => 0]);
        return response()->json(['message' => 'Đã ngừng kinh doanh sản phẩm']);
    }

    /**
     * Get price history
     */
    public function getPriceHistory($id)
    {
        $history = LichSuGiaSanPham::where('sanPhamId', $id)->orderBy('createdAt', 'desc')->get();
        return response()->json($history);
    }
}
