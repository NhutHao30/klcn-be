<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PosShift;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\DB;

class PosShiftController extends Controller
{
    /**
     * Mở ca làm việc POS (Thông qua Stored Procedure sp_open_pos_shift)
     */
    public function openShift(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $nguoidung = $user->nguoidung;
        if (!$nguoidung || !$nguoidung->cuahang_id) {
            return response()->json(['error' => 'Chỉ nhân viên thuộc chi nhánh mới có thể mở ca làm việc'], 403);
        }

        $request->validate([
            'tien_mat_dau_ca' => 'required|numeric|min:0',
        ]);

        DB::statement('CALL sp_open_pos_shift(?, ?, ?, @shift_id, @error)', [
            $nguoidung->cuahang_id,
            $nguoidung->id,
            $request->tien_mat_dau_ca,
        ]);

        $res = DB::select('SELECT @shift_id AS shift_id, @error AS error')[0];

        if ($res->error) {
            $activeShift = PosShift::where('nhanvien_id', $nguoidung->id)->where('trang_thai', 0)->first();
            return response()->json(['error' => $res->error, 'data' => $activeShift], 400);
        }

        $shift = PosShift::find($res->shift_id);
        return response()->json(['message' => 'Mở ca làm việc thành công', 'data' => $shift], 201);
    }

    /**
     * Đóng ca làm việc & Kết toán doanh thu (Thông qua Stored Procedure sp_close_pos_shift)
     */
    public function closeShift(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $nguoidung = $user->nguoidung;
        if (!$nguoidung) {
            return response()->json(['error' => 'Không tìm thấy hồ sơ người dùng'], 404);
        }

        $request->validate([
            'tien_mat_thuc_te' => 'required|numeric|min:0',
        ]);

        DB::statement('CALL sp_close_pos_shift(?, ?, @shift_id, @error)', [
            $nguoidung->id,
            $request->tien_mat_thuc_te,
        ]);

        $res = DB::select('SELECT @shift_id AS shift_id, @error AS error')[0];

        if ($res->error) {
            return response()->json(['error' => $res->error], 404);
        }

        $shift = PosShift::find($res->shift_id);
        return response()->json(['message' => 'Đã chốt ca và đóng ca thành công', 'data' => $shift]);
    }

    /**
     * Bán hàng & Thanh toán tại quầy POS (Thông qua Stored Procedure sp_pos_checkout & chống trùng Idempotency)
     */
    public function posCheckout(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->idempotency_key;

        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return response()->json(json_decode($existing->response_payload, true), $existing->status_code);
            }
        }

        $request->validate([
            'cuahang_id' => 'required|exists:cuahang,id',
            'items' => 'required|array|min:1',
            'items.*.sanpham_id' => 'required|exists:san_pham,id',
            'items.*.so_luong' => 'required|integer|min:1',
            'phuong_thuc_thanhtoan' => 'required|integer|in:0,1,2',
        ]);

        try {
            DB::statement('CALL sp_pos_checkout(?, ?, ?, @donhang_id, @ma_don_hang, @error)', [
                $request->cuahang_id,
                $request->phuong_thuc_thanhtoan,
                json_encode($request->items),
            ]);

            $res = DB::select('SELECT @donhang_id AS donhang_id, @ma_don_hang AS ma_don_hang, @error AS error')[0];

            if ($res->error) {
                return response()->json(['error' => 'Thanh toán POS thất bại: ' . $res->error], 500);
            }

            $donHang = DonHang::find($res->donhang_id);
            $responseData = ['message' => 'Thanh toán POS thành công', 'data' => $donHang->load('chitiet')];

            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'idempotency_key' => $idempotencyKey,
                    'request_path' => $request->path(),
                    'request_hash' => md5(serialize($request->all())),
                    'response_payload' => json_encode($responseData),
                    'status_code' => 200,
                    'expires_at' => now()->addHours(24),
                ]);
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Thanh toán POS thất bại: ' . $e->getMessage()], 500);
        }
    }
}
