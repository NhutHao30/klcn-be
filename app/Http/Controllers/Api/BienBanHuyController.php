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
            $disposal = null;
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
                    throw new \Exception($res->error);
                }

                $disposal = BienBanHuy::find($res->bienban_id);
            } catch (\Throwable $spEx) {
                \Illuminate\Support\Facades\Log::warning('sp_create_bien_ban_huy failed, falling back to Eloquent: ' . $spEx->getMessage());

                $disposal = DB::transaction(function () use ($cuaHangId, $nguoiLapId, $lyDo, $autoSign, $parsedItems) {
                    $maBB = 'BBH-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));

                    $bb = BienBanHuy::create([
                        'maBienBan' => $maBB,
                        'cuaHangId' => (int)$cuaHangId,
                        'nguoiLapId' => (int)$nguoiLapId,
                        'ngayLap' => now(),
                        'lyDo' => $lyDo,
                        'tongGiaTriHuy' => 0,
                        'trangThai' => $autoSign ? 1 : 0,
                    ]);

                    $user = \App\Models\NguoiDung::with('taikhoan')->find($nguoiLapId);
                    $userName = $user?->hoTen ?? 'Quản lý chi nhánh';
                    $savedSig = $user?->chuKySo ?? $user?->getRawOriginal('chuKySo') ?? $user?->taikhoan?->chuKySo ?? null;

                    \App\Models\ChuKySo::create([
                        'maChuKy' => 'SIG-HUY-' . $bb->id . '-R1-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)),
                        'loaiTaiLieu' => 2,
                        'taiLieuId' => $bb->id,
                        'vaiTroKy' => 1,
                        'nguoiKyId' => null,
                        'tenNguoiKy' => 'Nhân viên kế toán',
                        'trangThai' => 0,
                    ]);

                    \App\Models\ChuKySo::create([
                        'maChuKy' => 'SIG-HUY-' . $bb->id . '-R2-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)),
                        'loaiTaiLieu' => 2,
                        'taiLieuId' => $bb->id,
                        'vaiTroKy' => 2,
                        'nguoiKyId' => null,
                        'tenNguoiKy' => 'Nhân viên kho',
                        'trangThai' => 0,
                    ]);

                    \App\Models\ChuKySo::create([
                        'maChuKy' => 'SIG-HUY-' . $bb->id . '-R3-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6)),
                        'loaiTaiLieu' => 2,
                        'taiLieuId' => $bb->id,
                        'vaiTroKy' => 3,
                        'nguoiKyId' => $autoSign ? $nguoiLapId : null,
                        'tenNguoiKy' => $autoSign ? $userName : 'Quản lý chi nhánh',
                        'digitalSignatureHash' => $autoSign ? ($savedSig ?: hash('sha256', "BBH_{$bb->id}_R3_" . time())) : null,
                        'publicKey' => $autoSign ? 'BRANCH_MANAGER_SIGNATURE' : null,
                        'trangThai' => $autoSign ? 1 : 0,
                        'ngayKy' => $autoSign ? now() : null,
                        'ghiChu' => $autoSign ? 'Quản lý chi nhánh đã ký duyệt biên bản hủy' : null,
                    ]);

                    $tongGiaTri = 0;
                    foreach ($parsedItems as $it) {
                        $lo = \App\Models\LoHang::find($it['lohang_id']);
                        if (!$lo) continue;
                        $qtyDestroy = min((int)$it['so_luong_huy'], (int)$lo->soLuongCon);
                        if ($qtyDestroy <= 0) $qtyDestroy = (int)$it['so_luong_huy'];

                        $donGia = (float)($lo->giaNhap ?? 0);
                        $thanhTien = $qtyDestroy * $donGia;
                        $tongGiaTri += $thanhTien;

                        \App\Models\ChiTietBienBanHuy::create([
                            'bienBanHuyId' => $bb->id,
                            'loHangId' => $lo->id,
                            'sanPhamId' => $lo->sanPhamId,
                            'soLuongHuy' => $qtyDestroy,
                            'donGia' => $donGia,
                            'thanhTien' => $thanhTien,
                        ]);

                        if ($autoSign) {
                            $newBatchQty = max(0, (int)$lo->soLuongCon - $qtyDestroy);
                            $lo->update([
                                'soLuongCon' => $newBatchQty,
                                'trangThai' => $newBatchQty === 0 ? 3 : 2,
                            ]);

                            $st = \App\Models\TonKhoChiNhanh::where('cuaHangId', $cuaHangId)
                                ->where('sanPhamId', $lo->sanPhamId)
                                ->first();
                            if ($st) {
                                $st->update([
                                    'soLuongTon' => max(0, (int)$st->soLuongTon - $qtyDestroy),
                                ]);
                            }

                            \App\Services\InventoryLedgerService::record(
                                (int)$cuaHangId,
                                (int)$lo->sanPhamId,
                                5,
                                -$qtyDestroy,
                                $maBB
                            );
                        }
                    }

                    $bb->update(['tongGiaTriHuy' => $tongGiaTri]);
                    return $bb;
                });
            }

            if ($autoSign && $disposal) {
                try {
                    $disposal->load('chiTiets');
                    foreach ($disposal->chiTiets as $ct) {
                        $hasLedger = \App\Models\NhatKyKho::where('cuaHangId', $disposal->cuaHangId)
                            ->where('sanPhamId', $ct->sanPhamId)
                            ->where('maThamChieu', $disposal->maBienBan)
                            ->exists();
                        if (!$hasLedger) {
                            \App\Services\InventoryLedgerService::record(
                                (int)$disposal->cuaHangId,
                                (int)$ct->sanPhamId,
                                5, // 5: Xuất hủy hàng
                                -(int)$ct->soLuongHuy,
                                $disposal->maBienBan
                            );
                        }
                    }
                } catch (\Throwable $eLedger) {}
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => $autoSign ? 'Tạo biên bản hủy & tự động ký duyệt thành công' : 'Nhân viên kho đã lập biên bản hủy thành công, đang chờ Quản lý chi nhánh phê duyệt',
                'data' => $disposal ? $disposal->load(['cuaHang', 'nguoiLap', 'chuKySos', 'chiTiets.loHang', 'chiTiets.sanPham']) : null,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi tạo biên bản hủy: ' . $e->getMessage()], 500);
        }
    }

    public function duyet(Request $request, $id)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }
        $vaiTroId = (int) ($user->vaiTroId ?? 0);
        $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;
        if ($vaiTroId !== 2 && $maRole !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ Quản lý chi nhánh mới có quyền phê duyệt biên bản hủy!',
            ], 403);
        }

        $disposal = BienBanHuy::with(['chiTiets.loHang', 'cuaHang', 'chuKySos'])->find($id);
        if (!$disposal) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy biên bản hủy'], 404);
        }

        if ((int)$disposal->trangThai === 1) {
            return response()->json(['success' => false, 'message' => 'Biên bản hủy này đã được phê duyệt trước đó!'], 400);
        }

        $profile = \App\Models\NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung;
        $tenNguoiKy = $profile?->hoTen ?? $user->username;
        $savedSig = $profile?->chuKySo ?? $profile?->getRawOriginal('chuKySo') ?? $user->chuKySo ?? $user->getRawOriginal('chuKySo') ?? null;

        DB::beginTransaction();
        try {
            $slot = ChuKySo::firstOrCreate(
                ['loaiTaiLieu' => 2, 'taiLieuId' => $disposal->id, 'vaiTroKy' => 3],
                [
                    'maChuKy' => 'SIG-BBH-' . $disposal->id . '-R3-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                    'tenNguoiKy' => $tenNguoiKy,
                    'trangThai' => 0,
                ]
            );

            DB::statement('CALL sp_sign_role(?, ?, ?, ?, ?, @is_fully_signed, @error)', [
                $slot->id,
                $profile?->id ?? $user->id,
                $savedSig ?: json_encode(['docId' => $disposal->id, 'signer' => $tenNguoiKy, 'type' => 'BBH']),
                'BRANCH_MANAGER_SIGNATURE',
                'Quản lý chi nhánh đã phê duyệt biên bản hủy'
            ]);

            $res = DB::select('SELECT @is_fully_signed AS is_fully_signed, @error AS error')[0];
            if ($res->error) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $res->error], 400);
            }

            $slot->update(['tenNguoiKy' => $tenNguoiKy]);
            DB::commit();

            // Đảm bảo sổ cái được ghi nhận
            try {
                $freshBB = BienBanHuy::with('chiTiets')->find($disposal->id);
                if ($freshBB) {
                    foreach ($freshBB->chiTiets as $ct) {
                        $hasLedger = \App\Models\NhatKyKho::where('cuaHangId', $freshBB->cuaHangId)
                            ->where('sanPhamId', $ct->sanPhamId)
                            ->where('maThamChieu', $freshBB->maBienBan)
                            ->exists();
                        if (!$hasLedger) {
                            \App\Services\InventoryLedgerService::record(
                                (int)$freshBB->cuaHangId,
                                (int)$ct->sanPhamId,
                                5, // 5: Xuất hủy hàng
                                -(int)$ct->soLuongHuy,
                                $freshBB->maBienBan
                            );
                        }
                    }
                }
            } catch (\Throwable $eLedger) {}

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'Quản lý chi nhánh đã phê duyệt biên bản hủy thành công! Tồn kho đã được xuất hủy.',
                'data' => $disposal->fresh(['cuaHang', 'nguoiLap', 'chuKySos', 'chiTiets.loHang', 'chiTiets.sanPham'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi khi phê duyệt: ' . $e->getMessage()], 500);
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
