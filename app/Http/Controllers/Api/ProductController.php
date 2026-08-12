<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SanPham;
use App\Services\UploadService;

class ProductController extends Controller
{
    protected $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Customers & Admins: View product list (Pagination, Filter price, Search name)
     */
    public function index(Request $request)
    {
        $query = SanPham::with('loaisanpham');

        // Search by product name (Supports both 'search' and 'keyword')
        $search = $request->input('search', $request->input('keyword'));
        if ($search) {
            $query->where('TENSP', 'like', '%' . $search . '%');
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('GIABAN', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('GIABAN', '<=', $request->max_price);
        }

        // Filter by category code (Supports both 'maloai' or 'category')
        $category = $request->input('maloai', $request->input('category'));
        if ($category) {
            $query->where('MALOAI', $category);
        }

        // Supports sorting by name or price (sortBy and direction)
        if ($request->has('sortBy')) {
            $sortBy = $request->input('sortBy');
            $direction = $request->input('direction', 'asc');

            // Map the correct column names in Laravel.
            if (strtolower($sortBy) == 'tensp') {
                $sortBy = 'TENSP';
            }
            if (strtolower($sortBy) == 'giaban') {
                $sortBy = 'GIABAN';
            }

            $query->orderBy($sortBy, $direction);
        } else {
            // Default sorting is latest.
            $query->orderBy('MASP', 'desc');
        }

        // Lấy cửa hàng của nhân viên/quản lý đang đăng nhập
        $maCuaHang = null;
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();

        // Admin/Nhân viên sẽ được xem cả sản phẩm đã ngừng bán (Soft Deleted)
        if ($user && in_array($user->MAROLE, [0, 1, 2])) {
            $query->withTrashed();
        }
        if ($user && in_array($user->MAROLE, [0, 1, 2])) {
            $nhanVien = \App\Models\NhanVien::where('USERNAME', $user->USERNAME)->first();
            if ($nhanVien && $nhanVien->MACUAHANG) {
                $maCuaHang = $nhanVien->MACUAHANG;
            }
        }

        // Caching: Tạo cache key dựa trên tất cả các tham số query và mã cửa hàng
        $cacheKey = 'products_v2_' . md5(serialize($request->all())) . '_store_' . $maCuaHang;

        $products = \Illuminate\Support\Facades\Cache::tags(['products'])->remember($cacheKey, 600, function () use ($query, $maCuaHang) {
            // Paginate 12 products per page.
            $paginated = $query->paginate(12);

            // Calculate the actual inventory for each returned product.
            $paginated->getCollection()->transform(function ($product) use ($maCuaHang) {
                $product->setAttribute('TONKHO_THUCTE', $this->calculateStock($product->MASP, $maCuaHang));

                if (!$maCuaHang) {
                    $chiTiet = \App\Models\TonKhoCuaHang::with('cuahang')
                        ->whereHas('cuahang', function ($q) {
                            $q->where('TRANGTHAI', 'Đang hoạt động');
                        })
                        ->where('MASP', $product->MASP)
                        ->get()
                        ->map(function ($tk) {
                            return [
                                'MACUAHANG' => $tk->MACUAHANG,
                                'TENCUAHANG' => $tk->cuahang ? $tk->cuahang->TENCUAHANG : 'Chi nhánh ' . $tk->MACUAHANG,
                                'SOLUONG_TON' => $tk->SOLUONG_TON
                            ];
                        })->toArray();
                    $product->setAttribute('TONKHO_CHITIET', json_encode($chiTiet));
                }
                return $product;
            });

            // Quan trọng: Phải chuyển thành Array trước khi lưu vào Redis để tránh lỗi Serialize Object của PHP
            return $paginated->toArray();
        });

        return response()->json($products);
    }

    /**
     * Customer: View product details
     */
    public function show($id)
    {
        // Add 'binhluans' if the comment/review table has a link.
        $product = SanPham::with(['loaisanpham'])->find($id);
        if (!$product) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        $maCuaHang = null;
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
        if ($user && in_array($user->MAROLE, [0, 1, 2])) {
            $nhanVien = \App\Models\NhanVien::where('USERNAME', $user->USERNAME)->first();
            if ($nhanVien && $nhanVien->MACUAHANG) {
                $maCuaHang = $nhanVien->MACUAHANG;
            }
        }

        $product->setAttribute('TONKHO_THUCTE', $this->calculateStock($product->MASP, $maCuaHang));
        if (!$maCuaHang) {
            $chiTiet = \App\Models\TonKhoCuaHang::with('cuahang')
                ->whereHas('cuahang', function ($q) {
                    $q->where('TRANGTHAI', 'Đang hoạt động');
                })
                ->where('MASP', $product->MASP)
                ->get()
                ->map(function ($tk) {
                    return [
                        'MACUAHANG' => $tk->MACUAHANG,
                        'TENCUAHANG' => $tk->cuahang ? $tk->cuahang->TENCUAHANG : 'Chi nhánh ' . $tk->MACUAHANG,
                        'SOLUONG_TON' => $tk->SOLUONG_TON
                    ];
                })->toArray();
            $product->setAttribute('TONKHO_CHITIET', json_encode($chiTiet));
        }

        return response()->json($product);
    }

    /**
     * Admin: Add new products (Includes image upload feature to MinIO)
     */
    public function store(Request $request)
    {
        $request->validate([
            'MASP' => 'required|string|unique:sanpham,MASP',
            'MALOAI' => 'required|exists:loaisanpham,MALOAI',
            'TENSP' => 'required|string|unique:sanpham,TENSP',
            'GIABAN' => 'required|numeric|min:0',
            'SOLUONG' => 'nullable|numeric|min:0',
            'DVT' => 'required|string',
            'GHICHU' => 'nullable|string',
            'HINHANH' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'IS_NEW' => 'nullable|boolean',
            'PHAN_TRAM_GIAM' => 'nullable|integer|min:0|max:100'
        ]);

        // Upload photos to MinIO via UploadService
        $imageUrl = $this->uploadService->uploadImage($request->file('HINHANH'), 'products');

        $product = SanPham::create([
            'MASP' => $request->MASP,
            'MALOAI' => $request->MALOAI,
            'TENSP' => $request->TENSP,
            'GIABAN' => $request->GIABAN,
            'DVT' => $request->DVT,
            'GHICHU' => $request->GHICHU,
            'HINHANH' => $imageUrl,
            'SOLUONG' => $request->input('SOLUONG', 0), // Get the quantity from the form, the default is 0.
            'IS_NEW' => $request->boolean('IS_NEW', false),
            'PHAN_TRAM_GIAM' => $request->input('PHAN_TRAM_GIAM', 0)
        ]);

        \Illuminate\Support\Facades\Cache::tags(['products'])->flush();

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

        $request->validate([
            'MALOAI' => 'required|exists:loaisanpham,MALOAI',
            'TENSP' => 'required|string|unique:sanpham,TENSP,' . $id . ',MASP',
            'GIABAN' => 'required|numeric|min:0',
            'SOLUONG' => 'nullable|numeric|min:0',
            'DVT' => 'required|string',
            'GHICHU' => 'nullable|string',
            'HINHANH' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'IS_NEW' => 'nullable|boolean',
            'PHAN_TRAM_GIAM' => 'nullable|integer|min:0|max:100'
        ]);

        $dataUpdate = $request->except('HINHANH', 'SOLUONG');

        // Handle boolean conversion explicitly for IS_NEW
        if ($request->has('IS_NEW')) {
            $dataUpdate['IS_NEW'] = $request->boolean('IS_NEW');
        }

        // If you upload new photos
        if ($request->hasFile('HINHANH')) {
            // Delete old photos on MinIO
            $this->uploadService->deleteImage($product->HINHANH);
            // Upload new photos
            $dataUpdate['HINHANH'] = $this->uploadService->uploadImage($request->file('HINHANH'), 'products');
        }

        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $auditUsername = $user ? $user->USERNAME : null;
        if ($auditUsername) {
            \Illuminate\Support\Facades\DB::statement("SET @audit_username = '{$auditUsername}'");
        } else {
            \Illuminate\Support\Facades\DB::statement("SET @audit_username = NULL");
        }

        $product->update($dataUpdate);

        // Cập nhật tồn kho chi nhánh
        if ($request->has('SOLUONG')) {
            $maCuaHang = 1;
            $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
            if ($user && in_array($user->MAROLE, [0, 1, 2])) {
                $nhanVien = \App\Models\NhanVien::where('USERNAME', $user->USERNAME)->first();
                if ($nhanVien && $nhanVien->MACUAHANG) {
                    $maCuaHang = $nhanVien->MACUAHANG;
                }
            }
            // Do bảng TonKhoCuaHang không có khóa chính đơn (composite key),
            // nên không thể dùng phương thức update() của Eloquent Model, sẽ làm cập nhật toàn bộ bảng.
            // Phải dùng Query Builder:
            $exists = \Illuminate\Support\Facades\DB::table('tonkho_cuahang')
                        ->where('MACUAHANG', $maCuaHang)
                        ->where('MASP', $id)
                        ->exists();

            $auditUsername = $user ? $user->USERNAME : null;
            if ($auditUsername) {
                \Illuminate\Support\Facades\DB::statement("SET @audit_username = '{$auditUsername}'");
            } else {
                \Illuminate\Support\Facades\DB::statement("SET @audit_username = NULL");
            }
            \Illuminate\Support\Facades\DB::statement("SET @inv_type = 'ADJUSTMENT'");
            \Illuminate\Support\Facades\DB::statement("SET @inv_ref_id = '{$id}'");

            if ($exists) {
                \Illuminate\Support\Facades\DB::table('tonkho_cuahang')
                    ->where('MACUAHANG', $maCuaHang)
                    ->where('MASP', $id)
                    ->update(['SOLUONG_TON' => $request->SOLUONG]);
            } else {
                \Illuminate\Support\Facades\DB::table('tonkho_cuahang')->insert([
                    'MACUAHANG' => $maCuaHang,
                    'MASP' => $id,
                    'SOLUONG_TON' => $request->SOLUONG
                ]);
            }
        }

        \Illuminate\Support\Facades\Cache::tags(['products'])->flush();

        return response()->json(['message' => 'Cập nhật sản phẩm thành công', 'data' => $product]);
    }

    /**
     * Admin: delete product
     */
    public function destroy($id)
    {
        $product = SanPham::find($id);
        if (!$product) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        try {
            $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $auditUsername = $user ? $user->USERNAME : null;
            if ($auditUsername) {
                \Illuminate\Support\Facades\DB::statement("SET @audit_username = '{$auditUsername}'");
            } else {
                \Illuminate\Support\Facades\DB::statement("SET @audit_username = NULL");
            }
            // Không xóa bảng dữ liệu liên quan để giữ lịch sử, chỉ thực hiện vô hiệu hóa (Soft Delete)
            $product->delete();

            \Illuminate\Support\Facades\Cache::tags(['products'])->flush();

            return response()->json(['message' => 'Đã vô hiệu hóa (Ngừng bán) sản phẩm']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi vô hiệu hóa: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Admin: Khôi phục sản phẩm đã bị vô hiệu hóa
     */
    public function restore($id)
    {
        $product = SanPham::withTrashed()->find($id);
        if (!$product) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        try {
            $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
            $auditUsername = $user ? $user->USERNAME : null;
            if ($auditUsername) {
                \Illuminate\Support\Facades\DB::statement("SET @audit_username = '{$auditUsername}'");
            } else {
                \Illuminate\Support\Facades\DB::statement("SET @audit_username = NULL");
            }
            $product->restore();
            \Illuminate\Support\Facades\Cache::tags(['products'])->flush();
            return response()->json(['message' => 'Đã kích hoạt lại sản phẩm']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi kích hoạt lại: ' . $e->getMessage()], 500);
        }
    }

    public function calculateStock($masp, $maCuaHang = null)
    {
        if ($maCuaHang) {
            $tonKho = \App\Models\TonKhoCuaHang::where('MASP', $masp)->where('MACUAHANG', $maCuaHang)->first();
            return $tonKho ? $tonKho->SOLUONG_TON : 0;
        }

        // Đối với khách hàng mua online (không có $maCuaHang cụ thể),
        // Số lượng tồn kho lớn nhất hiển thị phải là số lượng lớn nhất ở MỘT chi nhánh bất kỳ.
        // Vì hệ thống hiện tại chỉ giao hàng từ 1 chi nhánh cho 1 đơn hàng (không chia nhỏ đơn).
        $maxStock = \App\Models\TonKhoCuaHang::whereHas('cuahang', function ($q) {
            $q->where('TRANGTHAI', 'Đang hoạt động');
        })->where('MASP', $masp)->max('SOLUONG_TON');
        return $maxStock ? $maxStock : 0;
    }

    /**
     * Homepage: Top 8 sản phẩm được mua nhiều nhất (dựa trên tổng số lượng bán trong chi tiết hóa đơn)
     */
    public function getBestSellers()
    {
        $bestSellers = \Illuminate\Support\Facades\DB::table('chitiethdban')
            ->select('chitiethdban.MASP', \Illuminate\Support\Facades\DB::raw('SUM(chitiethdban.SOLUONG) as TONG_BAN'))
            ->join('sanpham', 'sanpham.MASP', '=', 'chitiethdban.MASP')
            ->whereNull('sanpham.deleted_at')
            ->groupBy('chitiethdban.MASP')
            ->orderByDesc('TONG_BAN')
            ->limit(8)
            ->pluck('chitiethdban.MASP')
            ->toArray();

        if (empty($bestSellers)) {
            // Nếu chưa có đơn hàng nào, trả về 8 sản phẩm mới nhất làm mặc định
            $products = SanPham::with('loaisanpham')->orderByDesc('created_at')->limit(8)->get();
        } else {
            $products = SanPham::with('loaisanpham')
                ->whereIn('MASP', $bestSellers)
                ->get()
                ->sortBy(function ($product) use ($bestSellers) {
                    return array_search($product->MASP, $bestSellers);
                })->values();
        }

        // Thêm thông tin tồn kho cho từng sản phẩm
        $products->each(function ($product) {
            $product->setAttribute('TONKHO_THUCTE', $this->calculateStock($product->MASP));
            $chiTiet = \App\Models\TonKhoCuaHang::with('cuahang')
                ->whereHas('cuahang', function ($q) {
                    $q->where('TRANGTHAI', 'Đang hoạt động');
                })
                ->where('MASP', $product->MASP)
                ->get()
                ->map(function ($tk) {
                    return [
                        'MACUAHANG' => $tk->MACUAHANG,
                        'TENCUAHANG' => $tk->cuahang ? $tk->cuahang->TENCUAHANG : 'Chi nhánh ' . $tk->MACUAHANG,
                        'SOLUONG_TON' => $tk->SOLUONG_TON
                    ];
                })->toArray();
            $product->setAttribute('TONKHO_CHITIET', json_encode($chiTiet));
        });

        return response()->json($products);
    }

    /**
     * Homepage: Lấy sản phẩm theo nhóm cho trang chủ
     * - discounted: Sản phẩm có giảm giá (PHAN_TRAM_GIAM > 0)
     * - newest: Sản phẩm có tag mới (IS_NEW = true)
     * - expensive: Top 8 sản phẩm có giá cao nhất
     */
    public function getHomepageProducts()
    {
        // Sản phẩm đang giảm giá
        $discounted = SanPham::with('loaisanpham')
            ->where('PHAN_TRAM_GIAM', '>', 0)
            ->orderByDesc('PHAN_TRAM_GIAM')
            ->limit(8)
            ->get();

        // Sản phẩm mới (tag IS_NEW)
        $newest = SanPham::with('loaisanpham')
            ->where('IS_NEW', true)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // Top 8 sản phẩm giá cao nhất
        $expensive = SanPham::with('loaisanpham')
            ->orderByDesc('GIABAN')
            ->limit(8)
            ->get();

        // Thêm tồn kho cho tất cả sản phẩm
        $allProducts = collect()->merge($discounted)->merge($newest)->merge($expensive)->unique('MASP');
        $allProducts->each(function ($product) {
            $product->setAttribute('TONKHO_THUCTE', $this->calculateStock($product->MASP));
            $chiTiet = \App\Models\TonKhoCuaHang::with('cuahang')
                ->whereHas('cuahang', function ($q) {
                    $q->where('TRANGTHAI', 'Đang hoạt động');
                })
                ->where('MASP', $product->MASP)
                ->get()
                ->map(function ($tk) {
                    return [
                        'MACUAHANG' => $tk->MACUAHANG,
                        'TENCUAHANG' => $tk->cuahang ? $tk->cuahang->TENCUAHANG : 'Chi nhánh ' . $tk->MACUAHANG,
                        'SOLUONG_TON' => $tk->SOLUONG_TON
                    ];
                })->toArray();
            $product->setAttribute('TONKHO_CHITIET', json_encode($chiTiet));
        });

        return response()->json([
            'discounted' => $discounted->values(),
            'newest' => $newest->values(),
            'expensive' => $expensive->values()
        ]);
    }
}
