<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BienBanHuy;
use App\Models\ChuKySo;
use App\Models\HoaDonNhap;
use App\Models\NhatKyKho;
use App\Models\TonKhoChiNhanh;
use App\Models\NguoiDung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SignatureController extends Controller
{
    /**
     * Lấy user đang đăng nhập
     */
    private function getAuthUser(Request $request)
    {
        $user = Auth::guard('api')->user();
        if (!$user) {
            $token = $request->bearerToken();
            if ($token) {
                try {
                    $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->toUser();
                } catch (\Exception $e) {}
            }
        }
        return $user;
    }

    /**
     * Ánh xạ vaiTroId của tài khoản sang vị trí ký (vaiTroKy):
     * - vaiTroId = 7 (Nhân viên kế toán) -> vaiTroKy = 1
     * - vaiTroId = 4 (Nhân viên kho)     -> vaiTroKy = 2
     * - vaiTroId = 2 (Quản lý chi nhánh) -> vaiTroKy = 3
     * - Admin (vaiTroId = 1) và Giám đốc (vaiTroId = 8): KHÔNG tham gia ký duyệt chứng từ
     */
    public static function mapUserRoleToSlotRole($user): ?int
    {
        if (!$user) {
            return null;
        }
        $vaiTroId = (int) ($user->vaiTroId ?? 0);
        $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;
        $username = strtolower((string) ($user->username ?? $user->USERNAME ?? ''));

        // Admin tuyệt đối không được tham gia ký duyệt
        if ($vaiTroId === 1 || $maRole === 0 || $username === 'admin') {
            return null;
        }
        // Giám đốc không tham gia ký duyệt đơn nhập hàng / biên bản hủy của chi nhánh
        if ($vaiTroId === 8 || $maRole === 5) {
            return null;
        }

        return match ($vaiTroId) {
            7 => 1, // Nhân viên kế toán
            4 => 2, // Nhân viên kho
            2 => 3, // Quản lý chi nhánh
            default => null,
        };
    }

    /**
     * Kiểm tra chứng từ đã đủ chữ ký theo quy tắc mới chưa:
     * - Nếu Quản lý chi nhánh (vaiTroKy = 3) đã ký -> Hoàn tất ngay, không cần các role khác ký nữa.
     * - Nếu Quản lý chi nhánh chưa ký -> Cần đủ chữ ký của cả Nhân viên kế toán (vaiTroKy = 1) VÀ Nhân viên kho (vaiTroKy = 2).
     */
    public static function checkDocumentFullySigned($sigs, int $docTrangThai = 0): bool
    {
        if ($docTrangThai === 1) {
            return true;
        }
        $sigKeToan = $sigs->firstWhere('vaiTroKy', 1);
        $sigThuKho = $sigs->firstWhere('vaiTroKy', 2);
        $sigQuanLy = $sigs->firstWhere('vaiTroKy', 3);

        $quanLySigned = $sigQuanLy && (int) $sigQuanLy->trangThai === 1;
        $khoSigned = $sigThuKho && (int) $sigThuKho->trangThai === 1;
        $keToanSigned = $sigKeToan && (int) $sigKeToan->trangThai === 1;

        return $quanLySigned || ($khoSigned && $keToanSigned);
    }

    /**
     * Danh sách chứng từ chờ duyệt & đã ký số cho cả Website và Mobile
     */
    public function getPendingSignatures(Request $request)
    {
        \App\Services\InventoryLedgerService::ensureSchema();
        $user = $this->getAuthUser($request);
        $profile = $user ? (NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung) : null;
        $userStoreId = $profile?->cuaHangId;
        $mySlotRole = self::mapUserRoleToSlotRole($user);

        // 1. Hóa Đơn Nhập (loaiTaiLieu = 1)
        $hdQuery = HoaDonNhap::with([
            'nhaCungCap',
            'cuaHang',
            'nguoiNhap.taikhoan',
            'chuKySos.nguoiKy',
            'chiTiets.sanPham',
            'chiTiets.loHang'
        ])->latest('id');

        if ($userStoreId && in_array((int) ($user?->vaiTroId ?? 0), [2, 4, 7])) {
            $hdQuery->where('cuaHangId', $userStoreId);
        }

        $hdNhaps = $hdQuery->get();

        $hdNhapPending = [];
        $hdNhapSigned = [];
        $hdNhapAll = [];

        foreach ($hdNhaps as $hd) {
            $sigs = $hd->chuKySos;
            $sigKeToan = $sigs->where('vaiTroKy', 1)->last() ?? $sigs->firstWhere('vaiTroKy', 1);
            $sigThuKho = $sigs->where('vaiTroKy', 2)->last() ?? $sigs->firstWhere('vaiTroKy', 2);
            $sigQuanLy = $sigs->where('vaiTroKy', 3)->last() ?? $sigs->firstWhere('vaiTroKy', 3);

            $createdByBranchManager = (int) ($hd->nguoiNhap?->taikhoan?->vaiTroId ?? 0) === 2;
            $quanLySigned = ($sigQuanLy && (int) $sigQuanLy->trangThai === 1) || $createdByBranchManager;
            $khoSigned = $sigThuKho && (int) $sigThuKho->trangThai === 1;
            $keToanSigned = $sigKeToan && (int) $sigKeToan->trangThai === 1;

            $isFullySigned = $quanLySigned || self::checkDocumentFullySigned($sigs, (int) $hd->trangThai);
            $hasAnySigned = $quanLySigned || $khoSigned || $keToanSigned;

            if ($createdByBranchManager && (int) $hd->trangThai === 0) {
                try {
                    $hd->update(['trangThai' => 1]);
                } catch (\Throwable $eAuto) {}
            }

            $chiTietMapped = $hd->chiTiets->map(function ($ct) {
                $qty = (int) $ct->soLuong;
                $qtyReal = (int) ($ct->loHang?->soLuongCon ?? $qty);
                $price = (float) $ct->donGiaNhap;
                return [
                    'MASP' => $ct->sanPhamId,
                    'TENSP' => $ct->sanPham?->tenSanPham ?? 'Sản phẩm',
                    'sanpham' => ['TENSP' => $ct->sanPham?->tenSanPham ?? 'Sản phẩm'],
                    'SOLUONG' => $qty,
                    'SOLUONGTCT' => $qty,
                    'SOLUONGTN' => $qtyReal > 0 ? $qtyReal : $qty,
                    'DONGIA' => $price,
                    'DONGIANHAP' => $price,
                    'THANHTIEN' => (float) $ct->thanhTien,
                    'MALOHAN' => $ct->loHang?->maLoHang ?? 'Lô',
                    'HSD' => $ct->loHang?->hsd,
                    'HSD_LO' => $ct->loHang?->hsd,
                ];
            });

            $item = [
                'id' => $hd->id,
                'MAHDNHAP' => $hd->maHoaDonNhap ?: 'HDN-' . $hd->id,
                'maHoaDonNhap' => $hd->maHoaDonNhap ?: 'HDN-' . $hd->id,
                'NGAYLAP' => $hd->ngayNhap ?: $hd->createdAt,
                'ngayNhap' => $hd->ngayNhap ?: $hd->createdAt,
                'created_at' => $hd->ngayNhap ?: $hd->createdAt,
                'TONGTIEN' => (float) $hd->tongTien,
                'TONGTIEN_NHAP' => (float) $hd->tongTien,
                'tongTien' => (float) $hd->tongTien,
                'TRANGTHAI' => $isFullySigned ? 1 : 0,
                'trangThai' => $isFullySigned ? 1 : 0,
                'TRANGTHAI_KY' => $isFullySigned ? 'hoan_tat_ky' : ($hasAnySigned ? 'da_ky_mot_phan' : 'cho_ky'),
                'PHUONG_THUC_DUYET' => $quanLySigned
                    ? 'Quản lý chi nhánh đã ký duyệt (Không cần Kho & Kế toán ký thêm)'
                    : (($khoSigned && $keToanSigned) ? 'Nhân viên kho & Kế toán đã ký đủ' : 'Chờ Quản lý chi nhánh ký HOẶC (Nhân viên kho + Kế toán cùng ký)'),
                'cuaHangId' => $hd->cuaHangId,
                'nhanvien' => ['HOTEN' => $hd->nguoiNhap?->hoTen ?? 'Quản lý chi nhánh'],
                'nhacungcap' => ['TENNCC' => $hd->nhaCungCap?->tenNcc ?? $hd->nhaCungCap?->tenNhaCungCap ?? 'Nhà cung cấp'],
                'cuahang' => ['TENCUAHANG' => $hd->cuaHang?->tenCuaHang ?? 'Chi nhánh'],
                'KY_THU_KHO' => $khoSigned ? ($sigThuKho->digitalSignatureHash ?: 'SIGNED') : null,
                'KY_KE_TOAN' => $keToanSigned ? ($sigKeToan->digitalSignatureHash ?: 'SIGNED') : null,
                'KY_QUAN_LY' => $quanLySigned ? ($sigQuanLy->digitalSignatureHash ?: 'SIGNED') : null,
                'KY_GIAM_DOC' => $quanLySigned ? ($sigQuanLy->digitalSignatureHash ?: 'SIGNED') : null,
                'NGUOI_KY_THU_KHO' => $khoSigned ? ($sigThuKho?->nguoiKy?->hoTen ?? $sigThuKho?->tenNguoiKy) : null,
                'NGUOI_KY_KE_TOAN' => $keToanSigned ? ($sigKeToan?->nguoiKy?->hoTen ?? $sigKeToan?->tenNguoiKy) : null,
                'NGUOI_KY_QUAN_LY' => $quanLySigned ? ($sigQuanLy?->nguoiKy?->hoTen ?? $sigQuanLy?->tenNguoiKy) : null,
                'NGUOI_KY_GIAM_DOC' => $quanLySigned ? ($sigQuanLy?->nguoiKy?->hoTen ?? $sigQuanLy?->tenNguoiKy) : null,
                'NGAY_KY_THU_KHO' => $sigThuKho?->ngayKy,
                'NGAY_KY_KE_TOAN' => $sigKeToan?->ngayKy,
                'NGAY_KY_QUAN_LY' => $sigQuanLy?->ngayKy,
                'NGAY_KY_GIAM_DOC' => $sigQuanLy?->ngayKy,
                'chi_tiet' => $chiTietMapped,
                'chitiets' => $chiTietMapped,
                'slots' => $sigs,
            ];

            $hdNhapAll[] = $item;
            if ($isFullySigned) {
                $hdNhapSigned[] = $item;
            } else {
                $hdNhapPending[] = $item;
            }
        }

        // 2. Biên Bản Hủy (loaiTaiLieu = 2)
        $bbQuery = BienBanHuy::with([
            'cuaHang',
            'nguoiLap.taikhoan',
            'chuKySos.nguoiKy',
            'chiTiets.sanPham',
            'chiTiets.loHang'
        ])->latest('id');

        if ($userStoreId && in_array((int) ($user?->vaiTroId ?? 0), [2, 4, 7])) {
            $bbQuery->where('cuaHangId', $userStoreId);
        }

        $bbHuys = $bbQuery->get();

        $bbHuyPending = [];
        $bbHuySigned = [];
        $bbHuyAll = [];

        foreach ($bbHuys as $bb) {
            $sigs = $bb->chuKySos;
            $sigKeToan = $sigs->where('vaiTroKy', 1)->last() ?? $sigs->firstWhere('vaiTroKy', 1);
            $sigThuKho = $sigs->where('vaiTroKy', 2)->last() ?? $sigs->firstWhere('vaiTroKy', 2);
            $sigQuanLy = $sigs->where('vaiTroKy', 3)->last() ?? $sigs->firstWhere('vaiTroKy', 3);

            $createdByBranchManager = (int) ($bb->nguoiLap?->taikhoan?->vaiTroId ?? 0) === 2;
            $quanLySigned = ($sigQuanLy && (int) $sigQuanLy->trangThai === 1) || $createdByBranchManager;
            $khoSigned = $sigThuKho && (int) $sigThuKho->trangThai === 1;
            $keToanSigned = $sigKeToan && (int) $sigKeToan->trangThai === 1;

            $isFullySigned = $quanLySigned || self::checkDocumentFullySigned($sigs, (int) $bb->trangThai);
            $hasAnySigned = $quanLySigned || $khoSigned || $keToanSigned;

            if ($createdByBranchManager && (int) $bb->trangThai === 0) {
                try {
                    $bb->update(['trangThai' => 1]);
                } catch (\Throwable $eAuto) {}
            }
            $qtyHuy = (int) $bb->chiTiets->sum('soLuongHuy');

            $item = [
                'id' => $bb->id,
                'MABB' => $bb->maBienBan ?: 'BBH-' . $bb->id,
                'maBienBan' => $bb->maBienBan ?: 'BBH-' . $bb->id,
                'NGAY_HUY' => $bb->ngayLap ?: $bb->createdAt,
                'NGAYLAP' => $bb->ngayLap ?: $bb->createdAt,
                'created_at' => $bb->ngayLap ?: $bb->createdAt,
                'LYDO_HUY' => $bb->lyDo ?: 'Hao hụt/Hết hạn',
                'SO_LUONG_HUY' => $qtyHuy,
                'SOLUONG_HUY' => $qtyHuy,
                'MALOHAN' => $bb->chiTiets->first()?->loHang?->maLoHang ?: 'Lô bánh',
                'TONGGIATRI_HUY' => (float) $bb->tongGiaTriHuy,
                'TRANGTHAI' => $isFullySigned ? 1 : 0,
                'trangThai' => $isFullySigned ? 1 : 0,
                'TRANGTHAI_KY' => $isFullySigned ? 'hoan_tat_ky' : ($hasAnySigned ? 'da_ky_mot_phan' : 'cho_ky'),
                'PHUONG_THUC_DUYET' => $quanLySigned
                    ? 'Quản lý chi nhánh đã ký duyệt (Không cần Kho & Kế toán ký thêm)'
                    : (($khoSigned && $keToanSigned) ? 'Nhân viên kho & Kế toán đã ký đủ' : 'Chờ Quản lý chi nhánh ký HOẶC (Nhân viên kho + Kế toán cùng ký)'),
                'cuaHangId' => $bb->cuaHangId,
                'nhanvien' => ['HOTEN' => $bb->nguoiLap?->hoTen ?? 'Nhân viên kho'],
                'cuahang' => ['TENCUAHANG' => $bb->cuaHang?->tenCuaHang ?? 'Chi nhánh'],
                'KY_THU_KHO' => $khoSigned ? ($sigThuKho->digitalSignatureHash ?: 'SIGNED') : null,
                'KY_KE_TOAN' => $keToanSigned ? ($sigKeToan->digitalSignatureHash ?: 'SIGNED') : null,
                'KY_QUAN_LY' => $quanLySigned ? ($sigQuanLy->digitalSignatureHash ?: 'SIGNED') : null,
                'KY_GIAM_DOC' => $quanLySigned ? ($sigQuanLy->digitalSignatureHash ?: 'SIGNED') : null,
                'NGUOI_KY_THU_KHO' => $khoSigned ? ($sigThuKho?->nguoiKy?->hoTen ?? $sigThuKho?->tenNguoiKy) : null,
                'NGUOI_KY_KE_TOAN' => $keToanSigned ? ($sigKeToan?->nguoiKy?->hoTen ?? $sigKeToan?->tenNguoiKy) : null,
                'NGUOI_KY_QUAN_LY' => $quanLySigned ? ($sigQuanLy?->nguoiKy?->hoTen ?? $sigQuanLy?->tenNguoiKy) : null,
                'NGUOI_KY_GIAM_DOC' => $quanLySigned ? ($sigQuanLy?->nguoiKy?->hoTen ?? $sigQuanLy?->tenNguoiKy) : null,
                'NGAY_KY_THU_KHO' => $sigThuKho?->ngayKy,
                'NGAY_KY_KE_TOAN' => $sigKeToan?->ngayKy,
                'NGAY_KY_QUAN_LY' => $sigQuanLy?->ngayKy,
                'NGAY_KY_GIAM_DOC' => $sigQuanLy?->ngayKy,
                'chi_tiet' => $bb->chiTiets->map(function ($ct) {
                    return [
                        'TENSP' => $ct->sanPham?->tenSanPham ?? 'Sản phẩm',
                        'SOLUONG_HUY' => (int) $ct->soLuongHuy,
                        'DONGIA' => (float) $ct->donGia,
                        'THANHTIEN' => (float) $ct->thanhTien,
                        'MALOHAN' => $ct->loHang?->maLoHang ?? 'Lô',
                    ];
                }),
                'slots' => $sigs,
            ];

            $bbHuyAll[] = $item;
            if ($isFullySigned) {
                $bbHuySigned[] = $item;
            } else {
                $bbHuyPending[] = $item;
            }
        }

        $totalPending = count($hdNhapPending) + count($bbHuyPending);

        return response()->json([
            'status' => true,
            'success' => true,
            'my_slot_role' => $mySlotRole,
            'can_sign' => $mySlotRole !== null,
            'total_pending' => $totalPending,
            'hd_nhap' => [
                'pending' => $hdNhapPending,
                'signed' => $hdNhapSigned,
                'all' => $hdNhapAll,
            ],
            'bien_ban_huy' => [
                'pending' => $bbHuyPending,
                'signed' => $bbHuySigned,
                'all' => $bbHuyAll,
            ],
            'data' => [
                'hd_nhap' => $hdNhapPending,
                'bien_ban_huy' => $bbHuyPending,
            ],
        ]);
    }

    /**
     * Xác nhận ký số văn bản (Hóa đơn nhập, Biên bản hủy)
     * Quy tắc phân quyền nghiêm ngặt:
     * - Role Admin (vaiTroId = 1) KHÔNG được phép tham gia ký duyệt.
     * - Role Giám đốc (vaiTroId = 8) KHÔNG tham gia ký duyệt đơn nhập hàng / biên bản hủy của chi nhánh.
     * - Mỗi tài khoản chỉ được ký đúng vị trí role của mình:
     *   + Quản lý chi nhánh (vaiTroId = 2) -> vị trí Quản lý chi nhánh (vaiTroKy = 3)
     *   + Nhân viên kho (vaiTroId = 4)     -> vị trí Nhân viên kho (vaiTroKy = 2)
     *   + Nhân viên kế toán (vaiTroId = 7) -> vị trí Nhân viên kế toán (vaiTroKy = 1)
     * - Nếu Quản lý chi nhánh đã ký duyệt -> Đơn hoàn tất ngay, không cần các role khác ký nữa.
     * - Nếu không có chữ ký của Quản lý chi nhánh -> Cần đủ chữ ký của cả Nhân viên kho VÀ Nhân viên kế toán.
     */
    public function signDocument(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Bạn chưa đăng nhập!',
                'message' => 'Bạn chưa đăng nhập!',
            ], 401);
        }

        $vaiTroId = (int) ($user->vaiTroId ?? 0);
        $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;
        $username = strtolower((string) ($user->username ?? $user->USERNAME ?? ''));

        // Chặn tuyệt đối role Admin
        if ($vaiTroId === 1 || $maRole === 0 || $username === 'admin') {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Tài khoản Quản trị viên (Admin) không được phép tham gia vào việc ký duyệt chứng từ!',
                'message' => 'Tài khoản Quản trị viên (Admin) không được phép tham gia vào việc ký duyệt chứng từ!',
            ], 403);
        }

        // Chặn role Giám đốc ký vào vị trí chi nhánh
        if ($vaiTroId === 8 || $maRole === 5) {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Ban Giám Đốc chỉ phê duyệt các đơn yêu cầu nhập hàng khi cần thiết, không ký trực tiếp vào vị trí của chi nhánh!',
                'message' => 'Ban Giám Đốc chỉ phê duyệt các đơn yêu cầu nhập hàng khi cần thiết, không ký trực tiếp vào vị trí của chi nhánh!',
            ], 403);
        }

        $allowedSlotRole = self::mapUserRoleToSlotRole($user);
        if (!$allowedSlotRole) {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Tài khoản của bạn không thuộc vai trò được phép ký duyệt (chỉ Quản lý chi nhánh, Nhân viên kho, Nhân viên kế toán).',
                'message' => 'Tài khoản của bạn không thuộc vai trò được phép ký duyệt (chỉ Quản lý chi nhánh, Nhân viên kho, Nhân viên kế toán).',
            ], 403);
        }

        // Kiểm tra vị trí ký được yêu cầu có khớp đúng role của tài khoản không
        $vaitroInput = $request->input('vaitro_ky') ?? $request->input('role');
        $roleMap = [
            'ke_toan' => 1,
            'thu_kho' => 2,
            'quan_ly' => 3,
            'giam_doc' => 3,
        ];
        $requestedRole = is_numeric($vaitroInput) ? (int) $vaitroInput : ($roleMap[$vaitroInput] ?? $allowedSlotRole);

        if ($requestedRole !== $allowedSlotRole) {
            $roleNames = [
                1 => 'Nhân viên kế toán',
                2 => 'Nhân viên kho',
                3 => 'Quản lý chi nhánh',
            ];
            $myRoleLabel = $roleNames[$allowedSlotRole] ?? 'vai trò của mình';
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => "Mỗi tài khoản chỉ được ký đúng vị trí vai trò của mình ({$myRoleLabel})! Bạn không được phép ký vào vị trí của vai trò khác.",
                'message' => "Mỗi tài khoản chỉ được ký đúng vị trí vai trò của mình ({$myRoleLabel})! Bạn không được phép ký vào vị trí của vai trò khác.",
            ], 403);
        }

        $targetRole = $allowedSlotRole;

        // Lấy profile người dùng (kèm relation taikhoan để đọc chuKySo đã lưu)
        $profile = NguoiDung::with('taikhoan')->where('taiKhoanId', $user->id)->first() ?? $user->nguoidung;
        $signerName = $profile?->hoTen ?? $user->username ?? 'Người phê duyệt';

        // Đọc chữ ký đã lưu từ profile hoặc từ tài khoản (fallback)
        $savedSignature = $profile?->chuKySo
            ?? $profile?->getRawOriginal('chuKySo')
            ?? $user->chuKySo
            ?? $user->getRawOriginal('chuKySo')
            ?? null;

        // Xác định loại tài liệu
        $loaiPhieuInput = $request->input('loai_phieu') ?? $request->input('loai_tai_lieu');
        $loaiTaiLieu = 1; // Default: HoaDonNhap
        if (in_array($loaiPhieuInput, [2, '2', 'bien_ban_huy', 'huy', 'disposal'], true)) {
            $loaiTaiLieu = 2;
        }

        // Xác định ID tài liệu và chứng từ
        $docIdInput = $request->input('id') ?? $request->input('tai_lieu_id') ?? $request->input('documentId');
        $doc = null;
        if ($loaiTaiLieu === 1) {
            $doc = is_numeric($docIdInput)
                ? HoaDonNhap::find((int) $docIdInput)
                : HoaDonNhap::where('maHoaDonNhap', $docIdInput)->first();
        } else {
            $doc = is_numeric($docIdInput)
                ? BienBanHuy::find((int) $docIdInput)
                : BienBanHuy::where('maBienBan', $docIdInput)->first();
        }

        if (!$doc) {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Không tìm thấy chứng từ cần ký',
                'message' => 'Không tìm thấy chứng từ cần ký',
            ], 404);
        }

        // Kiểm tra chi nhánh của tài khoản có khớp với chi nhánh của chứng từ không
        if ($profile?->cuaHangId && $doc->cuaHangId && (int) $profile->cuaHangId !== (int) $doc->cuaHangId) {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Bạn chỉ được phép ký duyệt chứng từ thuộc chi nhánh của mình!',
                'message' => 'Bạn chỉ được phép ký duyệt chứng từ thuộc chi nhánh của mình!',
            ], 403);
        }

        $taiLieuId = $doc->id;
        $existingSigs = ChuKySo::where('loaiTaiLieu', $loaiTaiLieu)->where('taiLieuId', $taiLieuId)->get();

        if (self::checkDocumentFullySigned($existingSigs, (int) $doc->trangThai)) {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Chứng từ này đã được phê duyệt hoàn tất trước đó, không cần ký thêm!',
                'message' => 'Chứng từ này đã được phê duyệt hoàn tất trước đó, không cần ký thêm!',
            ], 400);
        }

        // Tìm hoặc tạo slot chữ ký đúng với vaiTroKy của người dùng
        $slot = $existingSigs->firstWhere('vaiTroKy', $targetRole);
        if (!$slot) {
            $slot = ChuKySo::create([
                'maChuKy' => 'SIG-' . ($loaiTaiLieu === 1 ? 'HDN' : 'HUY') . '-' . $taiLieuId . '-R' . $targetRole . '-' . strtoupper(substr(md5(uniqid()), 0, 6)),
                'loaiTaiLieu' => $loaiTaiLieu,
                'taiLieuId' => $taiLieuId,
                'vaiTroKy' => $targetRole,
                'tenNguoiKy' => $signerName,
                'trangThai' => 0,
            ]);
        }

        if ((int) $slot->trangThai === 1) {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Vị trí vai trò của bạn đã được ký xác nhận trước đó!',
                'message' => 'Vị trí vai trò của bạn đã được ký xác nhận trước đó!',
            ], 400);
        }

        $signatureImage = $request->input('signature_data')
            ?? $request->input('chu_ky')
            ?? $request->input('chuKySo')
            ?? $savedSignature
            ?? null;

        // Nếu người dùng vừa ký tay mà trong hồ sơ chưa lưu chữ ký thì tự động lưu lại cho lần sau
        if (!empty($signatureImage) && empty($savedSignature) && str_starts_with((string) $signatureImage, 'data:image/')) {
            try {
                $user->update(['chuKySo' => $signatureImage]);
                if ($profile) {
                    $profile->update(['chuKySo' => $signatureImage]);
                }
            } catch (\Throwable $eSaveSig) {}
        }

        $rawPayload = ($signatureImage && (str_starts_with((string) $signatureImage, 'data:image/') || str_starts_with((string) $signatureImage, '<svg') || str_starts_with((string) $signatureImage, 'http')))
            ? $signatureImage
            : json_encode([
                'docType' => $loaiTaiLieu,
                'docId' => $taiLieuId,
                'role' => $targetRole,
                'signer' => $signerName,
                'time' => now()->toIso8601String(),
            ]);

        try {
            \App\Services\InventoryLedgerService::ensureSchema();

            $sigHash = ($signatureImage && (str_starts_with((string) $signatureImage, 'data:image/') || str_starts_with((string) $signatureImage, '<svg') || str_starts_with((string) $signatureImage, 'http')))
                ? $signatureImage
                : hash('sha256', $rawPayload . "_ROLE_{$targetRole}_" . time());

            $slot->update([
                'nguoiKyId' => $profile?->id ?? 1,
                'tenNguoiKy' => $signerName,
                'digitalSignatureHash' => $sigHash,
                'publicKey' => 'SYSTEM_RSA_PUBLIC_KEY',
                'trangThai' => 1,
                'ngayKy' => now(),
                'ghiChu' => $request->input('ghi_chu', "Đã ký duyệt bởi {$signerName}"),
            ]);

            $otherSlots = \App\Models\ChuKySo::where('loaiTaiLieu', $loaiTaiLieu)
                ->where('taiLieuId', $taiLieuId)
                ->get();
            $r1Signed = $otherSlots->where('vaiTroKy', 1)->where('trangThai', 1)->isNotEmpty();
            $r2Signed = $otherSlots->where('vaiTroKy', 2)->where('trangThai', 1)->isNotEmpty();
            $r3Signed = $otherSlots->where('vaiTroKy', 3)->where('trangThai', 1)->isNotEmpty();

            $isFullySigned = $r3Signed || ($r1Signed && $r2Signed);

            if ($isFullySigned) {
                if ($loaiTaiLieu == 1) { // HoaDonNhap
                    $hdn = \App\Models\HoaDonNhap::with('chiTiets')->find($taiLieuId);
                    if ($hdn && $hdn->trangThai == 0) {
                        $hdn->update(['trangThai' => 1]);
                        foreach ($hdn->chiTiets as $ct) {
                            $st = \App\Models\TonKhoChiNhanh::firstOrCreate(
                                ['cuaHangId' => $hdn->cuaHangId, 'sanPhamId' => $ct->sanPhamId],
                                ['soLuongTon' => 0]
                            );
                            $st->increment('soLuongTon', (int)$ct->soLuong);
                            \App\Services\InventoryLedgerService::record(
                                (int)$hdn->cuaHangId,
                                (int)$ct->sanPhamId,
                                4,
                                (int)$ct->soLuong,
                                $hdn->maHoaDonNhap
                            );
                        }
                    }
                } elseif ($loaiTaiLieu == 2) { // BienBanHuy
                    $bb = \App\Models\BienBanHuy::with('chiTiets')->find($taiLieuId);
                    if ($bb && $bb->trangThai == 0) {
                        $bb->update(['trangThai' => 1]);
                        foreach ($bb->chiTiets as $ct) {
                            $lo = \App\Models\LoHang::find($ct->loHangId);
                            if ($lo) {
                                $newQty = max(0, (int)$lo->soLuongCon - (int)$ct->soLuongHuy);
                                $lo->update([
                                    'soLuongCon' => $newQty,
                                    'trangThai' => $newQty === 0 ? 3 : 2,
                                ]);
                            }
                            $st = \App\Models\TonKhoChiNhanh::where('cuaHangId', $bb->cuaHangId)
                                ->where('sanPhamId', $ct->sanPhamId)
                                ->first();
                            if ($st) {
                                $st->update(['soLuongTon' => max(0, (int)$st->soLuongTon - (int)$ct->soLuongHuy)]);
                            }
                            \App\Services\InventoryLedgerService::record(
                                (int)$bb->cuaHangId,
                                (int)$ct->sanPhamId,
                                5,
                                -(int)$ct->soLuongHuy,
                                $bb->maBienBan
                            );
                        }
                    }
                }
            }

            if ($isFullySigned) {
                \App\Http\Controllers\Api\ProductController::clearProductCache();
                \Illuminate\Support\Facades\Cache::flush();
            }

            $successMessage = $isFullySigned
                ? ($targetRole === 3
                    ? 'Quản lý chi nhánh đã ký phê duyệt! Chứng từ hoàn tất ngay lập tức (không cần các vai trò khác ký thêm) và đã cập nhật tồn kho.'
                    : 'Nhân viên kho và Nhân viên kế toán đã ký đủ! Chứng từ đã được phê duyệt hoàn tất và cập nhật tồn kho.')
                : ($targetRole === 2
                    ? 'Nhân viên kho đã ký xác nhận! Đang chờ Nhân viên kế toán ký tiếp (hoặc Quản lý chi nhánh ký duyệt).'
                    : 'Nhân viên kế toán đã ký xác nhận! Đang chờ Nhân viên kho ký tiếp (hoặc Quản lý chi nhánh ký duyệt).');

            return response()->json([
                'status' => true,
                'success' => true,
                'message' => $successMessage,
                'is_fully_signed' => $isFullySigned,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'error' => 'Lỗi khi ký số: ' . $e->getMessage(),
                'message' => 'Lỗi khi ký số: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin chữ ký cá nhân
     */
    public function updateProfileSignature(Request $request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        $sigData = $request->input('signature_data')
            ?? $request->input('chu_ky')
            ?? $request->input('chuKySo')
            ?? $request->input('signature')
            ?? $request->input('data');

        if (!empty($sigData)) {
            $user->update(['chuKySo' => $sigData]);
        }

        $profile = NguoiDung::where('taiKhoanId', $user->id)->first();
        if ($profile) {
            $profile->update([
                'chuKySo' => $sigData,
            ]);
        }

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'Cập nhật chữ ký số cá nhân thành công',
            'signature' => $sigData,
            'chuKySo' => $sigData,
            'chu_ky' => $sigData,
        ]);
    }

    /**
     * Ký cho 1 role cụ thể
     */
    public function signRole(Request $request)
    {
        return $this->signDocument($request);
    }

    /**
     * Tra cứu chữ ký số của tài liệu
     */
    public function verifyDocumentSignatures(Request $request)
    {
        $validated = $request->validate([
            'loai_tai_lieu' => 'required',
            'tai_lieu_id' => 'required',
        ]);

        $signatures = ChuKySo::with('nguoiKy')
            ->where('loaiTaiLieu', $validated['loai_tai_lieu'])
            ->where('taiLieuId', $validated['tai_lieu_id'])
            ->get();

        $hoanTat = self::checkDocumentFullySigned($signatures);
        $signedCount = $signatures->where('trangThai', 1)->count();

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => $hoanTat ? 'Chứng từ đã hoàn tất ký duyệt hợp lệ' : 'Chứng từ đang chờ ký duyệt',
            'data' => [
                'so_vai_tro_da_ky' => $signedCount,
                'hoan_tat' => $hoanTat,
                'danh_sach_chu_ky' => $signatures,
            ],
        ]);
    }
}
