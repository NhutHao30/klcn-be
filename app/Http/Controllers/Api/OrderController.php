<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;
use App\Models\LichSuTrangThaiDonHang;
use App\Models\SanPham;
use App\Models\Voucher;
use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;

class OrderController extends Controller
{
    /**
     * Customer & Admin: View order list
     */
    public function index(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $query = DonHang::with(['chitiet.sanpham', 'cuahang', 'voucher', 'timelineGHN']);

        // Khách hàng chỉ xem đơn hàng của mình
        if ($user->vaitro_id == 6) { // CUSTOMER
            $nguoidung = $user->nguoidung;
            $query->where('nguoidung_id', $nguoidung ? $nguoidung->id : 0);
        }

        if ($request->filled('trang_thai_don_hang')) {
            $query->where('trang_thai_don_hang', $request->trang_thai_don_hang);
        }

        $orders = $query->orderBy('id', 'desc')->paginate(10);
        return response()->json($orders);
    }

    /**
     * Customer: Checkout Online (Thông qua Stored Procedure sp_checkout_online)
     */
    public function checkoutOnline(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nguoidung = $user->nguoidung;
        if (!$nguoidung) {
            return response()->json(['error' => 'Không tìm thấy hồ sơ người dùng'], 404);
        }

        $request->validate([
            'cuahang_id' => 'required|exists:cuahang,id',
            'items' => 'required|array|min:1',
            'items.*.sanpham_id' => 'required|exists:san_pham,id',
            'items.*.so_luong' => 'required|integer|min:1',
            'dia_chi_giao_hang' => 'required|string|max:255',
            'phuong_thuc_thanhtoan' => 'required|integer|in:0,1,2', // 0: COD, 1: VNPAY, 2: MOMO
            'ma_voucher' => 'nullable|string',
        ]);

        try {
            DB::statement('CALL sp_checkout_online(?, ?, ?, ?, ?, ?, ?, @donhang_id, @ma_don_hang, @error)', [
                $nguoidung->id,
                $request->cuahang_id,
                $request->dia_chi_giao_hang,
                $request->phuong_thuc_thanhtoan,
                $request->ma_voucher ?? null,
                $request->ghi_chu ?? null,
                json_encode($request->items),
            ]);

            $res = DB::select('SELECT @donhang_id AS donhang_id, @ma_don_hang AS ma_don_hang, @error AS error')[0];

            if ($res->error) {
                return response()->json(['error' => 'Đặt hàng thất bại: ' . $res->error], 500);
            }

            $donHang = DonHang::find($res->donhang_id);
            return response()->json(['message' => 'Đặt hàng trực tuyến thành công', 'data' => $donHang->load('chitiet')], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Đặt hàng thất bại: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Customer: Cancel order if not yet shipped (Thông qua Stored Procedure sp_cancel_order)
     */
    public function cancelOrder($id)
    {
        $user = Auth::guard('api')->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        DB::statement('CALL sp_cancel_order(?, @error)', [$id]);

        $res = DB::select('SELECT @error AS error')[0];

        if ($res->error) {
            return response()->json(['error' => $res->error], 400);
        }

        return response()->json(['message' => 'Đã hủy đơn hàng thành công']);
    }

    /**
     * GHN Webhook Handler (Thông qua Stored Procedure sp_handle_ghn_webhook)
     */
    public function handleGHNWebhook(Request $request)
    {
        $maVanDon = $request->OrderCode ?? $request->ma_van_don;
        $ghnStatusCode = $request->Status ?? $request->ghn_status_code;

        if (!$maVanDon) {
            return response()->json(['error' => 'Mã vận đơn không hợp lệ'], 400);
        }

        DB::statement('CALL sp_handle_ghn_webhook(?, ?, @donhang_id, @error)', [
            $maVanDon,
            $ghnStatusCode,
        ]);

        $res = DB::select('SELECT @donhang_id AS donhang_id, @error AS error')[0];

        if ($res->error) {
            return response()->json(['error' => $res->error], 404);
        }

        return response()->json(['success' => true, 'message' => 'Cập nhật Webhook GHN thành công']);
    }
}
