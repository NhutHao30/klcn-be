<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SanPham;
use App\Models\LichSuGiaSanPham;
use App\Models\TonKhoChiNhanh;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ProductController extends Controller
{
    /**
     * View product list
     */
    public function index(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            $token = $request->bearerToken();
            if ($token) {
                try {
                    $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->toUser();
                } catch (\Exception $e) {}
            }
        }

        $storeId = $request->input('store_id') ?? $request->input('storeId') ?? $request->input('cuaHangId') ?? $request->input('macuahang');
        if ($user) {
            $nd = $user->nguoidung;
            if ($nd && $nd->cuaHangId && (int)$user->vaiTroId !== 1 && strtolower($user->username) !== 'admin' && (int)$user->vaiTroId !== 8) {
                // Khóa cứng chi nhánh cho quản lý / nhân viên chi nhánh
                $storeId = $nd->cuaHangId;
            }
        }

        $targetStoreId = $storeId ? (int)$storeId : null;

        // Skip cache for authenticated staff/management or when explicit refresh requested
        $bypassCache = $request->boolean('nocache')
            || $request->has('_t')
            || ($user && in_array((int)($user->vaiTroId ?? 0), [1, 2, 3, 4, 7, 8]));

        $fetchProducts = function () use ($request, $targetStoreId) {
            $query = SanPham::with(['loaiSanPham', 'tonKhoChiNhanhs', 'lichSuGias']);

            // Keyword search
            $keyword = $request->input('keyword') ?? $request->input('search');
            if (!empty($keyword)) {
                $trimmed = trim($keyword);
                $cleanNumeric = preg_replace('/^(SP|#)/i', '', $trimmed);
                $query->where(function($q) use ($trimmed, $cleanNumeric) {
                    $q->where('tenSanPham', 'like', '%' . $trimmed . '%')
                      ->orWhere('id', 'like', '%' . $trimmed . '%')
                      ->orWhere('slug', 'like', '%' . $trimmed . '%');
                    if (is_numeric($cleanNumeric)) {
                        $q->orWhere('id', (int)$cleanNumeric);
                    }
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
            if ($status !== null && $status !== '' && $status !== 'all') {
                if (in_array($status, ['ngung_ban', 'vo_hieu_hoa', 'disabled', '0'], true)) {
                    $query->where('trangThai', 0);
                } else if (in_array($status, ['con_hang', 'In Stock', 'in_stock'], true)) {
                    $query->where('trangThai', 1)->whereHas('tonKhoChiNhanhs', function($q) use ($targetStoreId) {
                        if ($targetStoreId) {
                            $q->where('cuaHangId', $targetStoreId)->where('soLuongTon', '>', 0);
                        } else {
                            $q->where('soLuongTon', '>', 0);
                        }
                    });
                } else if (in_array($status, ['het_hang', 'Out of Stock', 'out_of_stock'], true)) {
                    $query->where('trangThai', 1)->where(function($q) use ($targetStoreId) {
                        if ($targetStoreId) {
                            $q->whereDoesntHave('tonKhoChiNhanhs', function($q2) use ($targetStoreId) {
                                q2: $q2->where('cuaHangId', $targetStoreId)->where('soLuongTon', '>', 0);
                            });
                        } else {
                            $q->whereDoesntHave('tonKhoChiNhanhs', function($q2) {
                                $q2->where('soLuongTon', '>', 0);
                            });
                        }
                    });
                } else if (in_array($status, ['is_new', 'new', 'IS_NEW'], true)) {
                    $query->where('trangThai', 1)->where('isNew', 1);
                } else if (in_array($status, ['giam_gia', 'discount'], true)) {
                    $latestPriceSub = DB::table('lichSuGiaSanPham as l1')
                        ->select('l1.sanPhamId')
                        ->whereIn('l1.id', function($sub) {
                            $sub->select(DB::raw('MAX(id)'))
                                ->from('lichSuGiaSanPham')
                                ->groupBy('sanPhamId');
                        })
                        ->where('l1.phanTramGiam', '>', 0);

                    $query->where('trangThai', 1)->whereIn('id', $latestPriceSub);
                }
            }

            $perPage = (int) ($request->input('size') ?? $request->input('per_page') ?? 10);
            $products = $query->orderBy('id', 'desc')->paginate($perPage);

            $products->getCollection()->transform(function ($p) use ($targetStoreId) {
                if ($targetStoreId) {
                    $st = $p->tonKhoChiNhanhs->where('cuaHangId', $targetStoreId)->first();
                    $qty = $st ? (int)$st->soLuongTon : 0;
                } else {
                    $qty = (int)$p->tonKhoChiNhanhs->sum('soLuongTon');
                }
                $p->setAttribute('soLuongTon', $qty);
                $p->setAttribute('SOLUONG_TON', $qty);
                $p->setAttribute('TONKHO_THUCTE', $qty);
                return $p;
            });

            $stats = [
                'total' => SanPham::count(),
                'active' => SanPham::where('trangThai', 1)->count(),
                'disabled' => SanPham::where('trangThai', 0)->count(),
                'outOfStock' => SanPham::where('trangThai', 1)->whereDoesntHave('tonKhoChiNhanhs', function($q) use ($targetStoreId) {
                    if ($targetStoreId) {
                        $q->where('cuaHangId', $targetStoreId)->where('soLuongTon', '>', 0);
                    } else {
                        $q->where('soLuongTon', '>', 0);
                    }
                })->count(),
            ];

            $responseData = $products->toArray();
            $responseData['stats'] = $stats;

            return $responseData;
        };

        if ($bypassCache) {
            $responseData = $fetchProducts();
        } else {
            $cacheKey = 'products_list_' . md5(serialize($request->all()) . '_' . ($targetStoreId ?? 'all'));
            $responseData = Cache::remember($cacheKey, 600, $fetchProducts);
        }

        return response()->json($responseData);
    }

    /**
     * View single product
     */
    public function show($id)
    {
        $cleanId = preg_replace('/^(SP|#)/i', '', trim($id));
        $cacheKey = 'product_detail_' . $id;
        $product = Cache::remember($cacheKey, 1800, function () use ($id, $cleanId) {
            $query = SanPham::with(['loaiSanPham', 'tonKhoChiNhanhs.cuahang', 'loai', 'tonkho.cuahang']);
            if (is_numeric($cleanId)) {
                $p = $query->find((int)$cleanId);
                if ($p) return $p->toArray();
            }
            $p = $query->where('slug', $id)->first();
            return $p ? $p->toArray() : null;
        });

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
        $isNewInput = $request->input('IS_NEW') ?? $request->input('isNew') ?? $request->input('is_new');
        $isNewVal = ($isNewInput == '1' || $isNewInput === 'true' || $isNewInput === true) ? 1 : 0;

        $product = SanPham::create([
            'tenSanPham' => $validated['tenSanPham'],
            'slug' => $slug,
            'loaiId' => $validated['loaiId'],
            'giaBan' => $validated['giaBan'],
            'donViTinh' => $validated['donViTinh'],
            'moTa' => $validated['moTa'] ?? '',
            'hinhAnh' => $hinhAnhPath,
            'trangThai' => 1,
            'isNew' => $isNewVal,
        ]);

        $phanTramGiam = (float) ($request->input('PHAN_TRAM_GIAM') ?? $request->input('phanTramGiam') ?? $request->input('phan_tram_giam') ?? 0);

        // Ghi nhận lịch sử giá ban đầu
        LichSuGiaSanPham::create([
            'sanPhamId' => $product->id,
            'giaCu' => null,
            'giaMoi' => $validated['giaBan'],
            'phanTramGiam' => max(0, $phanTramGiam),
            'lyDo' => $phanTramGiam > 0 ? "Khởi tạo sản phẩm với giảm giá {$phanTramGiam}%" : 'Khởi tạo sản phẩm mới',
            'ngayApDung' => now(),
        ]);

        self::clearProductCache();

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

        $isNewInput = $request->input('IS_NEW') ?? $request->input('isNew') ?? $request->input('is_new');
        if ($isNewInput !== null) {
            $validated['isNew'] = ($isNewInput == '1' || $isNewInput === 'true' || $isNewInput === true) ? 1 : 0;
        }

        $oldPrice = $product->giaBan;
        $product->update($validated);

        $phanTramGiamInput = $request->input('PHAN_TRAM_GIAM') ?? $request->input('phanTramGiam') ?? $request->input('phan_tram_giam');

        if ($phanTramGiamInput !== null || (isset($validated['giaBan']) && $validated['giaBan'] != $oldPrice)) {
            $newPrice = isset($validated['giaBan']) ? (float)$validated['giaBan'] : (float)$product->giaBan;
            $discountPct = $phanTramGiamInput !== null ? (float)$phanTramGiamInput : ($oldPrice > 0 && $newPrice < $oldPrice ? round((($oldPrice - $newPrice) / $oldPrice) * 100, 2) : 0);

            LichSuGiaSanPham::create([
                'sanPhamId' => $product->id,
                'giaCu' => $oldPrice,
                'giaMoi' => $newPrice,
                'phanTramGiam' => max(0, $discountPct),
                'lyDo' => $request->ly_do_doi_gia ?? ($discountPct > 0 ? "Điều chỉnh % giảm giá: {$discountPct}%" : 'Điều chỉnh giá bán'),
                'ngayApDung' => now(),
            ]);
        }

        self::clearProductCache($id);

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
        self::clearProductCache($id);
        return response()->json(['message' => 'Đã ngừng kinh doanh sản phẩm']);
    }

    /**
     * Admin: Restore product
     */
    public function restore($id)
    {
        $product = SanPham::find($id);
        if (!$product) {
            return response()->json(['error' => 'Không tìm thấy sản phẩm'], 404);
        }

        $product->update(['trangThai' => 1]);
        self::clearProductCache($id);
        return response()->json(['message' => 'Đã kích hoạt lại sản phẩm']);
    }

    /**
     * Get price history
     */
    public function getPriceHistory($id)
    {
        $history = LichSuGiaSanPham::where('sanPhamId', $id)->orderBy('createdAt', 'desc')->get();
        return response()->json($history);
    }

    /**
     * Get best selling products
     */
    public function getBestSellers()
    {
        $products = Cache::remember('products_bestsellers', 1800, function () {
            return SanPham::where('trangThai', 1)
                ->with(['loaiSanPham', 'tonKhoChiNhanhs'])
                ->orderBy('id', 'desc')
                ->take(8)
                ->get()
                ->toArray();
        });
        return response()->json($products);
    }

    /**
     * Get homepage grouped products
     */
    public function getHomepageProducts()
    {
        $data = Cache::remember('products_homepage', 1800, function () {
            $products = SanPham::where('trangThai', 1)
                ->with(['loaiSanPham', 'tonKhoChiNhanhs'])
                ->get();

            $discounted = $products->filter(function ($p) {
                return ($p->phanTramGiam ?? 0) > 0;
            })->values()->take(8);

            if ($discounted->isEmpty()) {
                $discounted = $products->take(8);
            }

            $newest = $products->sortByDesc('createdAt')->values()->take(8);
            $expensive = $products->sortByDesc('giaBan')->values()->take(8);

            return [
                'discounted' => $discounted->values()->toArray(),
                'newest' => $newest->values()->toArray(),
                'expensive' => $expensive->values()->toArray(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Clear all product-related Redis caches
     */
    public static function clearProductCache($productId = null)
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {}

        Cache::forget('products_homepage');
        Cache::forget('products_bestsellers');
        if ($productId) {
            Cache::forget('product_detail_' . $productId);
        }

        try {
            $redis = Redis::connection('cache');
            $prefix = config('database.redis.options.prefix', 'laravel-database-');
            $keys = $redis->keys('*products_list_*');
            foreach ($keys as $k) {
                $rawKey = str_starts_with($k, $prefix) ? substr($k, strlen($prefix)) : $k;
                $redis->del($rawKey);
            }
        } catch (\Throwable $e) {
            // Silently ignore cache clearing errors
        }
    }
}
