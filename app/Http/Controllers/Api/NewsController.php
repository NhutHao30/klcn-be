<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    private function normalizeImageUrl(?string $hinhAnh): string
    {
        if (!$hinhAnh) {
            return '/api/proxy-image?path=productnew1.webp';
        }
        $trimmed = trim($hinhAnh);
        if (
            str_starts_with($trimmed, 'http://') ||
            str_starts_with($trimmed, 'https://') ||
            str_starts_with($trimmed, '/api/') ||
            str_starts_with($trimmed, 'data:image/')
        ) {
            return $trimmed;
        }
        return '/api/proxy-image?path=' . urlencode(basename($trimmed));
    }

    private function handleUploadedImage(Request $request, ?string $fallback = null): string
    {
        if ($request->hasFile('HINHANH')) {
            $file = $request->file('HINHANH');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $fileName = 'news_' . time() . '_' . uniqid() . '.' . $ext;

            // Lưu vào public/assets/IMG để ImageProxyController luôn đọc được
            $destDir = public_path('assets/IMG');
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0775, true);
            }
            $file->move($destDir, $fileName);

            // Đồng bộ lên MinIO (s3) nếu khả dụng
            try {
                $localFile = $destDir . DIRECTORY_SEPARATOR . $fileName;
                if (file_exists($localFile)) {
                    Storage::disk('s3')->put($fileName, file_get_contents($localFile));
                }
            } catch (\Throwable $e) {
                // Ignore S3 error, local file in public/assets/IMG is already saved
            }

            return '/api/proxy-image?path=' . $fileName;
        }

        $inputImg = $request->input('HINHANH');
        if (is_string($inputImg) && trim($inputImg) !== '') {
            return $this->normalizeImageUrl($inputImg);
        }

        return $fallback ?: '/api/proxy-image?path=productnew1.webp';
    }

    private function attachDetails(array $items): array
    {
        if (empty($items)) {
            return [];
        }
        $ids = array_map(fn($item) => $item->MATINTUC, $items);
        $details = DB::table('chitiettintuc')
            ->whereIn('MATINTUC', $ids)
            ->orderBy('THUTU', 'asc')
            ->orderBy('MACT', 'asc')
            ->get()
            ->groupBy('MATINTUC');

        foreach ($items as $item) {
            $item->HINHANH = $this->normalizeImageUrl($item->HINHANH);
            $item->chitiets = isset($details[$item->MATINTUC])
                ? $details[$item->MATINTUC]->values()->toArray()
                : [];
        }

        return $items;
    }

    public function index(Request $request)
    {
        $limit = (int)($request->input('limit') ?: 20);
        $paginator = DB::table('tintuc')
            ->where('trangThai', 1)
            ->orderBy('NGAYDANG', 'desc')
            ->orderBy('MATINTUC', 'desc')
            ->paginate($limit);

        $items = $this->attachDetails($paginator->items());

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function show($id)
    {
        $post = DB::table('tintuc')->where('MATINTUC', $id)->first();
        if (!$post) {
            return response()->json(['error' => 'Không tìm thấy bài viết tin tức'], 404);
        }

        $post->HINHANH = $this->normalizeImageUrl($post->HINHANH);
        $post->chitiets = DB::table('chitiettintuc')
            ->where('MATINTUC', $id)
            ->orderBy('THUTU', 'asc')
            ->orderBy('MACT', 'asc')
            ->get()
            ->values()
            ->toArray();

        return response()->json($post);
    }

    public function getComments($id)
    {
        $rows = DB::table('binhluantintuc as bl')
            ->leftJoin('taikhoan as tk', 'bl.taiKhoanId', '=', 'tk.id')
            ->leftJoin('nguoidung as nd', 'tk.id', '=', 'nd.taiKhoanId')
            ->where('bl.MATINTUC', $id)
            ->select(
                'bl.MABL',
                'bl.MATINTUC',
                'bl.MAKH',
                'bl.taiKhoanId',
                'bl.HOTEN',
                'bl.NOIDUNG',
                'bl.NGAYDANG',
                'bl.PARENT_ID',
                'tk.username as USERNAME',
                'tk.vaiTroId',
                'nd.hoTen as ND_HOTEN'
            )
            ->orderBy('bl.NGAYDANG', 'asc')
            ->orderBy('bl.MABL', 'asc')
            ->get();

        $byId = [];
        foreach ($rows as $r) {
            $vaiTroId = (int)($r->vaiTroId ?? 6);
            $isAdminRole = in_array($vaiTroId, [1, 2, 5, 8], true);
            $hoTen = $r->HOTEN ?: ($r->ND_HOTEN ?: ($r->USERNAME ?: 'Khách hàng'));

            $byId[$r->MABL] = [
                'MABL' => $r->MABL,
                'MATINTUC' => $r->MATINTUC,
                'MAKH' => $r->MAKH ?: ($isAdminRole ? 'KH_ADMIN' : 'KH_' . ($r->taiKhoanId ?: 'GUEST')),
                'NOIDUNG' => $r->NOIDUNG,
                'NGAYDANG' => $r->NGAYDANG ? substr((string)$r->NGAYDANG, 0, 10) : date('Y-m-d'),
                'PARENT_ID' => $r->PARENT_ID,
                'khachhang' => [
                    'HOTEN' => $hoTen,
                    'USERNAME' => $r->USERNAME ?: $hoTen,
                    'AVATAR' => null,
                    'taikhoan' => [
                        'MAROLE' => $isAdminRole ? 0 : 3,
                        'AVATAR' => null,
                    ],
                ],
                'replyTo' => null,
                'replies' => [],
            ];
        }

        // Helper tìm gốc của chuỗi bình luận (Root comment)
        $findRootId = function ($mabl) use (&$findRootId, $byId) {
            if (!isset($byId[$mabl])) return $mabl;
            $pId = $byId[$mabl]['PARENT_ID'];
            if (!$pId || !isset($byId[$pId])) {
                return $mabl;
            }
            return $findRootId($pId);
        };

        // Gắn thông tin người được trả lời (replyTo)
        foreach ($byId as $mabl => &$cmt) {
            $pId = $cmt['PARENT_ID'];
            if ($pId && isset($byId[$pId])) {
                $cmt['replyTo'] = [
                    'MABL' => $pId,
                    'HOTEN' => $byId[$pId]['khachhang']['HOTEN'] ?? 'Người dùng',
                ];
            }
        }
        unset($cmt);

        // Gom tất cả các câu trả lời về root thread (2-level hierarchy phẳng)
        // để tránh lỗi thụt lề vô tận và để mobile/web đồng bộ 100%
        $roots = [];
        foreach ($byId as $mabl => &$cmt) {
            $pId = $cmt['PARENT_ID'];
            if (!$pId || !isset($byId[$pId])) {
                $roots[$mabl] = &$cmt;
            } else {
                $rootId = $findRootId($mabl);
                if ($rootId && isset($byId[$rootId])) {
                    $byId[$rootId]['replies'][] = &$cmt;
                } else {
                    $roots[$mabl] = &$cmt;
                }
            }
        }
        unset($cmt);

        return response()->json(array_values($roots));
    }

    public function postComment(Request $request, $id)
    {
        $noiDung = trim((string)($request->input('noidung') ?? $request->input('NOIDUNG') ?? ''));
        $parentId = $request->input('parent_id') ?? $request->input('PARENT_ID');

        if ($noiDung === '') {
            return response()->json(['error' => 'Vui lòng nhập nội dung bình luận'], 422);
        }

        $post = DB::table('tintuc')->where('MATINTUC', $id)->first();
        if (!$post) {
            return response()->json(['error' => 'Bài viết không tồn tại'], 404);
        }

        $user = Auth::guard('api')->user();
        $taiKhoanId = $user?->id;
        $vaiTroId = (int)($user?->vaiTroId ?? 6);
        $isAdminRole = in_array($vaiTroId, [1, 2, 5, 8], true);
        $hoTen = $user?->nguoidung?->hoTen ?: ($user?->username ?: 'Khách hàng');
        if ($isAdminRole && !str_contains($hoTen, 'Quản trị viên')) {
            $hoTen = $hoTen . ' (Quản trị viên)';
        }

        $mabl = DB::table('binhluantintuc')->insertGetId([
            'MATINTUC' => $id,
            'MAKH' => $isAdminRole ? 'KH_ADMIN_' . $taiKhoanId : 'KH_' . ($taiKhoanId ?: 'GUEST'),
            'taiKhoanId' => $taiKhoanId,
            'HOTEN' => $hoTen,
            'NOIDUNG' => $noiDung,
            'NGAYDANG' => now(),
            'PARENT_ID' => $parentId ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi bình luận thành công',
            'MABL' => $mabl,
        ], 201);
    }

    public function deleteComment($id)
    {
        DB::table('binhluantintuc')
            ->where('MABL', $id)
            ->orWhere('PARENT_ID', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa bình luận thành công',
        ]);
    }

    public function adminIndex(Request $request)
    {
        $search = trim((string)$request->input('search', ''));
        $query = DB::table('tintuc')
            ->orderBy('NGAYDANG', 'desc')
            ->orderBy('MATINTUC', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('TIEUDE', 'like', "%{$search}%")
                  ->orWhere('MOTA', 'like', "%{$search}%")
                  ->orWhere('MATINTUC', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate(50);
        $items = $this->attachDetails($paginator->items());

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    private function generateNextMaTinTuc(): string
    {
        $rows = DB::table('tintuc')->pluck('MATINTUC');
        $maxNum = 0;
        foreach ($rows as $code) {
            if (preg_match('/^TT(\d+)$/i', (string)$code, $m)) {
                $num = (int)$m[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
        return 'TT' . str_pad((string)($maxNum + 1), 3, '0', STR_PAD_LEFT);
    }

    private function parseChiTiets($rawChiTiets, ?string $articleFallback = null): array
    {
        if (is_string($rawChiTiets) && trim($rawChiTiets) !== '') {
            $decoded = json_decode($rawChiTiets, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        } elseif (is_array($rawChiTiets)) {
            return $rawChiTiets;
        }

        if ($articleFallback && trim($articleFallback) !== '') {
            $paragraphs = preg_split('/\n\s*\n/', trim($articleFallback));
            return array_map(fn($p) => ['BOCUC' => 'paragraph', 'ARTICLE' => trim($p)], array_filter($paragraphs));
        }

        return [];
    }

    public function store(Request $request)
    {
        $tieuDe = trim((string)($request->input('TIEUDE') ?? $request->input('tieuDe') ?? ''));
        $moTa = trim((string)($request->input('MOTA') ?? $request->input('moTa') ?? ''));

        if ($tieuDe === '') {
            return response()->json(['error' => 'Vui lòng nhập tiêu đề bài viết tin tức!'], 422);
        }

        $maTinTuc = $this->generateNextMaTinTuc();
        $hinhAnh = $this->handleUploadedImage($request);
        $chiTiets = $this->parseChiTiets($request->input('chitiets'), $request->input('ARTICLE'));
        $user = Auth::guard('api')->user();

        DB::beginTransaction();
        try {
            DB::table('tintuc')->insert([
                'MATINTUC' => $maTinTuc,
                'TIEUDE' => $tieuDe,
                'MOTA' => $moTa,
                'HINHANH' => $hinhAnh,
                'NGAYDANG' => now()->toDateString(),
                'taiKhoanId' => $user?->id,
                'trangThai' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($chiTiets as $idx => $ct) {
                $articleText = trim((string)($ct['ARTICLE'] ?? $ct['article'] ?? ''));
                if ($articleText === '') continue;
                DB::table('chitiettintuc')->insert([
                    'MATINTUC' => $maTinTuc,
                    'BOCUC' => $ct['BOCUC'] ?? 'paragraph',
                    'ARTICLE' => $articleText,
                    'HINHANH' => $ct['HINHANH'] ?? null,
                    'THUTU' => $idx + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            \App\Services\AuditTrail::logCustom(
                'CREATE',
                'TinTuc',
                $maTinTuc,
                null,
                ['MATINTUC' => $maTinTuc, 'TIEUDE' => $tieuDe, 'MOTA' => $moTa],
                "Đăng bài viết tin tức mới #{$maTinTuc}: {$tieuDe}"
            );

            return $this->show($maTinTuc);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi khi thêm tin tức: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $existing = DB::table('tintuc')->where('MATINTUC', $id)->first();
        if (!$existing) {
            return response()->json(['error' => 'Không tìm thấy bài viết cần sửa'], 404);
        }

        $tieuDe = trim((string)($request->input('TIEUDE') ?? $existing->TIEUDE));
        $moTa = (string)($request->input('MOTA') ?? $existing->MOTA ?? '');
        $hinhAnh = $this->handleUploadedImage($request, $existing->HINHANH);
        $chiTiets = $this->parseChiTiets($request->input('chitiets'), $request->input('ARTICLE'));

        DB::beginTransaction();
        try {
            DB::table('tintuc')->where('MATINTUC', $id)->update([
                'TIEUDE' => $tieuDe,
                'MOTA' => $moTa,
                'HINHANH' => $hinhAnh,
                'updated_at' => now(),
            ]);

            if ($request->has('chitiets') || $request->has('ARTICLE')) {
                DB::table('chitiettintuc')->where('MATINTUC', $id)->delete();
                foreach ($chiTiets as $idx => $ct) {
                    $articleText = trim((string)($ct['ARTICLE'] ?? $ct['article'] ?? ''));
                    if ($articleText === '') continue;
                    DB::table('chitiettintuc')->insert([
                        'MATINTUC' => $id,
                        'BOCUC' => $ct['BOCUC'] ?? 'paragraph',
                        'ARTICLE' => $articleText,
                        'HINHANH' => $ct['HINHANH'] ?? null,
                        'THUTU' => $idx + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            \App\Services\AuditTrail::logCustom(
                'UPDATE',
                'TinTuc',
                $id,
                ['TIEUDE' => $existing->TIEUDE, 'MOTA' => $existing->MOTA],
                ['TIEUDE' => $tieuDe, 'MOTA' => $moTa],
                "Cập nhật bài viết tin tức #{$id}: {$tieuDe}"
            );

            return $this->show($id);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi khi cập nhật tin tức: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $existing = DB::table('tintuc')->where('MATINTUC', $id)->first();
        if (!$existing) {
            return response()->json(['error' => 'Không tìm thấy bài viết cần xóa'], 404);
        }

        DB::beginTransaction();
        try {
            DB::table('chitiettintuc')->where('MATINTUC', $id)->delete();
            DB::table('binhluantintuc')->where('MATINTUC', $id)->delete();
            DB::table('tintuc')->where('MATINTUC', $id)->delete();
            DB::commit();

            \App\Services\AuditTrail::logCustom(
                'DELETE',
                'TinTuc',
                $id,
                ['MATINTUC' => $id, 'TIEUDE' => $existing->TIEUDE],
                null,
                "Xóa bài viết tin tức #{$id}: {$existing->TIEUDE}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Xóa bài viết tin tức thành công',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi khi xóa tin tức: ' . $e->getMessage()], 500);
        }
    }

    public function toggleVisibility($id)
    {
        $existing = DB::table('tintuc')->where('MATINTUC', $id)->first();
        if (!$existing) {
            return response()->json(['error' => 'Không tìm thấy bài viết tin tức'], 404);
        }

        $newStatus = ($existing->trangThai == 1) ? 0 : 1;
        DB::table('tintuc')->where('MATINTUC', $id)->update([
            'trangThai' => $newStatus,
            'updated_at' => now(),
        ]);

        \App\Services\AuditTrail::logCustom(
            'UPDATE',
            'TinTuc',
            $id,
            ['trangThai' => $existing->trangThai],
            ['trangThai' => $newStatus],
            ($newStatus ? 'Hiển thị bài viết tin tức #' : 'Ẩn bài viết tin tức #') . $id
        );

        return response()->json([
            'success' => true,
            'trangThai' => $newStatus,
            'message' => $newStatus ? 'Đã hiển thị bài viết tới khách hàng' : 'Đã ẩn bài viết khỏi khách hàng',
        ]);
    }
}
