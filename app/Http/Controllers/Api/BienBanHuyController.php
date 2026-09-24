<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BienBanHuy;
use App\Models\ChiTietBienBanHuy;
use App\Models\ChuKySo;
use App\Models\LoHang;
use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BienBanHuyController extends Controller
{
    public function index(Request $request)
    {
        $records = BienBanHuy::with(['cuaHang', 'nguoiLap', 'chuKySos.nguoiKy', 'chiTiets.sanPham', 'chiTiets.loHang'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách biên bản hủy sản phẩm hết hạn thành công',
            'data' => $records,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cuahang_id' => 'required|exists:cuahang,id',
            'nguoi_lap_id' => 'required|exists:nguoidung,id',
            'ly_do' => 'required|string|max:255',
            'auto_sign_all' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.lohang_id' => 'required|exists:lo_hang,id',
            'items.*.so_luong_huy' => 'required|integer|min:1',
        ]);

        $autoSign = $request->get('auto_sign_all', false) ? 1 : 0;

        try {
            DB::statement('CALL sp_create_bien_ban_huy(?, ?, ?, ?, ?, @bienban_id, @ma_bien_ban, @error)', [
                $validated['cuahang_id'],
                $validated['nguoi_lap_id'],
                $validated['ly_do'],
                $autoSign,
                json_encode($validated['items']),
            ]);

            $res = DB::select('SELECT @bienban_id AS bienban_id, @ma_bien_ban AS ma_bien_ban, @error AS error')[0];

            if ($res->error) {
                return response()->json(['success' => false, 'message' => $res->error], 500);
            }

            $disposal = BienBanHuy::find($res->bienban_id);

            return response()->json([
                'success' => true,
                'message' => $autoSign ? 'Tạo biên bản hủy & tự động hoàn tất ký 2 bên thành công' : 'Tạo biên bản hủy thành công, đã gửi yêu cầu trình ký cho Kế toán & Quản lý kho',
                'data' => $disposal->load(['cuaHang', 'nguoiLap', 'chuKySos', 'chiTiets.loHang', 'chiTiets.sanPham']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi tạo biên bản hủy: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $disposal = BienBanHuy::with(['cuaHang', 'nguoiLap', 'chuKySos.nguoiKy', 'chiTiets.sanPham', 'chiTiets.loHang'])->find($id);

        if (!$disposal) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy biên bản hủy',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết biên bản hủy sản phẩm',
            'data' => $disposal,
        ]);
    }
}
