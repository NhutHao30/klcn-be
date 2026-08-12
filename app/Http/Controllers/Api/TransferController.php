<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\NhanVien;

class TransferController extends Controller
{
    private function getUserStore()
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            return null;
        }
        if ($user->MAROLE == 0) {
            return 'ADMIN';
        } // Admin tổng
        $nhanVien = NhanVien::where('USERNAME', $user->USERNAME)->first();
        return $nhanVien ? $nhanVien->MACUAHANG : null;
    }

    public function getStoreEmployees(Request $request)
    {
        $storeId = $request->query('store_id');
        if (!$storeId) {
            $userStore = $this->getUserStore();
            $storeId = $userStore === 'ADMIN' ? null : $userStore;
        }

        $query = \App\Models\NhanVien::where('TRANGTHAI', '!=', 'Nghỉ việc');
        if ($storeId && $storeId !== 'ADMIN') {
            $query->where('MACUAHANG', $storeId);
        }

        return response()->json($query->get(['USERNAME', 'HOTEN', 'MACUAHANG']));
    }

    // 1. Lấy danh sách yêu cầu nhập hàng
    public function getRequests(Request $request)
    {
        $storeId = $this->getUserStore();
        if (!$storeId) {
            return response()->json(['error' => 'Không tìm thấy chi nhánh'], 403);
        }

        $query = DB::table('yeucau_nhaphang')
            ->join('cuahang', 'yeucau_nhaphang.MACUAHANG_YEUCAU', '=', 'cuahang.MACUAHANG')
            ->leftJoin('nhanvien', 'yeucau_nhaphang.NGUOITAO', '=', 'nhanvien.USERNAME')
            ->select('yeucau_nhaphang.*', 'cuahang.TENCUAHANG', 'nhanvien.HOTEN as TEN_NGUOITAO');

        $requests = $query->orderBy('created_at', 'desc')->get();

        foreach ($requests as $req) {
            $req->details = DB::table('chitiet_yeucau')
                ->join('sanpham', 'chitiet_yeucau.MASP', '=', 'sanpham.MASP')
                ->where('ID_YEUCAU', $req->ID_YEUCAU)
                ->select('chitiet_yeucau.*', 'sanpham.TENSP')
                ->get();
        }

        return response()->json($requests);
    }

    // 2. Tạo yêu cầu nhập hàng
    public function createRequest(Request $request)
    {
        $storeId = $this->getUserStore();
        if (!$storeId || $storeId === 'ADMIN') {
            return response()->json(['error' => 'Chỉ quản lý chi nhánh mới được tạo yêu cầu'], 403);
        }

        $request->validate([
            'GHICHU' => 'nullable|string',
            'details' => 'required|array',
            'details.*.MASP' => 'required|string',
            'details.*.SOLUONG' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();
        try {
            $user = Auth::guard('api')->user();
            $id = DB::table('yeucau_nhaphang')->insertGetId([
                'MACUAHANG_YEUCAU' => $storeId,
                'NGUOITAO' => $user->USERNAME,
                'TRANGTHAI' => 'Chờ tiếp nhận',
                'GHICHU' => $request->GHICHU,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            foreach ($request->details as $item) {
                DB::table('chitiet_yeucau')->insert([
                    'ID_YEUCAU' => $id,
                    'MASP' => $item['MASP'],
                    'SOLUONG_YEUCAU' => $item['SOLUONG'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            DB::commit();
            return response()->json(['message' => 'Tạo yêu cầu thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 3. Tiếp nhận yêu cầu (Chi nhánh khác tiếp nhận)
    public function acceptRequest(Request $request, $id)
    {
        $storeId = $this->getUserStore();
        if (!$storeId || $storeId === 'ADMIN') {
            return response()->json(['error' => 'Chỉ quản lý chi nhánh mới được tiếp nhận'], 403);
        }

        $req = DB::table('yeucau_nhaphang')->where('ID_YEUCAU', $id)->first();
        if (!$req || $req->TRANGTHAI !== 'Chờ tiếp nhận') {
            return response()->json(['error' => 'Yêu cầu không hợp lệ hoặc đã được xử lý'], 400);
        }

        if ($req->MACUAHANG_YEUCAU == $storeId) {
            return response()->json(['error' => 'Không thể tiếp nhận yêu cầu của chính chi nhánh mình'], 400);
        }

        return $this->processTransfer($request, $id, $storeId, $req->MACUAHANG_YEUCAU);
    }

    // 4. Điều phối yêu cầu (Dành cho Admin)
    public function coordinateRequest(Request $request, $id)
    {
        $storeId = $this->getUserStore();
        if ($storeId !== 'ADMIN') {
            return response()->json(['error' => 'Chỉ Admin mới có quyền điều phối'], 403);
        }

        $request->validate(['MACUAHANG_XUAT' => 'required|integer']);

        $req = DB::table('yeucau_nhaphang')->where('ID_YEUCAU', $id)->first();
        if (!$req || $req->TRANGTHAI !== 'Chờ tiếp nhận') {
            return response()->json(['error' => 'Yêu cầu không hợp lệ'], 400);
        }

        return $this->processTransfer($request, $id, $request->MACUAHANG_XUAT, $req->MACUAHANG_YEUCAU);
    }

    private function processTransfer($request, $reqId, $sourceStore, $destStore)
    {
        $request->validate([
            'HINHTHUC_VANCHUYEN' => 'required|string',
            'MA_VANDON' => 'nullable|string',
            'NGUOI_VANCHUYEN' => 'nullable|string',
            'PHI_VANCHUYEN' => 'nullable|numeric'
        ]);

        DB::beginTransaction();
        try {
            $user = Auth::guard('api')->user();

            // Kiểm tra tồn kho chi nhánh xuất trước khi lập phiếu
            $details = DB::table('chitiet_yeucau')->where('ID_YEUCAU', $reqId)->get();
            $insufficientProducts = [];

            foreach ($details as $d) {
                $soLuongYeuCau = (int) $d->SOLUONG_YEUCAU;
                $tonKho = DB::table('tonkho_cuahang')
                    ->where('MACUAHANG', $sourceStore)
                    ->where('MASP', $d->MASP)
                    ->first();

                $soLuongTon = $tonKho ? (int) $tonKho->SOLUONG_TON : 0;

                if ($soLuongTon < $soLuongYeuCau) {
                    $tenSP = DB::table('sanpham')->where('MASP', $d->MASP)->value('TENSP') ?? $d->MASP;
                    $insufficientProducts[] = "{$tenSP} (Cần: {$soLuongYeuCau}, Hiện có: {$soLuongTon})";
                }
            }

            if (!empty($insufficientProducts)) {
                DB::rollBack();
                $tenCuaHang = DB::table('cuahang')->where('MACUAHANG', $sourceStore)->value('TENCUAHANG') ?? "Chi nhánh {$sourceStore}";
                return response()->json([
                    'error' => "Chi nhánh \"{$tenCuaHang}\" không đủ tồn kho cho các sản phẩm sau:\n- " . implode("\n- ", $insufficientProducts)
                ], 400);
            }

            // Đổi trạng thái yêu cầu
            DB::table('yeucau_nhaphang')->where('ID_YEUCAU', $reqId)->update([
                'TRANGTHAI' => 'Đã được tiếp nhận',
                'updated_at' => now()
            ]);

            // Tạo phiếu điều chuyển
            $phieuId = DB::table('phieu_dieuchuyen')->insertGetId([
                'MACUAHANG_XUAT' => $sourceStore,
                'MACUAHANG_NHAP' => $destStore,
                'ID_YEUCAU' => $reqId,
                'NGUOITAO' => $user->USERNAME,
                'TRANGTHAI' => 'Đang xử lý',
                'HINHTHUC_VANCHUYEN' => $request->HINHTHUC_VANCHUYEN,
                'MA_VANDON' => $request->MA_VANDON,
                'NGUOI_VANCHUYEN' => $request->NGUOI_VANCHUYEN,
                'PHI_VANCHUYEN' => $request->PHI_VANCHUYEN ?? 0,
                'GHICHU' => $request->GHICHU ?? '',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Copy chi tiết
            foreach ($details as $d) {
                DB::table('chitiet_dieuchuyen')->insert([
                    'ID_PHIEU' => $phieuId,
                    'MASP' => $d->MASP,
                    'SOLUONG_XUAT' => $d->SOLUONG_YEUCAU,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            if ($request->HINHTHUC_VANCHUYEN === 'Giao Hàng Nhanh (GHN)') {
                \App\Jobs\PushTransferToGhnJob::dispatch($phieuId);
            }

            return response()->json(['message' => 'Tạo phiếu điều chuyển thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 5. Lấy danh sách phiếu điều chuyển
    public function getTransfers(Request $request)
    {
        $storeId = $this->getUserStore();
        if (!$storeId) {
            return response()->json(['error' => 'Không tìm thấy chi nhánh'], 403);
        }

        $query = DB::table('phieu_dieuchuyen')
            ->join('cuahang as CH_XUAT', 'phieu_dieuchuyen.MACUAHANG_XUAT', '=', 'CH_XUAT.MACUAHANG')
            ->join('cuahang as CH_NHAP', 'phieu_dieuchuyen.MACUAHANG_NHAP', '=', 'CH_NHAP.MACUAHANG')
            ->leftJoin('nhanvien as NV_TAO', 'phieu_dieuchuyen.NGUOITAO', '=', 'NV_TAO.USERNAME')
            ->leftJoin('nhanvien as NV_NHAN', 'phieu_dieuchuyen.NGUOINHAN', '=', 'NV_NHAN.USERNAME')
            ->select('phieu_dieuchuyen.*', 'CH_XUAT.TENCUAHANG as TEN_CH_XUAT', 'CH_NHAP.TENCUAHANG as TEN_CH_NHAP', 'NV_TAO.HOTEN as TEN_NGUOITAO', 'NV_NHAN.HOTEN as TEN_NGUOINHAN');

        if ($storeId !== 'ADMIN') {
            $query->where(function ($q) use ($storeId) {
                $q->where('MACUAHANG_XUAT', $storeId)
                  ->orWhere('MACUAHANG_NHAP', $storeId);
            });
        }

        $transfers = $query->orderBy('created_at', 'desc')->get();

        foreach ($transfers as $t) {
            $t->details = DB::table('chitiet_dieuchuyen')
                ->join('sanpham', 'chitiet_dieuchuyen.MASP', '=', 'sanpham.MASP')
                ->where('ID_PHIEU', $t->ID_PHIEU)
                ->select('chitiet_dieuchuyen.*', 'sanpham.TENSP')
                ->get();
        }

        return response()->json($transfers);
    }

    // 6. Cập nhật trạng thái phiếu điều chuyển
    public function updateTransferStatus(Request $request, $id)
    {
        $request->validate(['TRANGTHAI' => 'required|string']);
        $storeId = $this->getUserStore();

        $phieu = DB::table('phieu_dieuchuyen')->where('ID_PHIEU', $id)->first();
        if (!$phieu) {
            return response()->json(['error' => 'Không tìm thấy phiếu'], 404);
        }

        DB::beginTransaction();
        try {
            $user = Auth::guard('api')->user();

            // Nếu chuyển thành Đang vận chuyển -> Phải là chi nhánh xuất hoặc admin
            if ($request->TRANGTHAI === 'Đang vận chuyển') {
                if ($storeId !== 'ADMIN' && $phieu->MACUAHANG_XUAT != $storeId) {
                    return response()->json(['error' => 'Chỉ chi nhánh xuất mới được giao hàng'], 403);
                }

                // Trừ kho chi nhánh xuất
                $details = DB::table('chitiet_dieuchuyen')->where('ID_PHIEU', $id)->get();
                foreach ($details as $d) {
                    $soLuongXuat = (int) $d->SOLUONG_XUAT;

                    // Kiểm tra tồn kho trước khi trừ
                    $tonKho = DB::table('tonkho_cuahang')
                        ->where('MACUAHANG', $phieu->MACUAHANG_XUAT)
                        ->where('MASP', $d->MASP)
                        ->first();

                    if (!$tonKho || $tonKho->SOLUONG_TON < $soLuongXuat) {
                        DB::rollBack();
                        $tenSP = DB::table('sanpham')->where('MASP', $d->MASP)->value('TENSP') ?? $d->MASP;
                        return response()->json(['error' => "Sản phẩm \"{$tenSP}\" không đủ tồn kho để xuất (Cần: {$soLuongXuat}, Hiện có: " . ($tonKho ? $tonKho->SOLUONG_TON : 0) . ")"], 400);
                    }

                    DB::table('tonkho_cuahang')
                        ->where('MACUAHANG', $phieu->MACUAHANG_XUAT)
                        ->where('MASP', $d->MASP)
                        ->update([
                            'SOLUONG_TON' => DB::raw("SOLUONG_TON - {$soLuongXuat}"),
                            'updated_at' => now()
                        ]);
                }
            }

            // Nếu chuyển thành Đã nhận hàng -> Phải là chi nhánh nhận
            if ($request->TRANGTHAI === 'Đã nhận hàng') {
                if ($storeId !== 'ADMIN' && $phieu->MACUAHANG_NHAP != $storeId) {
                    return response()->json(['error' => 'Chỉ chi nhánh nhận mới được xác nhận'], 403);
                }

                // Cập nhật số lượng nhận thực tế
                $request->validate(['details' => 'required|array']);

                foreach ($request->details as $d) {
                    $soLuongNhan = (int) $d['SOLUONG_NHAN'];
                    $masp = $d['MASP'];

                    DB::table('chitiet_dieuchuyen')
                        ->where('ID_CHITIET', $d['ID_CHITIET'])
                        ->update([
                            'SOLUONG_NHAN' => $soLuongNhan,
                            'LYDO_HAOHUT' => $d['LYDO_HAOHUT'] ?? null,
                            'updated_at' => now()
                        ]);

                    // Cộng kho chi nhánh nhận
                    $tonKho = DB::table('tonkho_cuahang')
                        ->where('MACUAHANG', $phieu->MACUAHANG_NHAP)
                        ->where('MASP', $masp)
                        ->first();

                    if ($tonKho) {
                        DB::table('tonkho_cuahang')
                            ->where('MACUAHANG', $phieu->MACUAHANG_NHAP)
                            ->where('MASP', $masp)
                            ->update([
                                'SOLUONG_TON' => DB::raw("SOLUONG_TON + {$soLuongNhan}"),
                                'updated_at' => now()
                            ]);
                    } else {
                        DB::table('tonkho_cuahang')->insert([
                            'MACUAHANG' => $phieu->MACUAHANG_NHAP,
                            'MASP' => $masp,
                            'SOLUONG_TON' => $soLuongNhan,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    \Log::info("Transfer #{$id}: Cộng kho chi nhánh {$phieu->MACUAHANG_NHAP}, SP: {$masp}, SL nhận: {$soLuongNhan}");
                }

                DB::table('phieu_dieuchuyen')->where('ID_PHIEU', $id)->update(['NGUOINHAN' => $user->USERNAME]);
            }

            DB::table('phieu_dieuchuyen')->where('ID_PHIEU', $id)->update([
                'TRANGTHAI' => $request->TRANGTHAI,
                'updated_at' => now()
            ]);

            // Xóa cache sản phẩm để cập nhật tồn kho ngay lập tức
            try {
                \Illuminate\Support\Facades\Cache::tags(['products'])->flush();
            } catch (\Exception $e) {
                // Bỏ qua lỗi cache
            }

            DB::commit();
            return response()->json(['message' => 'Cập nhật trạng thái thành công']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Transfer #{$id} updateStatus error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
