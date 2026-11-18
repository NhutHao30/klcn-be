<?php

namespace App\Services;

use App\Enums\PurchaseOrderSignatureRole;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Api\ProductController;
use App\Models\ChiTietHoaDonNhap;
use App\Models\HoaDonNhap;
use App\Models\LoHang;
use App\Models\NguoiDung;
use App\Models\PurchaseOrderSignature;
use App\Models\TaiKhoan;
use App\Models\TonKhoChiNhanh;
use App\Models\YeuCauDieuChuyen;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function resolveProfile(TaiKhoan $user): ?NguoiDung
    {
        return NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung;
    }

    public function resolveCreatorRole(TaiKhoan $user): string
    {
        $vaiTroId = (int) ($user->vaiTroId ?? 0);
        $maRole = isset($user->MAROLE) ? (int) $user->MAROLE : -1;

        if ($vaiTroId === 2 || $maRole === 1) {
            return 'MANAGER';
        }

        return 'WAREHOUSE';
    }

    /**
     * Giai đoạn 1: Tạo phiếu nhập hàng mới (KHÔNG cộng tồn kho ở bước này).
     * - Nhân viên kho tạo -> CHO_DUYET
     * - Quản lý chi nhánh tạo -> DA_DUYET
     */
    public function create(TaiKhoan $user, array $validated, ?int $yeuCauId = null): HoaDonNhap
    {
        InventoryLedgerService::ensureSchema();

        $profile = $this->resolveProfile($user);
        $creatorRole = $this->resolveCreatorRole($user);
        $initialStatus = $creatorRole === 'MANAGER'
            ? PurchaseOrderStatus::DA_DUYET
            : PurchaseOrderStatus::CHO_DUYET;

        $invoice = DB::transaction(function () use ($validated, $creatorRole, $initialStatus, $profile, $user) {
            $maHoaDon = 'HDN-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 10));

            $hd = HoaDonNhap::create([
                'maHoaDonNhap' => $maHoaDon,
                'nhaCungCapId' => (int) $validated['nhaCungCapId'],
                'cuaHangId' => (int) $validated['cuaHangId'],
                'nguoiNhapId' => (int) $validated['nguoi_nhap_id'],
                'nguoiTaoRole' => $creatorRole,
                'nguoiDuyetId' => $creatorRole === 'MANAGER' ? ($profile?->id ?? $user->id) : null,
                'thoiGianDuyet' => $creatorRole === 'MANAGER' ? now() : null,
                'ngayNhap' => now(),
                'tongTien' => 0,
                'ghiChu' => $validated['ghiChu'] ?? null,
                'trangThai' => $initialStatus->toLegacyInt(),
                'maTrangThai' => $initialStatus->value,
                'tonKhoDaCong' => false,
            ]);

            $tongTien = 0;
            foreach ($validated['items'] as $it) {
                $spId = (int) $it['sanPhamId'];
                $qty = (int) $it['soLuong'];
                $donGia = (float) $it['don_gia_nhap'];
                $thanhTien = $qty * $donGia;
                $tongTien += $thanhTien;

                $maLo = 'LH-' . $hd->id . '-' . $spId . '-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 4));
                $lo = LoHang::create([
                    'maLoHang' => $maLo,
                    'sanPhamId' => $spId,
                    'cuaHangId' => (int) $validated['cuaHangId'],
                    'nhaCungCapId' => (int) $validated['nhaCungCapId'],
                    'soLuongNhap' => $qty,
                    'soLuongCon' => 0, // Chưa nhập kho cho đến khi kiểm kê & đủ chữ ký HOAN_TAT
                    'ngaySanXuat' => $it['ngaySanXuat'] ?? null,
                    'hsd' => $it['hsd'],
                    'giaNhap' => $donGia,
                    'trangThai' => 0,
                ]);

                ChiTietHoaDonNhap::create([
                    'hoaDonNhapId' => $hd->id,
                    'loHangId' => $lo->id,
                    'sanPhamId' => $spId,
                    'soLuong' => $qty,
                    'soLuongThucNhan' => null,
                    'donGiaNhap' => $donGia,
                    'thanhTien' => $thanhTien,
                ]);
            }

            $hd->update(['tongTien' => $tongTien]);
            return $hd;
        });

        if ($yeuCauId) {
            $yeuCau = YeuCauDieuChuyen::find($yeuCauId);
            if ($yeuCau && (int) $yeuCau->trangThai === 0) {
                $yeuCau->update([
                    'trangThai' => 1,
                    'nguoiDuyetId' => $validated['nguoi_nhap_id'],
                ]);
            }
        }

        return $invoice->load(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'nguoiDuyet', 'signatures', 'chiTiets.loHang', 'chiTiets.sanPham']);
    }

    /**
     * Quản lý chi nhánh duyệt phiếu của Nhân viên kho: CHO_DUYET -> DA_DUYET
     */
    public function approve(HoaDonNhap $hoaDonNhap, TaiKhoan $user, ?string $ghiChu = null): HoaDonNhap
    {
        $profile = $this->resolveProfile($user);

        return DB::transaction(function () use ($hoaDonNhap, $profile, $user, $ghiChu) {
            $locked = HoaDonNhap::where('id', $hoaDonNhap->id)->lockForUpdate()->firstOrFail();
            if ($locked->resolvedStatus() !== PurchaseOrderStatus::CHO_DUYET) {
                throw ValidationException::withMessages([
                    'trangThai' => ['Chỉ có thể duyệt phiếu đang ở trạng thái Chờ duyệt.'],
                ]);
            }

            $updateData = [
                'maTrangThai' => PurchaseOrderStatus::DA_DUYET->value,
                'trangThai' => PurchaseOrderStatus::DA_DUYET->toLegacyInt(),
                'nguoiDuyetId' => $profile?->id ?? $user->id,
                'thoiGianDuyet' => now(),
            ];
            if ($ghiChu) {
                $updateData['ghiChu'] = $locked->ghiChu ? ($locked->ghiChu . "\n[Duyệt]: " . $ghiChu) : $ghiChu;
            }

            $locked->update($updateData);
            return $locked->fresh(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'nguoiDuyet', 'signatures', 'chiTiets.loHang', 'chiTiets.sanPham']);
        });
    }

    /**
     * Quản lý chi nhánh từ chối phiếu của Nhân viên kho: CHO_DUYET -> TU_CHOI
     */
    public function reject(HoaDonNhap $hoaDonNhap, TaiKhoan $user, string $lyDoTuChoi): HoaDonNhap
    {
        $profile = $this->resolveProfile($user);

        return DB::transaction(function () use ($hoaDonNhap, $profile, $user, $lyDoTuChoi) {
            $locked = HoaDonNhap::where('id', $hoaDonNhap->id)->lockForUpdate()->firstOrFail();
            if ($locked->resolvedStatus() !== PurchaseOrderStatus::CHO_DUYET) {
                throw ValidationException::withMessages([
                    'trangThai' => ['Chỉ có thể từ chối phiếu đang ở trạng thái Chờ duyệt.'],
                ]);
            }

            $locked->update([
                'maTrangThai' => PurchaseOrderStatus::TU_CHOI->value,
                'trangThai' => PurchaseOrderStatus::TU_CHOI->toLegacyInt(),
                'nguoiDuyetId' => $profile?->id ?? $user->id,
                'thoiGianDuyet' => now(),
                'lyDoTuChoi' => $lyDoTuChoi,
            ]);

            return $locked->fresh(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'nguoiDuyet', 'signatures', 'chiTiets.loHang', 'chiTiets.sanPham']);
        });
    }

    /**
     * Giai đoạn 2.1: Cập nhật số lượng thực nhận & ghi chú chênh lệch: DA_DUYET / DANG_KIEM_KE -> DANG_KIEM_KE
     */
    public function updateInspection(HoaDonNhap $hoaDonNhap, array $items, ?string $ghiChu = null): HoaDonNhap
    {
        return DB::transaction(function () use ($hoaDonNhap, $items, $ghiChu) {
            $locked = HoaDonNhap::with('chiTiets')->where('id', $hoaDonNhap->id)->lockForUpdate()->firstOrFail();
            $status = $locked->resolvedStatus();

            if (!in_array($status, [PurchaseOrderStatus::DA_DUYET, PurchaseOrderStatus::DANG_KIEM_KE], true)) {
                throw ValidationException::withMessages([
                    'trangThai' => ['Phiếu phải ở trạng thái Đã duyệt hoặc Đang kiểm kê mới có thể cập nhật kiểm kê.'],
                ]);
            }

            foreach ($items as $it) {
                $actualQty = (int) $it['soLuongThucNhan'];
                $note = $it['ghiChuChenhLech'] ?? null;

                $ct = null;
                if (!empty($it['chiTietId'])) {
                    $ct = $locked->chiTiets->firstWhere('id', (int) $it['chiTietId']);
                } elseif (!empty($it['loHangId'])) {
                    $ct = $locked->chiTiets->firstWhere('loHangId', (int) $it['loHangId']);
                } elseif (!empty($it['sanPhamId'])) {
                    $ct = $locked->chiTiets->firstWhere('sanPhamId', (int) $it['sanPhamId']);
                }

                if ($ct) {
                    $ct->update([
                        'soLuongThucNhan' => $actualQty,
                        'ghiChuChenhLech' => $note,
                    ]);
                }
            }

            $updateData = [
                'maTrangThai' => PurchaseOrderStatus::DANG_KIEM_KE->value,
                'trangThai' => PurchaseOrderStatus::DANG_KIEM_KE->toLegacyInt(),
            ];
            if ($ghiChu !== null && $ghiChu !== '') {
                $updateData['ghiChu'] = $ghiChu;
            }
            $locked->update($updateData);

            return $locked->fresh(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'nguoiDuyet', 'signatures', 'chiTiets.loHang', 'chiTiets.sanPham']);
        });
    }

    /**
     * Giai đoạn 2.2: Ký xác nhận theo vai trò.
     * Chỉ khi đủ chữ ký theo quy tắc mới chuyển sang HOAN_TAT và cộng tồn kho theo soLuongThucNhan (idempotent).
     */
    public function sign(
        HoaDonNhap $hoaDonNhap,
        TaiKhoan $user,
        PurchaseOrderSignatureRole $role,
        array $payload = []
    ): array {
        InventoryLedgerService::ensureSchema();

        $profile = $this->resolveProfile($user);
        $result = DB::transaction(function () use ($hoaDonNhap, $user, $profile, $role, $payload) {
            $locked = HoaDonNhap::with('chiTiets')->where('id', $hoaDonNhap->id)->lockForUpdate()->firstOrFail();
            $status = $locked->resolvedStatus();

            if (!in_array($status, [PurchaseOrderStatus::DA_DUYET, PurchaseOrderStatus::DANG_KIEM_KE], true)) {
                throw ValidationException::withMessages([
                    'trangThai' => ["Không thể ký nhận khi phiếu đang ở trạng thái '{$status->label()}'."],
                ]);
            }

            $creatorRole = strtoupper((string) ($locked->nguoiTaoRole ?: 'WAREHOUSE'));
            $requiredRoles = PurchaseOrderSignatureRole::requiredRolesForCreator($creatorRole);

            if (!in_array($role, $requiredRoles, true)) {
                throw ValidationException::withMessages([
                    'vaiTroKy' => ["Vai trò '{$role->label()}' không nằm trong danh sách ký bắt buộc của phiếu này."],
                ]);
            }

            // Nếu user có gửi kèm items kiểm kê cùng lúc ký (tương thích ngược xacNhanNhapKho)
            if (!empty($payload['items']) && is_array($payload['items'])) {
                foreach ($payload['items'] as $it) {
                    if (!isset($it['soLuongThucNhan'])) {
                        continue;
                    }
                    $ct = null;
                    if (!empty($it['chiTietId'])) {
                        $ct = $locked->chiTiets->firstWhere('id', (int) $it['chiTietId']);
                    } elseif (!empty($it['loHangId'])) {
                        $ct = $locked->chiTiets->firstWhere('loHangId', (int) $it['loHangId']);
                    } elseif (!empty($it['sanPhamId'])) {
                        $ct = $locked->chiTiets->firstWhere('sanPhamId', (int) $it['sanPhamId']);
                    }
                    if ($ct) {
                        $ct->update([
                            'soLuongThucNhan' => max(0, (int) $it['soLuongThucNhan']),
                            'ghiChuChenhLech' => $it['ghiChuChenhLech'] ?? $ct->ghiChuChenhLech,
                        ]);
                    }
                }
            }

            $existing = PurchaseOrderSignature::where('hoaDonNhapId', $locked->id)
                ->where('vaiTroKy', $role->value)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'vaiTroKy' => ["Vai trò '{$role->label()}' đã ký xác nhận cho phiếu này trước đó."],
                ]);
            }

            $defaultName = $role->isExternalCarrier()
                ? $role->label()
                : ($profile?->hoTen ?? $user->username ?? $role->label());
            $tenNguoiKy = trim((string) ($payload['tenNguoiKy'] ?? '')) ?: $defaultName;

            $savedSig = $profile?->chuKySo
                ?? $profile?->getRawOriginal('chuKySo')
                ?? $user->chuKySo
                ?? $user->getRawOriginal('chuKySo')
                ?? null;

            $chuKyAnh = $payload['chuKyAnh'] ?? ($role->isExternalCarrier() ? null : $savedSig);

            PurchaseOrderSignature::create([
                'hoaDonNhapId' => $locked->id,
                'vaiTroKy' => $role->value,
                'nguoiKyId' => $role->isExternalCarrier() ? null : ($profile?->id ?? $user->id),
                'tenNguoiKy' => $tenNguoiKy,
                'chuKyAnh' => $chuKyAnh,
                'ghiChu' => $payload['ghiChu'] ?? null,
                'thoiGianKy' => now(),
            ]);

            $signedRoleValues = PurchaseOrderSignature::where('hoaDonNhapId', $locked->id)
                ->pluck('vaiTroKy')
                ->all();

            $allSigned = true;
            foreach ($requiredRoles as $reqRole) {
                if (!in_array($reqRole->value, $signedRoleValues, true)) {
                    $allSigned = false;
                    break;
                }
            }

            $completedNow = false;
            if ($allSigned && !$locked->tonKhoDaCong && $locked->resolvedStatus() !== PurchaseOrderStatus::HOAN_TAT) {
                $locked->refresh();
                $locked->load('chiTiets');

                foreach ($locked->chiTiets as $ct) {
                    $actualQty = $ct->soLuongThucNhan !== null ? (int) $ct->soLuongThucNhan : (int) $ct->soLuong;
                    if ($ct->soLuongThucNhan === null) {
                        $ct->update(['soLuongThucNhan' => $actualQty]);
                    }

                    if ($ct->loHangId) {
                        LoHang::where('id', $ct->loHangId)->update([
                            'soLuongCon' => $actualQty,
                            'trangThai' => $actualQty > 0 ? 0 : 1,
                        ]);
                    }

                    if ($actualQty > 0) {
                        $stock = TonKhoChiNhanh::where('cuaHangId', $locked->cuaHangId)
                            ->where('sanPhamId', $ct->sanPhamId)
                            ->lockForUpdate()
                            ->first();

                        if (!$stock) {
                            $stock = TonKhoChiNhanh::create([
                                'cuaHangId' => $locked->cuaHangId,
                                'sanPhamId' => $ct->sanPhamId,
                                'soLuongTon' => 0,
                            ]);
                        }

                        $newQty = (int) $stock->soLuongTon + $actualQty;
                        $stock->update(['soLuongTon' => $newQty]);

                        InventoryLedgerService::record(
                            (int) $locked->cuaHangId,
                            (int) $ct->sanPhamId,
                            4, // 4: Nhập lô mới
                            $actualQty,
                            $locked->maHoaDonNhap,
                            $newQty
                        );
                    }
                }

                $locked->update([
                    'maTrangThai' => PurchaseOrderStatus::HOAN_TAT->value,
                    'trangThai' => PurchaseOrderStatus::HOAN_TAT->toLegacyInt(),
                    'tonKhoDaCong' => true,
                ]);
                $completedNow = true;
            } elseif (!$allSigned && $locked->resolvedStatus() === PurchaseOrderStatus::DA_DUYET) {
                $locked->update([
                    'maTrangThai' => PurchaseOrderStatus::DANG_KIEM_KE->value,
                    'trangThai' => PurchaseOrderStatus::DANG_KIEM_KE->toLegacyInt(),
                ]);
            }

            return [
                'invoice' => $locked->fresh(['nhaCungCap', 'cuaHang', 'nguoiNhap', 'nguoiDuyet', 'signatures.nguoiKy', 'chiTiets.loHang', 'chiTiets.sanPham']),
                'completedNow' => $completedNow,
            ];
        });

        if ($result['completedNow']) {
            ProductController::clearProductCache();
            Cache::flush();
        }

        return $result;
    }
}
