<?php

namespace App\Enums;

enum PurchaseOrderSignatureRole: string
{
    case NHAN_VIEN_VAN_CHUYEN = 'NHAN_VIEN_VAN_CHUYEN';
    case NHAN_VIEN_KE_TOAN = 'NHAN_VIEN_KE_TOAN';
    case NHAN_VIEN_KHO = 'NHAN_VIEN_KHO';
    case QUAN_LY_CHI_NHANH = 'QUAN_LY_CHI_NHANH';
    case NHAN_VIEN_GIAO_HANG = 'NHAN_VIEN_GIAO_HANG';

    public function label(): string
    {
        return match ($this) {
            self::NHAN_VIEN_VAN_CHUYEN => 'Nhân viên vận chuyển',
            self::NHAN_VIEN_KE_TOAN => 'Nhân viên kế toán',
            self::NHAN_VIEN_KHO => 'Nhân viên kho',
            self::QUAN_LY_CHI_NHANH => 'Quản lý chi nhánh',
            self::NHAN_VIEN_GIAO_HANG => 'Nhân viên giao hàng',
        };
    }

    /**
     * Danh sách vai trò ký bắt buộc theo vai trò người tạo phiếu.
     *
     * @return self[]
     */
    public static function requiredRolesForCreator(string $creatorRole): array
    {
        if (strtoupper($creatorRole) === 'MANAGER') {
            return [
                self::QUAN_LY_CHI_NHANH,
                self::NHAN_VIEN_GIAO_HANG,
            ];
        }

        // Mặc định: Nhân viên kho tạo (WAREHOUSE)
        return [
            self::NHAN_VIEN_VAN_CHUYEN,
            self::NHAN_VIEN_KE_TOAN,
            self::NHAN_VIEN_KHO,
        ];
    }

    /**
     * Kiểm tra xem vai trò ký có phải là người giao/vận chuyển bên ngoài (ký tại thiết bị chi nhánh) không.
     */
    public function isExternalCarrier(): bool
    {
        return in_array($this, [self::NHAN_VIEN_VAN_CHUYEN, self::NHAN_VIEN_GIAO_HANG], true);
    }

    /**
     * Map từ vaiTroId của hệ thống sang vai trò ký nội bộ.
     */
    public static function fromUserRoleId(int $vaiTroId): ?self
    {
        return match ($vaiTroId) {
            2 => self::QUAN_LY_CHI_NHANH,
            4 => self::NHAN_VIEN_KHO,
            7 => self::NHAN_VIEN_KE_TOAN,
            default => null,
        };
    }
}
