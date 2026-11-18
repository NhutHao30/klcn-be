<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class StaffPresenceService
{
    const CACHE_KEY = 'cskh_online_staff_map';
    const TTL_SECONDS = 180; // 3 phút không có request -> tự động tính là offline

    /**
     * Ghi nhận nhân viên CSKH/Admin vừa hoạt động trực tuyến
     */
    public static function recordOnline(int $userId): void
    {
        $online = Cache::get(self::CACHE_KEY, []);
        $online[$userId] = now()->timestamp;
        Cache::put(self::CACHE_KEY, $online, 86400);
    }

    /**
     * Ghi nhận nhân viên đăng xuất khỏi hệ thống
     */
    public static function recordOffline(int $userId): void
    {
        $online = Cache::get(self::CACHE_KEY, []);
        if (isset($online[$userId])) {
            unset($online[$userId]);
            Cache::put(self::CACHE_KEY, $online, 86400);
        }
    }

    /**
     * Kiểm tra xem hiện tại có nhân viên CSKH nào đang online không
     */
    public static function isStaffOnline(): bool
    {
        $online = Cache::get(self::CACHE_KEY, []);
        if (empty($online)) {
            return false;
        }

        $now = now()->timestamp;
        $active = false;
        $cleaned = [];

        foreach ($online as $id => $timestamp) {
            if (($now - $timestamp) <= self::TTL_SECONDS) {
                $cleaned[$id] = $timestamp;
                $active = true;
            }
        }

        if (count($cleaned) !== count($online)) {
            Cache::put(self::CACHE_KEY, $cleaned, 86400);
        }

        return $active;
    }

    /**
     * Lấy danh sách ID nhân viên đang online
     */
    public static function getOnlineStaffIds(): array
    {
        $online = Cache::get(self::CACHE_KEY, []);
        $now = now()->timestamp;
        $activeIds = [];

        foreach ($online as $id => $timestamp) {
            if (($now - $timestamp) <= self::TTL_SECONDS) {
                $activeIds[] = $id;
            }
        }

        return $activeIds;
    }

    /**
     * Xóa toàn bộ trạng thái online (hỗ trợ kiểm thử)
     */
    public static function clearAll(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
