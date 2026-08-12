<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NhatKyHeThong;

class NhatKyHeThongController extends Controller
{
    /**
     * Lấy danh sách nhật ký hệ thống (Chỉ role 0)
     * Hỗ trợ: phân trang, lọc theo thời gian, người thực hiện, hành động
     */
    public function index(Request $request)
    {
        $query = NhatKyHeThong::orderBy('created_at', 'desc');

        // Lọc theo khoảng thời gian
        if ($request->filled('tu_ngay')) {
            $query->whereDate('created_at', '>=', $request->tu_ngay);
        }
        if ($request->filled('den_ngay')) {
            $query->whereDate('created_at', '<=', $request->den_ngay);
        }

        // Lọc theo username
        if ($request->filled('username')) {
            $query->where('USERNAME', 'like', '%' . $request->username . '%');
        }

        // Lọc theo hành động
        if ($request->filled('hanh_dong')) {
            $query->where('HANH_DONG', 'like', '%' . $request->hanh_dong . '%');
        }

        // Phân trang (mặc định 20 bản ghi / trang)
        $perPage = $request->query('per_page', 20);
        $data = $query->paginate($perPage);

        return response()->json($data);
    }
}
