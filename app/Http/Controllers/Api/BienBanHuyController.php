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
        $user = \Illuminate\Support\Facades\Auth::guard('api')->user();
        $defaultNguoiLap = 1;
        $prof = null;
        if ($user) {
            $vaiTroId = (int) ($user->vaiTroId ?? 0);
            $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;
            $username = strtolower((string) ($user->username ?? $user->USERNAME ?? ''));
            if ($vaiTroId === 1 || $maRole === 0 || $username === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản Quản trị viên (Admin) không được phép tạo hoặc ký duyệt biên bản hủy!',
                ], 403);
            }
            $prof = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first();
            $defaultNguoiLap = $prof?->id ?? $user->id;
        }

        $cuaHangId = $prof?->cuaHangId ?? $request->input('cuaHangId') ?? $request->input('cua_hang_id') ?? $request->input('macuahang') ?? 1;
        $nguoiLapId = $request->input('nguoi_lap_id') ?? $request->input('nguoiLapId') ?? $defaultNguoiLap;
        $lyDo = $request->input('ly_do') ?? $request->input('lyDo') ?? $request->input('ghi_chu') ?? 'Sản phẩm hết hạn / hư hỏng';
        // Nếu người lập là Quản lý chi nhánh (vaiTroId = 2) -> hoàn tất và trừ tồn kho ngay lập tức, không cần chờ ký
        // Nếu người lập là Nhân viên kho -> tạo ở trạng thái chờ ký (autoSign = 0)
        $isBranchManager = $user && ((int) ($user->vaiTroId ?? 0) === 2 || (int) ($user->MAROLE ?? -1) === 1);
        $autoSign = $isBranchManager ? 1 : ($request->boolean('auto_sign_all', false) ? 1 : 0);

        // Parse items from either 'items' or 'chi_tiet'
        $rawItems = $request->input('items') ?? $request->input('chi_tiet') ?? [];
        if (empty($rawItems) && $request->filled('lohang_id')) {
            $rawItems = [[
                'lohang_id' => $request->input('lohang_id'),
                'so_luong_huy' => $request->input('so_luong_huy', 1)
            ]];
        }

        $parsedItems = [];
        foreach ($rawItems as $it) {
            $loId = $it['lohang_id'] ?? $it['lo_hang_id'] ?? $it['loHangId'] ?? null;
            if (!is_numeric($loId)) {
                $foundLo = LoHang::where('maLoHang', $loId)->first();
                $loId = $foundLo?->id;
            }
            $qty = (int)($it['so_luong_huy'] ?? $it['soLuongHuy'] ?? 1);
            if ($loId && $qty > 0) {
                $parsedItems[] = [
                    'lohang_id' => (int)$loId,
                    'so_luong_huy' => $qty,
                ];
            }
        }

        if (empty($parsedItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng chọn ít nhất 1 lô hàng hợp lệ cần hủy',
            ], 422);
        }

        try {
            DB::statement('CALL sp_create_bien_ban_huy(?, ?, ?, ?, ?, @bienban_id, @ma_bien_ban, @error)', [
                (int)$cuaHangId,
                (int)$nguoiLapId,
                $lyDo,
                $autoSign,
                json_encode($parsedItems),
            ]);

            $res = DB::select('SELECT @bienban_id AS bienban_id, @ma_bien_ban AS ma_bien_ban, @error AS error')[0];

            if ($res->error) {
                return response()->json(['success' => false, 'message' => $res->error], 500);
            }

            $disposal = BienBanHuy::find($res->bienban_id);

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => $autoSign ? 'Tạo biên bản hủy & tự động ký duyệt thành công' : 'Tạo biên bản hủy thành công, đã gửi yêu cầu trình ký',
                'data' => $disposal ? $disposal->load(['cuaHang', 'nguoiLap', 'chuKySos', 'chiTiets.loHang', 'chiTiets.sanPham']) : null,
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
