<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PosShiftController extends Controller
{
    private function getUserStore()
    {
        $user = Auth::guard('api')->user();
        $nhanVien = \App\Models\NhanVien::where('USERNAME', $user->USERNAME)->first();
        return $nhanVien ? $nhanVien->MACUAHANG : null;
    }

    public function openShift(Request $request)
    {
        $request->validate(['opening_cash' => 'required|numeric|min:0']);

        $openingCash = $request->opening_cash;
        if ($openingCash < 0) {
            return response()->json(['error' => 'Số tiền mở ca không được âm!'], 422);
        }

        $storeId = $this->getUserStore();
        if (!$storeId) return response()->json(['error' => 'User not assigned to a store'], 403);

        $username = Auth::guard('api')->user()->USERNAME;

        try {
            DB::statement('CALL sp_open_shift(?, ?, ?)', [
                $storeId,
                $username,
                $openingCash
            ]);

            // Ghi audit log mở ca
            DB::table('audit_logs')->insert([
                'entity_type' => 'PosShift',
                'entity_id'   => 'STORE-' . $storeId,
                'action'      => 'OPEN_SHIFT',
                'old_values'  => null,
                'new_values'  => json_encode([
                    'macuahang'    => $storeId,
                    'opening_cash' => $openingCash,
                ]),
                'USERNAME'   => $username,
                'created_at' => now(),
            ]);

            return response()->json(['message' => 'Mở ca thành công!']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function closeShift(Request $request)
    {
        $request->validate([
            'shift_id'    => 'required|integer',
            'actual_cash' => 'required|numeric',
        ]);

        $actualCash = $request->actual_cash;
        $shiftId    = $request->shift_id;

        if ($actualCash < 0) {
            return response()->json(['error' => 'Số tiền đóng ca không được âm!'], 422);
        }

        // Lấy thông tin ca hiện tại
        $shift = DB::table('pos_shifts')->where('id', $shiftId)->first();
        if (!$shift || $shift->status !== 'open') {
            return response()->json(['error' => 'Ca làm việc không tồn tại hoặc đã đóng!'], 400);
        }

        // Tính doanh thu trong ca
        $salesInShift = DB::table('hdban')
            ->where('NGAYLAP', '>=', $shift->opened_at)
            ->where('TRANGTHAITHANHTOAN', 'Đã hoàn thành')
            ->sum('TONGTIEN');

        $expectedCash = $shift->opening_cash + $salesInShift;
        $difference   = $actualCash - $expectedCash;

        if ($difference != 0) {
            $msg = 'Số tiền thực tế không khớp với doanh thu! ' 
                 . 'Chênh lệch: ' . number_format(abs($difference), 0, ',', '.') . ' ₫ ' 
                 . ($difference > 0 ? '(Thừa)' : '(Thiếu)') 
                 . '. Vui lòng kiểm tra lại két tiền!';
            return response()->json(['error' => $msg], 400);
        }

        $username = Auth::guard('api')->user()->USERNAME;

        try {
            DB::statement('CALL sp_close_shift(?, ?)', [
                $shiftId,
                $actualCash
            ]);

            // Ghi audit log đóng ca
            DB::table('audit_logs')->insert([
                'entity_type' => 'PosShift',
                'entity_id'   => 'SHIFT-' . $shiftId,
                'action'      => 'CLOSE_SHIFT',
                'old_values'  => json_encode([
                    'opening_cash'  => $shift->opening_cash,
                    'sales_in_shift' => $salesInShift,
                    'expected_cash' => $expectedCash,
                ]),
                'new_values'  => json_encode([
                    'actual_cash' => $actualCash,
                    'difference'  => $difference,
                    'status'      => 'balanced',
                ]),
                'USERNAME'   => $username,
                'created_at' => now(),
            ]);

            return response()->json([
                'message'       => 'Đóng ca thành công!',
                'expected_cash' => $expectedCash,
                'actual_cash'   => $actualCash,
                'difference'    => $difference,
                'warning'       => null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function getActiveShift()
    {
        $storeId = $this->getUserStore();
        if (!$storeId) return response()->json(['error' => 'User not assigned to a store'], 403);

        $shift = DB::table('v_pos_shift_summary')
            ->where('MACUAHANG', $storeId)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return response()->json(['message' => 'No active shift'], 404);
        }
        return response()->json($shift);
    }
}
