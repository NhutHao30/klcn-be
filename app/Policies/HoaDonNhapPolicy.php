<?php

namespace App\Policies;

use App\Enums\PurchaseOrderSignatureRole;
use App\Models\HoaDonNhap;
use App\Models\NguoiDung;
use App\Models\TaiKhoan;
use Illuminate\Auth\Access\Response;

class HoaDonNhapPolicy
{
    private function getProfile(TaiKhoan $user): ?NguoiDung
    {
        return NguoiDung::where('taiKhoanId', $user->id)->first() ?? $user->nguoidung;
    }

    private function belongsToBranch(TaiKhoan $user, HoaDonNhap $hoaDonNhap): bool
    {
        $profile = $this->getProfile($user);
        if (!$profile || !$profile->cuaHangId) {
            return false;
        }

        return (int) $profile->cuaHangId === (int) $hoaDonNhap->cuaHangId;
    }

    private function isManager(TaiKhoan $user): bool
    {
        return (int) ($user->vaiTroId ?? 0) === 2 || (isset($user->MAROLE) && (int) $user->MAROLE === 1);
    }

    private function isWarehouse(TaiKhoan $user): bool
    {
        return (int) ($user->vaiTroId ?? 0) === 4 || (isset($user->MAROLE) && (int) $user->MAROLE === 7);
    }

    private function isAccountant(TaiKhoan $user): bool
    {
        return (int) ($user->vaiTroId ?? 0) === 7 || (isset($user->MAROLE) && (int) $user->MAROLE === 6);
    }

    public function create(TaiKhoan $user): Response
    {
        if ($this->isManager($user) || $this->isWarehouse($user)) {
            return Response::allow();
        }

        return Response::deny('Chỉ Nhân viên kho hoặc Quản lý chi nhánh mới có quyền tạo phiếu nhập hàng.');
    }

    public function approve(TaiKhoan $user, HoaDonNhap $hoaDonNhap): Response
    {
        if (!$this->isManager($user)) {
            return Response::deny('Chỉ Quản lý chi nhánh mới có quyền duyệt phiếu nhập hàng.');
        }

        if (!$this->belongsToBranch($user, $hoaDonNhap)) {
            return Response::deny('Quản lý chỉ được duyệt phiếu nhập thuộc chi nhánh mình quản lý.');
        }

        return Response::allow();
    }

    public function reject(TaiKhoan $user, HoaDonNhap $hoaDonNhap): Response
    {
        if (!$this->isManager($user)) {
            return Response::deny('Chỉ Quản lý chi nhánh mới có quyền từ chối phiếu nhập hàng.');
        }

        if (!$this->belongsToBranch($user, $hoaDonNhap)) {
            return Response::deny('Quản lý chỉ được từ chối phiếu nhập thuộc chi nhánh mình quản lý.');
        }

        return Response::allow();
    }

    public function inspect(TaiKhoan $user, HoaDonNhap $hoaDonNhap): Response
    {
        if (!$this->isManager($user) && !$this->isWarehouse($user)) {
            return Response::deny('Chỉ Nhân viên kho hoặc Quản lý chi nhánh mới được kiểm kê hàng nhập.');
        }

        if (!$this->belongsToBranch($user, $hoaDonNhap)) {
            return Response::deny('Bạn không thuộc chi nhánh của phiếu nhập hàng này.');
        }

        return Response::allow();
    }

    public function sign(TaiKhoan $user, HoaDonNhap $hoaDonNhap, PurchaseOrderSignatureRole $targetRole): Response
    {
        if (!$this->belongsToBranch($user, $hoaDonNhap)) {
            return Response::deny('Bạn không thuộc chi nhánh của phiếu nhập hàng này.');
        }

        // Vai trò giao hàng / vận chuyển bên ngoài: Quản lý chi nhánh hoặc Nhân viên kho ghi nhận chữ ký trên thiết bị tại kho
        if ($targetRole->isExternalCarrier()) {
            if ($this->isManager($user) || $this->isWarehouse($user)) {
                return Response::allow();
            }

            return Response::deny('Chỉ Nhân viên kho hoặc Quản lý chi nhánh mới được ghi nhận xác nhận của đơn vị giao hàng.');
        }

        $userSignRole = PurchaseOrderSignatureRole::fromUserRoleId((int) ($user->vaiTroId ?? 0));
        if (!$userSignRole && $this->isAccountant($user)) {
            $userSignRole = PurchaseOrderSignatureRole::NHAN_VIEN_KE_TOAN;
        } elseif (!$userSignRole && $this->isManager($user)) {
            $userSignRole = PurchaseOrderSignatureRole::QUAN_LY_CHI_NHANH;
        } elseif (!$userSignRole && $this->isWarehouse($user)) {
            $userSignRole = PurchaseOrderSignatureRole::NHAN_VIEN_KHO;
        }

        if ($userSignRole !== $targetRole) {
            return Response::deny("Bạn không có quyền ký thay vai trò {$targetRole->label()}.");
        }

        return Response::allow();
    }
}
