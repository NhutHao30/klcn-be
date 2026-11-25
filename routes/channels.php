<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Kênh bảo mật cho đoạn chat (nhân viên/cskh hoặc khách hàng sở hữu đoạn chat mới được nghe)
Broadcast::channel('chat.{conversationId}', function ($userLogin, $conversationId) {
    if (!$userLogin) {
        return false;
    }

    // Nhân viên / Admin / Quản lý / CSKH luôn được phép nghe
    if (in_array($userLogin->MAROLE, [0, 1, 2, 4, 5, 6])) {
        return true;
    }

    // Khách hàng thì phải là chủ của cuộc trò chuyện
    $conversation = \App\Models\CuocTroChuyen::find($conversationId);
    if ($conversation) {
        $khachHang = \App\Models\KhachHang::findByUsername($userLogin->username);
        return $khachHang && ($conversation->khachHangId == $khachHang->id);
    }

    return false;
}, ['guards' => ['api']]);

// Kênh bảo mật cho chat nội bộ
Broadcast::channel('internal.{channel}', function ($userLogin, $channel) {
    if (!$userLogin) {
        return false;
    }

    // Nếu là Admin tổng
    if ($userLogin->MAROLE == 0) {
        return true;
    }

    // Nếu là chat quản lý
    if ($channel === 'managers') {
        return in_array($userLogin->MAROLE, [0, 1]);
    }

    // Nếu là chat chi nhánh
    if (str_starts_with($channel, 'branch_')) {
        $branchId = str_replace('branch_', '', $channel);
        $nhanVien = \App\Models\NhanVien::findByUsername($userLogin->username);
        if ($nhanVien && $nhanVien->MACUAHANG == $branchId) {
            return true;
        }
    }

    return false;
}, ['guards' => ['api']]);
