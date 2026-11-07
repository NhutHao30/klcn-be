<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\YeuCauDieuChuyen;
use App\Models\ChiTietDieuChuyen;
use App\Models\PhieuDieuChuyen;
use App\Models\ChiTietPhieuDieuChuyen;
use App\Models\TonKhoChiNhanh;
use App\Models\NguoiDung;

class TransferController extends Controller
{
    /**
     * Lấy user hiện tại với nguoidung relation
     */
    private function getCurrentUser()
    {
        $user = Auth::guard('api')->user();
        if (!$user) return null;
        return $user;
    }

    /**
     * Lấy danh sách nhân viên của 1 cửa hàng
     */
    public function getStoreEmployees(Request $request)
    {
        $storeId = $request->input('store_id');

        $query = DB::table('nguoidung')
            ->join('taikhoan', 'nguoidung.taiKhoanId', '=', 'taikhoan.id')
            ->where('nguoidung.loaiNguoiDung', 1) // nhân viên
            ->select('nguoidung.id', 'nguoidung.hoTen AS HOTEN', 'taikhoan.username AS USERNAME');

        if ($storeId) {
            $query->where('nguoidung.cuaHangId', $storeId);
        }

        return response()->json($query->get());
    }

    // ================================================================
    //  YÊU CẦU NHẬP HÀNG (Requests)
    // ================================================================

    /**
     * Danh sách tất cả yêu cầu nhập hàng
     */
    public function getRequests(Request $request)
    {
        $user = $this->getCurrentUser();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $requests = YeuCauDieuChuyen::with(['cuahangNhap', 'nguoiTao', 'chitiet.sanpham'])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($req) {
                return [
                    'ID_YEUCAU' => $req->id,
                    'MACUAHANG_YEUCAU' => $req->cuaHangNhapId,
                    'TENCUAHANG' => $req->cuahangNhap?->tenCuaHang ?? $req->cuahangNhap?->TENCUAHANG ?? $req->cuahangNhap?->ten_cuahang ?? 'N/A',
                    'NGUOITAO' => $req->nguoiTaoId,
                    'TEN_NGUOITAO' => $req->nguoiTao?->hoTen ?? 'N/A',
                    'TRANGTHAI' => $this->mapRequestStatus($req->trangThai),
                    'TRANGTHAI_CODE' => $req->trangThai,
                    'GHICHU' => $req->ghiChu,
                    'NGAYTAO' => $req->createdAt,
                    'details' => $req->chitiet->map(function ($d) {
                        return [
                            'ID_CHITIET' => $d->id,
                            'MASP' => $d->sanPhamId,
                            'TENSP' => $d->sanpham?->tenSanPham ?? 'N/A',
                            'HINHANH' => $d->sanpham?->hinhAnh ?? '',
                            'SOLUONG_YEUCAU' => $d->soLuongYeuCau,
                        ];
                    }),
                    'SO_PHIEU' => PhieuDieuChuyen::where('yeuCauId', $req->id)->count(),
                ];
            });

