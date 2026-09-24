<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\YeuCauDieuChuyen;
use App\Models\ChiTietDieuChuyen;
use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;

class TransferController extends Controller
{
    /**
     * Danh sách các yêu cầu điều chuyển kho
     */
    public function index(Request $request)
    {
        $query = YeuCauDieuChuyen::with(['cuahangXuat', 'cuahangNhap', 'nguoiTao', 'nguoiDuyet', 'chitiet.sanpham']);

        if ($request->filled('trang_thai')) {
            $query->where('trang_thai', $request->trang_thai);
        }

        $transfers = $query->orderBy('id', 'desc')->paginate(10);
        return response()->json($transfers);
    }

    /**
     * Lập yêu cầu điều chuyển kho mới (Thông qua Stored Procedure sp_create_transfer_request)
     */
    public function store(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user || !$user->nguoidung) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $request->validate([
            'cuahang_xuat_id' => 'required|exists:cuahang,id',
            'cuahang_nhap_id' => 'required|exists:cuahang,id|different:cuahang_xuat_id',
            'items' => 'required|array|min:1',
            'items.*.sanpham_id' => 'required|exists:san_pham,id',
            'items.*.so_luong_chuyen' => 'required|integer|min:1',
        ]);

        try {
            DB::statement('CALL sp_create_transfer_request(?, ?, ?, ?, ?, @yeucau_id, @error)', [
                $request->cuahang_xuat_id,
                $request->cuahang_nhap_id,
                $user->nguoidung->id,
                $request->ghi_chu ?? null,
                json_encode($request->items),
            ]);

            $res = DB::select('SELECT @yeucau_id AS yeucau_id, @error AS error')[0];

            if ($res->error) {
                return response()->json(['error' => 'Thất bại: ' . $res->error], 500);
            }

            $yeuCau = YeuCauDieuChuyen::find($res->yeucau_id);
            return response()->json(['message' => 'Lập yêu cầu điều chuyển kho thành công', 'data' => $yeuCau->load('chitiet')], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Thất bại: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Phê duyệt / Cập nhật trạng thái giao nhận kho (Thông qua Stored Procedure sp_update_transfer_status)
     */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        if (!$user || !$user->nguoidung) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $request->validate([
            'trang_thai' => 'required|integer|in:1,2,3', // 1: DANG_VAN_CHUYEN, 2: HOAN_THANH, 3: TU_CHOI
        ]);

        try {
            DB::statement('CALL sp_update_transfer_status(?, ?, ?, @error)', [
                $id,
                $request->trang_thai,
                $user->nguoidung->id,
            ]);

            $res = DB::select('SELECT @error AS error')[0];

            if ($res->error) {
                return response()->json(['error' => 'Lỗi: ' . $res->error], 404);
            }

            $yeuCau = YeuCauDieuChuyen::find($id);
            return response()->json(['message' => 'Cập nhật trạng thái điều chuyển kho thành công', 'data' => $yeuCau->load('chitiet')]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}
