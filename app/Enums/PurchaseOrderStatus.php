<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case CHO_DUYET = 'CHO_DUYET';
    case DA_DUYET = 'DA_DUYET';
    case TU_CHOI = 'TU_CHOI';
    case DANG_KIEM_KE = 'DANG_KIEM_KE';
    case HOAN_TAT = 'HOAN_TAT';

    public function label(): string
    {
        return match ($this) {
            self::CHO_DUYET => 'Chờ duyệt',
            self::DA_DUYET => 'Đã duyệt',
            self::TU_CHOI => 'Từ chối',
            self::DANG_KIEM_KE => 'Đang kiểm kê',
            self::HOAN_TAT => 'Hoàn tất',
        };
    }

    /**
     * Giá trị số tương thích ngược với cột trangThai cũ (0: chưa hoàn tất, 1: hoàn tất, 2: từ chối)
     */
    public function toLegacyInt(): int
    {
        return match ($this) {
            self::HOAN_TAT => 1,
            self::TU_CHOI => 2,
            default => 0,
        };
    }

    public static function resolve(?string $maTrangThai, ?int $legacyTrangThai = null): self
    {
        if ($maTrangThai) {
            $parsed = self::tryFrom($maTrangThai);
            if ($parsed) {
                return $parsed;
            }
        }

        return match ((int) $legacyTrangThai) {
            1 => self::HOAN_TAT,
            2 => self::TU_CHOI,
            default => self::CHO_DUYET,
        };
    }
}