        return response()->json($requests);
    }

    /**
     * Tạo yêu cầu nhập hàng mới
     */
    public function createRequest(Request $request)
    {
        $user = $this->getCurrentUser();
        if (!$user || !$user->nguoidung) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nd = $user->nguoidung;
        $cuaHangId = $request->input('MACUAHANG_NHAP') ?? $request->input('MACUAHANG_YEUCAU') ?? $request->input('cuaHangNhapId') ?? $nd?->cuaHangId ?? 1;

        if (!$cuaHangId) {
            $cuaHangId = 1;
        }

        $details = $request->input('details') ?? $request->input('CHI_TIET') ?? $request->input('chi_tiet') ?? [];
        if (empty($details)) {
            return response()->json(['error' => 'Vui lòng chọn ít nhất 1 sản phẩm'], 400);
        }

        DB::beginTransaction();
        try {
            $cuaHangXuatId = $request->input('MACUAHANG_XUAT') ?? $request->input('cuaHangXuatId') ?? null;
            $yeuCau = YeuCauDieuChuyen::create([
                'cuaHangXuatId' => $cuaHangXuatId ?: null,
                'cuaHangNhapId' => (int)$cuaHangId,
                'nguoiTaoId' => $nd?->id ?? $user->id,
                'trangThai' => 0, // Chờ tiếp nhận
                'ghiChu' => $request->input('GHICHU', $request->input('ghiChu', '')),
            ]);

            foreach ($details as $d) {
                $spId = $this->resolveSanPhamId($d['sanPhamId'] ?? $d['sanpham_id'] ?? $d['id'] ?? $d['MASP'] ?? null);
                if (!$spId) {
                    continue;
                }
                ChiTietDieuChuyen::create([
                    'yeuCauId' => $yeuCau->id,
                    'sanPhamId' => $spId,
                    'soLuongYeuCau' => $d['SOLUONG'] ?? $d['SOLUONG_CHUYEN'] ?? $d['soLuongYeuCau'] ?? $d['soLuong'] ?? 1,
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Tạo yêu cầu nhập hàng thành công!',
                'data' => $yeuCau->load('chitiet.sanpham')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Chi nhánh tiếp nhận yêu cầu → tạo phiếu điều chuyển
     * (Quản lý chi nhánh khác chủ động nhận đơn)
     */
    public function acceptRequest(Request $request, $id)
    {
        $user = $this->getCurrentUser();
        if (!$user || !$user->nguoidung) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nd = $user->nguoidung;
        $cuaHangXuat = $nd->cuaHangId;

        $yeuCau = YeuCauDieuChuyen::with('chitiet.sanpham')->find($id);
        if (!$yeuCau) return response()->json(['error' => 'Yêu cầu không tồn tại'], 404);

        if ($yeuCau->cuaHangNhapId == $cuaHangXuat) {
            return response()->json(['error' => 'Không thể tự điều chuyển cho chính cửa hàng của bạn'], 400);
        }

        // Get selected products from request body
        $selectedItems = $request->input('items', []);
        if (empty($selectedItems)) {
            // If no items specified, take all from request
            $selectedItems = $yeuCau->chitiet->map(fn($d) => [
                'MASP' => $d->sanPhamId,
                'SOLUONG' => $d->soLuongYeuCau,
            ])->toArray();
        }

        // Validate stock
        foreach ($selectedItems as $item) {
            $spId = $this->resolveSanPhamId($item['MASP'] ?? $item['sanPhamId'] ?? $item['id'] ?? null);
            if (!$spId) continue;
            $tonKho = TonKhoChiNhanh::where('cuaHangId', $cuaHangXuat)
                ->where('sanPhamId', $spId)
                ->first();
            $available = $tonKho ? $tonKho->soLuongTon : 0;
            if ($available < $item['SOLUONG']) {
                $sp = \App\Models\SanPham::find($spId);
                $spName = $sp ? $sp->tenSanPham : "Sản phẩm #{$spId}";
                return response()->json([
                    'error' => "Kho không đủ hàng cho '{$spName}' (Có: {$available}, Cần: {$item['SOLUONG']})"
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            $phieu = PhieuDieuChuyen::create([
                'yeuCauId' => $yeuCau->id,
                'cuaHangXuatId' => $cuaHangXuat,
                'cuaHangNhapId' => $yeuCau->cuaHangNhapId,
                'nguoiTaoId' => $nd->id,
                'trangThai' => 'Đang xử lý',
                'hinhThucVanChuyen' => $request->input('HINHTHUC_VANCHUYEN', 'Tự vận chuyển'),
                'maVanDon' => $request->input('MA_VANDON', ''),
                'nguoiVanChuyen' => $request->input('NGUOI_VANCHUYEN', ''),
                'phiVanChuyen' => $request->input('PHI_VANCHUYEN', 0),
                'ghiChu' => $request->input('GHICHU', ''),
            ]);

            foreach ($selectedItems as $item) {
                $spId = $this->resolveSanPhamId($item['MASP'] ?? $item['sanPhamId'] ?? $item['id'] ?? null);
                if (!$spId) continue;
                ChiTietPhieuDieuChuyen::create([
                    'phieuId' => $phieu->id,
                    'sanPhamId' => $spId,
                    'soLuongXuat' => $item['SOLUONG'],
                ]);
            }

            // Update request status to "Đã được tiếp nhận" if first response
            if ($yeuCau->trangThai === 0) {
                $yeuCau->update(['trangThai' => 1]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Tiếp nhận thành công! Phiếu điều chuyển đã được tạo.',
                'data' => $phieu->load('chitiet.sanpham')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Giám đốc chỉ định chi nhánh xuất hàng
     * (Khi không có chi nhánh nào tự nhận)
     */
    public function coordinateRequest(Request $request, $id)
    {
        $user = $this->getCurrentUser();
        $isDirectorOrAdmin = $user && (
            in_array((int)$user->vaiTroId, [1, 8]) ||
            in_array((int)$user->MAROLE, [0, 5]) ||
            strtolower($user->username ?? '') === 'admin'
        );

        if (!$isDirectorOrAdmin) {
            return response()->json(['error' => 'Chỉ Ban Giám Đốc hoặc Quản trị viên mới có quyền điều phối và chỉ định chi nhánh xuất hàng'], 403);
        }

        $nd = $user->nguoidung;
        $cuaHangXuat = $request->input('MACUAHANG_XUAT');
        if (!$cuaHangXuat) {
            return response()->json(['error' => 'Vui lòng chọn chi nhánh xuất hàng'], 400);
        }

        $yeuCau = YeuCauDieuChuyen::with('chitiet')->find($id);
        if (!$yeuCau) return response()->json(['error' => 'Yêu cầu không tồn tại'], 404);

        if ($yeuCau->cuaHangNhapId == $cuaHangXuat) {
            return response()->json(['error' => 'Chi nhánh xuất không thể trùng với chi nhánh yêu cầu'], 400);
        }

        // Get selected items or default to all
        $selectedItems = $request->input('items', []);
        if (empty($selectedItems)) {
            $selectedItems = $yeuCau->chitiet->map(fn($d) => [
                'MASP' => $d->sanPhamId,
                'SOLUONG' => $d->soLuongYeuCau,
            ])->toArray();
        }

        DB::beginTransaction();
        try {
            $phieu = PhieuDieuChuyen::create([
                'yeuCauId' => $yeuCau->id,
                'cuaHangXuatId' => $cuaHangXuat,
                'cuaHangNhapId' => $yeuCau->cuaHangNhapId,
                'nguoiTaoId' => $nd->id,
                'trangThai' => 'Đang xử lý',
                'hinhThucVanChuyen' => $request->input('HINHTHUC_VANCHUYEN', 'Tự vận chuyển'),
                'maVanDon' => $request->input('MA_VANDON', ''),
                'nguoiVanChuyen' => $request->input('NGUOI_VANCHUYEN', ''),
                'phiVanChuyen' => $request->input('PHI_VANCHUYEN', 0),
                'ghiChu' => $request->input('GHICHU', 'Được giám đốc chỉ định điều chuyển'),
            ]);

            foreach ($selectedItems as $item) {
                $spId = $this->resolveSanPhamId($item['MASP'] ?? $item['sanPhamId'] ?? $item['id'] ?? null);
                if (!$spId) continue;
                ChiTietPhieuDieuChuyen::create([
                    'phieuId' => $phieu->id,
                    'sanPhamId' => $spId,
                    'soLuongXuat' => $item['SOLUONG'],
                ]);
            }

            // Mark request as being processed
            if ($yeuCau->trangThai === 0) {
                $yeuCau->update([
                    'trangThai' => 1,
                    'nguoiDuyetId' => $nd->id,
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Điều phối thành công! Phiếu điều chuyển đã được tạo.',
                'data' => $phieu->load('chitiet.sanpham')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    // ================================================================
    //  PHIẾU ĐIỀU CHUYỂN (Transfer Vouchers)
    // ================================================================

    /**
     * Danh sách phiếu điều chuyển
     */
    public function getTransfers(Request $request)
    {
        $user = $this->getCurrentUser();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);

        $transfers = PhieuDieuChuyen::with([
                'cuahangXuat', 'cuahangNhap', 'nguoiTao', 'yeucau', 'chitiet.sanpham'
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'ID_PHIEU' => $t->id,
                    'ID_YEUCAU' => $t->yeuCauId,
                    'MACUAHANG_XUAT' => $t->cuaHangXuatId,
                    'MACUAHANG_NHAP' => $t->cuaHangNhapId,
                    'TEN_CH_XUAT' => $t->cuahangXuat?->ten_cuahang ?? $t->cuahangXuat?->TENCUAHANG ?? 'N/A',
                    'TEN_CH_NHAP' => $t->cuahangNhap?->ten_cuahang ?? $t->cuahangNhap?->TENCUAHANG ?? 'N/A',
                    'TEN_NGUOITAO' => $t->nguoiTao?->hoTen ?? 'N/A',
                    'TRANGTHAI' => $t->trangThai,
                    'HINHTHUC_VANCHUYEN' => $t->hinhThucVanChuyen,
                    'MA_VANDON' => $t->maVanDon,
                    'NGUOI_VANCHUYEN' => $t->nguoiVanChuyen,
                    'PHI_VANCHUYEN' => $t->phiVanChuyen,
                    'GHICHU' => $t->ghiChu,
                    'NGAYTAO' => $t->createdAt,
                    'details' => $t->chitiet->map(function ($d) {
                        return [
                            'ID_CHITIET' => $d->id,
                            'MASP' => $d->sanPhamId,
                            'TENSP' => $d->sanpham?->tenSanPham ?? 'N/A',
                            'HINHANH' => $d->sanpham?->hinhAnh ?? '',
                            'SOLUONG_XUAT' => $d->soLuongXuat,
                            'SOLUONG_NHAN' => $d->soLuongNhan,
                            'LYDO_HAOHUT' => $d->lyDoHaoHut,
                        ];
                    }),
                ];
            });

        return response()->json($transfers);
    }

    /**
     * Cập nhật trạng thái phiếu điều chuyển
     * Đang xử lý → Đang vận chuyển → Đã nhận hàng
     */
    public function updateTransferStatus(Request $request, $id)
    {
        $user = $this->getCurrentUser();
        if (!$user || !$user->nguoidung) {
            return response()->json(['error' => 'Chưa đăng nhập'], 401);
        }

        $nd = $user->nguoidung;
        $isDirectorOrAdmin = (
            in_array((int)$user->vaiTroId, [1, 8]) ||
            in_array((int)$user->MAROLE, [0, 5]) ||
            strtolower($user->username ?? '') === 'admin'
        );

        $phieu = PhieuDieuChuyen::with('chitiet')->find($id);
        if (!$phieu) return response()->json(['error' => 'Phiếu không tồn tại'], 404);

        $newStatus = $request->input('TRANGTHAI');

        DB::beginTransaction();
        try {
            if ($newStatus === 'Đang vận chuyển') {
                if (!$isDirectorOrAdmin && $nd->cuaHangId != $phieu->cuaHangXuatId) {
                    return response()->json(['error' => 'Chỉ chi nhánh xuất hàng mới có quyền bàn giao vận chuyển'], 403);
                }

                // Trừ tồn kho chi nhánh xuất
                foreach ($phieu->chitiet as $d) {
                    $tonKho = TonKhoChiNhanh::where('cuaHangId', $phieu->cuaHangXuatId)
                        ->where('sanPhamId', $d->sanPhamId)
                        ->first();
                    if ($tonKho) {
                        $tonKho->decrement('soLuongTon', $d->soLuongXuat);
                    }
                    \App\Services\InventoryLedgerService::record(
                        (int)$phieu->cuaHangXuatId,
                        (int)$d->sanPhamId,
                        2, // Chuyển kho (Xuất)
                        -$d->soLuongXuat,
                        $phieu->maVanDon ?: ("DC_" . $phieu->id),
                        $tonKho ? $tonKho->fresh()->soLuongTon : 0
                    );
                }
                $phieu->update(['trangThai' => 'Đang vận chuyển']);

            } elseif ($newStatus === 'Đã nhận hàng') {
                if (!$isDirectorOrAdmin && $nd->cuaHangId != $phieu->cuaHangNhapId) {
                    return response()->json(['error' => 'Chỉ chi nhánh nhận hàng mới có quyền xác nhận nhận hàng'], 403);
                }
                // Kiểm đếm: cập nhật số lượng thực nhận
                $details = $request->input('details', []);
                foreach ($details as $detail) {
                    $ct = ChiTietPhieuDieuChuyen::find($detail['ID_CHITIET']);
                    if ($ct) {
                        $soLuongNhan = $detail['SOLUONG_NHAN'] ?? $ct->soLuongXuat;
                        $ct->update([
                            'soLuongNhan' => $soLuongNhan,
                            'lyDoHaoHut' => $detail['LYDO_HAOHUT'] ?? null,
                        ]);

                        // Cộng tồn kho chi nhánh nhập
                        $tonKho = TonKhoChiNhanh::firstOrCreate(
                            ['cuaHangId' => $phieu->cuaHangNhapId, 'sanPhamId' => $ct->sanPhamId],
                            ['soLuongTon' => 0]
                        );
                        $tonKho->increment('soLuongTon', $soLuongNhan);

                        \App\Services\InventoryLedgerService::record(
                            (int)$phieu->cuaHangNhapId,
                            (int)$ct->sanPhamId,
                            3, // Chuyển kho (Nhập)
                            (int)$soLuongNhan,
                            $phieu->maVanDon ?: ("DC_" . $phieu->id),
                            $tonKho->fresh()->soLuongTon
                        );
                    }
                }
                $phieu->update(['trangThai' => 'Đã nhận hàng']);

                // Check if all phieu of this request are completed
                $yeuCau = $phieu->yeucau;
                if ($yeuCau) {
                    $allDone = PhieuDieuChuyen::where('yeuCauId', $yeuCau->id)
                        ->where('trangThai', '!=', 'Đã nhận hàng')
                        ->where('trangThai', '!=', 'Hủy')
                        ->doesntExist();
                    if ($allDone) {
                        $yeuCau->update(['trangThai' => 2]); // Hoàn thành
                    }
                }

            } elseif ($newStatus === 'Hủy') {
                $phieu->update(['trangThai' => 'Hủy']);
            }

            DB::commit();
            return response()->json([
                'message' => 'Cập nhật trạng thái thành công!',
                'data' => $phieu->fresh()->load('chitiet.sanpham')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Lấy tồn kho chi nhánh cho một danh sách sản phẩm
     */
    public function getStockInfo(Request $request)
    {
        $storeId = $request->input('store_id');
        $productIds = $request->input('product_ids', []);

        if (!$storeId) {
            return response()->json([]);
        }

        $stock = TonKhoChiNhanh::where('cuaHangId', $storeId)
            ->when(!empty($productIds), fn($q) => $q->whereIn('sanPhamId', $productIds))
            ->get()
            ->keyBy('sanPhamId')
            ->map(fn($t) => $t->soLuongTon);

        return response()->json($stock);
    }

    // ================================================================
    //  HELPERS
    // ================================================================

    private function resolveSanPhamId($raw)
    {
        if (is_numeric($raw)) {
            return (int)$raw;
        }
        if (empty($raw)) {
            return null;
        }
        $num = (int) preg_replace('/\D/', '', (string)$raw);
        if ($num > 0 && \App\Models\SanPham::where('id', $num)->exists()) {
            return $num;
        }
        $sp = \App\Models\SanPham::where('slug', (string)$raw)->orWhere('tenSanPham', (string)$raw)->first();
        if ($sp) {
            return (int)$sp->id;
        }
        return $num > 0 ? $num : null;
    }

    private function mapRequestStatus($code)
    {
        return match ($code) {
            0 => 'Chờ tiếp nhận',
            1 => 'Đã được tiếp nhận',
            2 => 'Hoàn thành',
            3 => 'Đã hủy',
            default => 'Không xác định',
        };
    }
}
