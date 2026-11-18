<?php

namespace App\Http\Resources;

use App\Enums\PurchaseOrderSignatureRole;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->resolvedStatus();
        $creatorRole = strtoupper((string) ($this->nguoiTaoRole ?: 'WAREHOUSE'));
        $requiredRoles = PurchaseOrderSignatureRole::requiredRolesForCreator($creatorRole);

        $signatures = $this->relationLoaded('signatures')
            ? $this->signatures
            : $this->signatures()->with('nguoiKy')->get();

        $signedMap = $signatures->keyBy('vaiTroKy');
        $requiredList = [];
        $signedList = [];
        $pendingList = [];

        foreach ($requiredRoles as $roleEnum) {
            $sig = $signedMap->get($roleEnum->value);
            $item = [
                'vaiTroKy' => $roleEnum->value,
                'label' => $roleEnum->label(),
                'isExternalCarrier' => $roleEnum->isExternalCarrier(),
                'signed' => (bool) $sig,
                'nguoiKyId' => $sig?->nguoiKyId,
                'tenNguoiKy' => $sig?->tenNguoiKy,
                'chuKyAnh' => $sig?->chuKyAnh,
                'ghiChu' => $sig?->ghiChu,
                'thoiGianKy' => $sig?->thoiGianKy?->toIso8601String(),
            ];
            $requiredList[] = $item;
            if ($sig) {
                $signedList[] = $item;
            } else {
                $pendingList[] = $item;
            }
        }

        // Fallback cho phiếu cũ (trước migration) đã hoàn tất nhưng chưa có bảng purchase_order_signatures
        $isLegacyCompleted = $status === PurchaseOrderStatus::HOAN_TAT && $signatures->isEmpty();

        return [
            'id' => $this->id,
            'maHoaDonNhap' => $this->maHoaDonNhap,
            'MAHDNHAP' => $this->MAHDNHAP,
            'nhaCungCapId' => $this->nhaCungCapId,
            'MANCC' => $this->MANCC,
            'cuaHangId' => $this->cuaHangId,
            'MACUAHANG' => $this->MACUAHANG,
            'nguoiNhapId' => $this->nguoiNhapId,
            'nguoiTaoRole' => $creatorRole,
            'nguoiDuyetId' => $this->nguoiDuyetId,
            'thoiGianDuyet' => $this->thoiGianDuyet?->toIso8601String(),
            'lyDoTuChoi' => $this->lyDoTuChoi,
            'ngayNhap' => $this->ngayNhap?->toIso8601String(),
            'NGAYLAP' => $this->NGAYLAP,
            'tongTien' => (float) $this->tongTien,
            'TONGTIEN' => (float) $this->TONGTIEN,
            'TONGTIEN_NHAP' => (float) $this->TONGTIEN_NHAP,
            'ghiChu' => $this->ghiChu,
            'trangThai' => (int) $this->trangThai,
            'TRANGTHAI' => (int) $this->TRANGTHAI,
            'maTrangThai' => $status->value,
            'trangThaiLabel' => $status->label(),
            'tonKhoDaCong' => (bool) $this->tonKhoDaCong,
            'nhaCungCap' => $this->whenLoaded('nhaCungCap'),
            'cuaHang' => $this->whenLoaded('cuaHang'),
            'nguoiNhap' => $this->whenLoaded('nguoiNhap'),
            'nguoiDuyet' => $this->whenLoaded('nguoiDuyet'),
            'chiTiets' => $this->whenLoaded('chiTiets'),
            'chuKySos' => $this->whenLoaded('chuKySos'),
            'signatures' => $signatures->values(),
            'signatureProgress' => [
                'creatorRole' => $creatorRole,
                'requiredRoles' => $requiredList,
                'signedRoles' => $signedList,
                'pendingRoles' => $pendingList,
                'totalRequired' => count($requiredList),
                'totalSigned' => $isLegacyCompleted ? count($requiredList) : count($signedList),
                'isComplete' => $isLegacyCompleted || count($pendingList) === 0,
            ],
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
