<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TaiKhoan;
use App\Models\NhanVien;
use App\Models\HdNhap;
use App\Models\BienBanHuy;
use App\Models\LoHang;

class DigitalSignatureTest extends TestCase
{
    /**
     * Test nhân viên lưu chữ ký số vào Profile
     */
    public function test_employee_can_save_signature_to_profile()
    {
        \App\Models\VaiTro::firstOrCreate(['MAROLE' => 2], ['MOTA' => 'Nhân viên']);

        $user = TaiKhoan::firstOrCreate(
            ['USERNAME' => 'test_nv_signature'],
            [
                'PASSWORD' => 'password123',
                'MAROLE' => 2,
                'EMAIL' => 'test_nv@bakery.com',
            ]
        );

        NhanVien::updateOrCreate(
            ['USERNAME' => 'test_nv_signature'],
            [
                'HOTEN' => 'Nhân Viên Ký Test',
                'CHUCVU' => 'Thủ kho',
                'MACUAHANG' => 1
            ]
        );

        $fakeSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($user, 'api')->postJson('/api/profile/chu-ky', [
            'chu_ky' => $fakeSignature
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true,
                     'message' => 'Lưu chữ ký số cá nhân thành công!'
                 ]);

        $this->assertDatabaseHas('nhanvien', [
            'USERNAME' => 'test_nv_signature',
            'CHU_KY' => $fakeSignature
        ]);
    }

    /**
     * Test tài khoản sai vai trò không thể ký ở vị trí Kế toán (403 Forbidden)
     */
    public function test_unauthorized_user_cannot_sign_on_behalf_of_accountant()
    {
        \App\Models\VaiTro::firstOrCreate(['MAROLE' => 2], ['MOTA' => 'Nhân viên']);

        $cashierUser = TaiKhoan::firstOrCreate(
            ['USERNAME' => 'test_cashier_user'],
            [
                'PASSWORD' => 'password123',
                'MAROLE' => 2,
                'EMAIL' => 'cashier@bakery.com',
            ]
        );

        NhanVien::updateOrCreate(
            ['USERNAME' => 'test_cashier_user'],
            [
                'HOTEN' => 'Thu Ngân Test',
                'CHUCVU' => 'Thu ngân',
                'MACUAHANG' => 1,
                'CHU_KY' => 'data:image/png;base64,fake_sig'
            ]
        );

        // Tạo 1 phiếu nhập kho mẫu
        $hd = HdNhap::create([
            'MAHDNHAP' => 'NH999999',
            'NGAYLAP' => now(),
            'USERNAME' => 'test_cashier_user',
            'MACUAHANG_NHAP' => 1,
            'TONGTIEN_NHAP' => 100000,
        ]);

        // Cố gắng ký dưới danh nghĩa Kế toán
        $response = $this->actingAs($cashierUser, 'api')->postJson('/api/chu-ky/xac-nhan', [
            'loai_phieu' => 'hdnhap',
            'id' => 'NH999999',
            'vaitro_ky' => 'ke_toan'
        ]);

        $response->assertStatus(403)
                 ->assertJsonStructure(['error']);
    }

    /**
     * Test kế toán / quản lý ký xác nhận phiếu nhập kho thành công
     */
    public function test_authorized_accountant_can_sign_document()
    {
        \App\Models\VaiTro::firstOrCreate(['MAROLE' => 1], ['MOTA' => 'Quản lý']);

        $accountantUser = TaiKhoan::firstOrCreate(
            ['USERNAME' => 'test_accountant_user'],
            [
                'PASSWORD' => 'password123',
                'MAROLE' => 1, // Role 1 quản lý / kế toán
                'EMAIL' => 'ketoan@bakery.com',
            ]
        );

        NhanVien::updateOrCreate(
            ['USERNAME' => 'test_accountant_user'],
            [
                'HOTEN' => 'Kế Toán Viễn',
                'CHUCVU' => 'Kế toán trưởng',
                'MACUAHANG' => 1,
                'CHU_KY' => 'data:image/png;base64,accountant_sig'
            ]
        );

        $hd = HdNhap::create([
            'MAHDNHAP' => 'NH888888',
            'NGAYLAP' => now(),
            'USERNAME' => 'test_accountant_user',
            'MACUAHANG_NHAP' => 1,
            'TONGTIEN_NHAP' => 500000,
        ]);

        $response = $this->actingAs($accountantUser, 'api')->postJson('/api/chu-ky/xac-nhan', [
            'loai_phieu' => 'hdnhap',
            'id' => 'NH888888',
            'vaitro_ky' => 'ke_toan'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => true,
                     'message' => 'Đã ký xác nhận Phiếu Nhập Kho thành công!'
                 ]);

        $this->assertDatabaseHas('hdnhap', [
            'MAHDNHAP' => 'NH888888',
            'KY_KE_TOAN' => 'data:image/png;base64,accountant_sig',
            'NGUOI_KY_KE_TOAN' => 'test_accountant_user'
        ]);
    }
}
