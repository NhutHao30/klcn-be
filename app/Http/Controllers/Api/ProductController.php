<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SanPham;
use App\Services\UploadService;

class ProductController extends Controller
{
    protected $uploadService;

    public function __construct(?UploadService $uploadService = null)
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

        $products = \Illuminate\Support\Facades\Cache::tags(['products'])->remember($cacheKey, 600, function () use ($query, $maCuaHang, $request) {
            $perPage = (int) $request->input('size', $request->input('limit', $request->input('per_page', 12)));
            $paginated = $query->paginate($perPage);

            // Bulk fetch stock in ONE single query to avoid N+1 query performance bottleneck
            $productIds = $paginated->getCollection()->pluck('MASP')->toArray();
            $allStock = \App\Models\TonKhoCuaHang::with('cuahang')
                ->whereHas('cuahang', function ($q) {
                    $q->where('TRANGTHAI', 'Đang hoạt động');
                })
                ->whereIn('MASP', $productIds)
                ->get()
                ->groupBy('MASP');

            $paginated->getCollection()->transform(function ($product) use ($maCuaHang, $allStock) {
                $stockList = $allStock->get($product->MASP, collect());
                if ($maCuaHang) {
                    $storeStock = $stockList->firstWhere('MACUAHANG', $maCuaHang);
                    $stockVal = $storeStock ? (int)$storeStock->SOLUONG_TON : 0;
                } else {
                    $stockVal = (int)$stockList->sum('SOLUONG_TON');
                }

                // Standardize non-negative stock
                $stockVal = max(0, $stockVal);
                if ($product->SOLUONG < 0) {
                    $product->SOLUONG = 0;
                }

                $product->setAttribute('TONKHO_THUCTE', $stockVal);

                if (!$maCuaHang) {
                    $chiTiet = $stockList->map(function ($tk) {
                        return [
                            'MACUAHANG' => $tk->MACUAHANG,
                            'TENCUAHANG' => $tk->cuahang ? $tk->cuahang->TENCUAHANG : 'Chi nhánh ' . $tk->MACUAHANG,
                            'SOLUONG_TON' => $tk->SOLUONG_TON
                        ];
                    })->values()->toArray();
                    $product->setAttribute('TONKHO_CHITIET', json_encode($chiTiet));
                }
                return $product;
            });

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
        $today = \Carbon\Carbon::today();

        // Tự động quét và loại trừ lô hết hạn nếu có
        try {
            (new \App\Services\LoHangService)->autoExpireLo();
        } catch (\Exception $e) {}

        if ($maCuaHang) {
            $hasLoHang = \App\Models\LoHang::where('MASP', $masp)->where('MACUAHANG', $maCuaHang)->exists();
            if ($hasLoHang) {
                return (int) \App\Models\LoHang::where('MASP', $masp)
                    ->where('MACUAHANG', $maCuaHang)
                    ->where('TRANGTHAI', 'con_hang')
                    ->where('HSD', '>=', $today)
                    ->where('SOLUONG_CON', '>', 0)
                    ->sum('SOLUONG_CON');
            }
            $tonKho = \App\Models\TonKhoCuaHang::where('MASP', $masp)->where('MACUAHANG', $maCuaHang)->first();
            return $tonKho ? $tonKho->SOLUONG_TON : 0;
        }

        $hasLoHang = \App\Models\LoHang::where('MASP', $masp)->exists();
        if ($hasLoHang) {
            $activeStores = \App\Models\CuaHang::where('TRANGTHAI', 'Đang hoạt động')->pluck('MACUAHANG');
            if ($activeStores->isEmpty()) {
                return 0;
            }

            $maxStock = \App\Models\LoHang::selectRaw('MACUAHANG, SUM(SOLUONG_CON) as total_con')
                ->where('MASP', $masp)
                ->whereIn('MACUAHANG', $activeStores)
                ->where('TRANGTHAI', 'con_hang')
                ->where('HSD', '>=', $today)
                ->where('SOLUONG_CON', '>', 0)
                ->groupBy('MACUAHANG')
                ->orderByDesc('total_con')
                ->value('total_con');

            return (int) ($maxStock ?? 0);
        }

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

        // Top 8 sản phẩm bán chạy nhất dựa trên tổng số lượng bán
        $bestSellersIds = \Illuminate\Support\Facades\DB::table('chitiethdban')
            ->select('chitiethdban.MASP', \Illuminate\Support\Facades\DB::raw('SUM(chitiethdban.SOLUONG) as TONG_BAN'))
            ->join('sanpham', 'sanpham.MASP', '=', 'chitiethdban.MASP')
            ->whereNull('sanpham.deleted_at')
            ->groupBy('chitiethdban.MASP')
            ->orderByDesc('TONG_BAN')
            ->limit(8)
            ->pluck('chitiethdban.MASP')
            ->toArray();

        if (empty($bestSellersIds)) {
            $bestSellers = SanPham::with('loaisanpham')->orderByDesc('created_at')->limit(8)->get();
        } else {
            $bestSellers = SanPham::with('loaisanpham')
                ->whereIn('MASP', $bestSellersIds)
                ->get()
                ->sortBy(function ($product) use ($bestSellersIds) {
                    return array_search($product->MASP, $bestSellersIds);
                })->values();
        }

        // Bulk fetch stock for homepage products to avoid N+1 query overhead
        $allProducts = collect()->merge($discounted)->merge($newest)->merge($bestSellers)->unique('MASP');
        $productIds = $allProducts->pluck('MASP')->toArray();
        $allStock = \App\Models\TonKhoCuaHang::with('cuahang')
            ->whereHas('cuahang', function ($q) {
                $q->where('TRANGTHAI', 'Đang hoạt động');
            })
            ->whereIn('MASP', $productIds)
            ->get()
            ->groupBy('MASP');

        $allProducts->each(function ($product) use ($allStock) {
            $stockList = $allStock->get($product->MASP, collect());
            $product->setAttribute('TONKHO_THUCTE', (int)$stockList->sum('SOLUONG_TON'));
            $chiTiet = $stockList->map(function ($tk) {
                return [
                    'MACUAHANG' => $tk->MACUAHANG,
                    'TENCUAHANG' => $tk->cuahang ? $tk->cuahang->TENCUAHANG : 'Chi nhánh ' . $tk->MACUAHANG,
                    'SOLUONG_TON' => $tk->SOLUONG_TON
                ];
            })->values()->toArray();
            $product->setAttribute('TONKHO_CHITIET', json_encode($chiTiet));
        });

        return response()->json([
            'discounted' => $discounted->values(),
            'newest' => $newest->values(),
            'bestSellers' => $bestSellers->values(),
            'expensive' => $bestSellers->values()
        ]);
    }

    /**
     * Lấy lịch sử biến động giá của sản phẩm
     */
    public function getPriceHistory($masp)
    {
        $product = SanPham::find($masp);
        if (!$product) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        $history = \App\Models\LichSuGiaSanPham::with('nhanvien')
            ->where('MASP', $masp)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'masp' => $product->MASP,
            'tensp' => $product->TENSP,
            'gia_hien_tai' => $product->GIABAN,
            'phan_tram_giam_hien_tai' => $product->PHAN_TRAM_GIAM ?? 0,
            'history' => $history
        ]);
    }
}
