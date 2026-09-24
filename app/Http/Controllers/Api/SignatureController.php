<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BienBanHuy;
use App\Models\ChuKySo;
use App\Models\HoaDonNhap;
use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignatureController extends Controller
{
    /**
     * Thực hiện ký số cho một vai trò cụ thể trong quy trình trình ký đa bên.
     */
    public function signRole(Request $request)
    {
        $validated = $request->validate([
            'chu_ky_so_id' => 'required|exists:chu_ky_so,id',
            'nguoi_ky_id' => 'required|exists:nguoidung,id',
            'raw_payload' => 'required|string',
            'public_key' => 'nullable|string',
            'ghi_chu' => 'nullable|string',
        ]);

        try {
            DB::statement('CALL sp_sign_role(?, ?, ?, ?, ?, @is_fully_signed, @error)', [
                $validated['chu_ky_so_id'],
                $validated['nguoi_ky_id'],
                $validated['raw_payload'],
                $validated['public_key'] ?? 'SYSTEM_RSA_PUBLIC_KEY',
                $validated['ghi_chu'] ?? 'Đã ký xác nhận thành công',
            ]);

            $res = DB::select('SELECT @is_fully_signed AS is_fully_signed, @error AS error')[0];

            $sigSlot = ChuKySo::find($validated['chu_ky_so_id']);

            if ($res->error) {
                return response()->json([
                    'success' => false,
                    'message' => $res->error,
                    'data' => $sigSlot,
                ], 400);
            }

            $isFullySigned = (bool) $res->is_fully_signed;

            return response()->json([
                'success' => true,
                'message' => $isFullySigned
                    ? 'Tất cả các bên đã ký đủ! Văn bản chính thức có hiệu lực và đã cập nhật kho.'
                    : 'Đã ký thành công vai trò ' . $sigSlot->ten_nguoi_ky . '. Đang chờ các bên còn lại ký tiếp.',
                'data' => [
                    'slot' => $sigSlot->load('nguoiKy'),
                    'hoan_tat_ky' => $isFullySigned,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi ký số: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Tra cứu tất cả chữ ký số của một văn bản.
     */
    public function verifyDocumentSignatures(Request $request)
    {
        $validated = $request->validate([
            'loai_tai_lieu' => 'required|integer|in:1,2,3',
            'tai_lieu_id' => 'required|integer',
        ]);

        $signatures = ChuKySo::with('nguoiKy')
            ->where('loai_tai_lieu', $validated['loai_tai_lieu'])
            ->where('tai_lieu_id', $validated['tai_lieu_id'])
            ->get();

        $totalRequired = ($validated['loai_tai_lieu'] == 1) ? 4 : 2;
        $signedCount = $signatures->where('trang_thai', 1)->count();

        return response()->json([
            'success' => true,
            'message' => "Danh sách chữ ký số ({$signedCount}/{$totalRequired} đã ký)",
            'data' => [
                'tong_so_vai_tro' => $totalRequired,
                'so_vai_tro_da_ky' => $signedCount,
                'hoan_tat' => $signedCount >= $totalRequired,
                'danh_sach_chu_ky' => $signatures,
            ],
        ]);
    }
}
