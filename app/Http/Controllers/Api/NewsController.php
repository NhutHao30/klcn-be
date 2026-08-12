<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TinTuc;
use App\Models\ChiTietTinTuc;
use App\Models\BinhLuan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\UploadService;

class NewsController extends Controller
{
    protected $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    // ==========================================
    // PUBLIC API
    // ==========================================

    public function index(Request $request)
    {
        $query = TinTuc::query()->orderBy('NGAYDANG', 'desc');

        if ($request->has('limit')) {
            return response()->json($query->limit($request->limit)->get());
        }

        return response()->json($query->paginate(9));
    }

    public function show($id)
    {
        $news = TinTuc::with('chitiets')->find($id);
        if (!$news) {
            return response()->json(['error' => 'Không tìm thấy tin tức'], 404);
        }
        return response()->json($news);
    }

    public function getComments($id)
    {
        $comments = BinhLuan::with([
            'khachhang' => function ($q) {
                $q->select('MAKH', 'HOTEN', 'USERNAME');
            },
            'khachhang.taikhoan' => function ($q) {
                $q->select('USERNAME', 'AVATAR', 'MAROLE');
            },
            'replies'
        ])
        ->where('MATINTUC', $id)
        ->whereNull('PARENT_ID')
        ->orderBy('NGAYDANG', 'desc')
        ->get();

        return response()->json($comments);
    }

    public function postComment(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Bạn cần đăng nhập để bình luận'], 401);
        }

        $khachhang = $user->khachhang;
        if (!$khachhang) {
            // Nếu người dùng là Staff/Admin (role 0, 1, 2, 4), tự tạo hoặc lấy hồ sơ KhachHang tương ứng
            if (in_array((int)$user->MAROLE, [0, 1, 2, 4])) {
                $khachhang = \App\Models\KhachHang::firstOrCreate(
                    ['USERNAME' => $user->USERNAME],
                    [
                        'MAKH' => 'KH_' . strtoupper(substr(md5($user->USERNAME), 0, 8)),
                        'HOTEN' => $user->nhanvien->HOTEN ?? ($user->MAROLE == 0 ? 'Quản trị viên (Dola Bakery)' : 'Quản lý Chi nhánh'),
                        'SDT' => $user->nhanvien->SDT ?? '0000000000'
                    ]
                );
            } else {
                return response()->json(['error' => 'Không tìm thấy thông tin người dùng để bình luận'], 403);
            }
        }

        $request->validate([
            'noidung' => 'required|string|max:500',
            'parent_id' => 'nullable|exists:binhluan,MABL'
        ]);

        $comment = BinhLuan::create([
            'MAKH' => $khachhang->MAKH,
            'MATINTUC' => $id,
            'PARENT_ID' => $request->parent_id ?? null,
            'NOIDUNG' => $request->noidung,
            'NGAYDANG' => now()
        ]);

        return response()->json(['message' => 'Bình luận thành công', 'data' => $comment], 201);
    }

    public function deleteComment($id)
    {
        $comment = BinhLuan::find($id);
        if (!$comment) {
            return response()->json(['error' => 'Không tìm thấy bình luận'], 404);
        }

        try {
            $comment->delete();
            return response()->json(['message' => 'Xóa bình luận thành công']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi khi xóa bình luận: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // ADMIN API
    // ==========================================

    public function adminIndex(Request $request)
    {
        $query = TinTuc::query()->orderBy('created_at', 'desc');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('TIEUDE', 'like', "%{$search}%");
        }

        return response()->json($query->paginate(10));
    }

    public function store(Request $request)
    {
        $request->validate([
            'TIEUDE' => 'required|string|max:200',
            'MOTA' => 'nullable|string|max:1000',
            'HINHANH' => 'nullable',
            'chitiets' => 'nullable'
        ]);

        $imageUrl = null;
        if ($request->hasFile('HINHANH')) {
            $imageUrl = $this->uploadService->uploadImage($request->file('HINHANH'), 'news');
        } elseif ($request->filled('HINHANH') && is_string($request->HINHANH)) {
            $imageUrl = $request->HINHANH;
        }

        $chitiets = $request->chitiets;
        if (is_string($chitiets)) {
            $chitiets = json_decode($chitiets, true);
        }

        DB::beginTransaction();
        try {
            // Generate ID: TT001, TT002...
            $lastNews = TinTuc::orderBy('MATINTUC', 'desc')->first();
            $newId = 'TT001';
            if ($lastNews) {
                $num = intval(substr($lastNews->MATINTUC, 2)) + 1;
                $newId = 'TT' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }

            $news = TinTuc::create([
                'MATINTUC' => $newId,
                'TIEUDE' => $request->TIEUDE,
                'MOTA' => $request->MOTA,
                'HINHANH' => $imageUrl,
                'NGAYDANG' => now()->toDateString()
            ]);

            if (is_array($chitiets)) {
                foreach ($chitiets as $chitiet) {
                    ChiTietTinTuc::create([
                        'MATINTUC' => $newId,
                        'BOCUC' => $chitiet['BOCUC'] ?? 'paragraph',
                        'ARTICLE' => $chitiet['ARTICLE'] ?? '',
                        'HINHANH' => $chitiet['HINHANH'] ?? null,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Thêm tin tức thành công', 'data' => $news], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $news = TinTuc::find($id);
        if (!$news) {
            return response()->json(['error' => 'Không tìm thấy'], 404);
        }

        $request->validate([
            'TIEUDE' => 'nullable|string|max:200',
            'MOTA' => 'nullable|string|max:1000',
            'HINHANH' => 'nullable',
            'chitiets' => 'nullable'
        ]);

        $chitiets = $request->chitiets;
        if (is_string($chitiets)) {
            $chitiets = json_decode($chitiets, true);
        }

        DB::beginTransaction();
        try {
            $dataUpdate = [];
            if ($request->has('TIEUDE')) {
                $dataUpdate['TIEUDE'] = $request->TIEUDE;
            }
            if ($request->has('MOTA')) {
                $dataUpdate['MOTA'] = $request->MOTA;
            }

            if ($request->hasFile('HINHANH')) {
                $this->uploadService->deleteImage($news->HINHANH);
                $dataUpdate['HINHANH'] = $this->uploadService->uploadImage($request->file('HINHANH'), 'news');
            } elseif ($request->has('HINHANH')) {
                $dataUpdate['HINHANH'] = $request->HINHANH;
            }

            if (!empty($dataUpdate)) {
                $news->update($dataUpdate);
            }

            if ($request->has('chitiets')) {
                ChiTietTinTuc::where('MATINTUC', $id)->delete();
                if (is_array($chitiets)) {
                    foreach ($chitiets as $chitiet) {
                        ChiTietTinTuc::create([
                            'MATINTUC' => $id,
                            'BOCUC' => $chitiet['BOCUC'] ?? 'paragraph',
                            'ARTICLE' => $chitiet['ARTICLE'] ?? '',
                            'HINHANH' => $chitiet['HINHANH'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json(['message' => 'Cập nhật thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $news = TinTuc::find($id);
        if (!$news) {
            return response()->json(['error' => 'Không tìm thấy'], 404);
        }

        DB::beginTransaction();
        try {
            BinhLuan::where('MATINTUC', $id)->delete();
            ChiTietTinTuc::where('MATINTUC', $id)->delete();
            $news->delete();
            DB::commit();
            return response()->json(['message' => 'Xóa thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}
