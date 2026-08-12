<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\KhuyenMai;

class VoucherTest extends TestCase
{
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        // Cần gọi API setup-vouchers vì table khuyenmai được tạo động qua route này, không có file migration chuẩn
        $this->getJson('/api/setup-vouchers');

        $user = \App\Models\TaiKhoan::create([
            'USERNAME' => 'testvoucheruser',
            'PASSWORD' => bcrypt('123456'),
            'MAROLE' => 3,
            'EMAIL' => 'testvoucheruser@example.com',
        ]);
        
        \App\Models\KhachHang::create([
            'USERNAME' => 'testvoucheruser',
            'MAKH' => 'KH888',
            'TENKH' => 'Test Voucher Customer',
            'SDT' => '0123456789'
        ]);

        $this->token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
    }

    /**
     * Test apply valid voucher
     */
    public function test_can_apply_valid_voucher()
    {
        // Tạo một voucher hợp lệ
        KhuyenMai::create([
            'MA_VOUCHER' => 'TESTVOUCHER',
            'TEN_VOUCHER' => 'Voucher Test 10%',
            'LOAI_GIAM' => 'PHAN_TRAM',
            'GIA_TRI' => 10,
            'DON_TOI_THIEU' => 100000,
            'GIAM_TOI_DA' => 50000,
            'SO_LUONG_GIOI_HAN' => 100,
            'SO_LUONG_DA_DUNG' => 0,
            'NGAY_BAT_DAU' => now()->subDay(),
            'NGAY_KET_THUC' => now()->addDays(10),
            'TRANG_THAI' => true
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/vouchers/apply', [
            'voucher_code' => 'TESTVOUCHER',
            'subtotal' => 200000
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'voucher_code' => 'TESTVOUCHER',
                     'discount_amount' => 20000 // 10% of 200,000
                 ]);
    }

    /**
     * Test apply voucher below minimum amount
     */
    public function test_cannot_apply_voucher_below_minimum_amount()
    {
        KhuyenMai::create([
            'MA_VOUCHER' => 'TESTVOUCHER2',
            'TEN_VOUCHER' => 'Voucher Test 50K',
            'LOAI_GIAM' => 'TIEN_MAT',
            'GIA_TRI' => 50000,
            'DON_TOI_THIEU' => 300000,
            'SO_LUONG_GIOI_HAN' => 100,
            'SO_LUONG_DA_DUNG' => 0,
            'NGAY_BAT_DAU' => now()->subDay(),
            'NGAY_KET_THUC' => now()->addDays(10),
            'TRANG_THAI' => true
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/vouchers/apply', [
            'voucher_code' => 'TESTVOUCHER2',
            'subtotal' => 200000 // Below 300000
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'error' => 'Đơn hàng chưa đạt giá trị tối thiểu để dùng mã này (300,000đ)'
                 ]);
    }
    
    /**
     * Test apply expired voucher
     */
    public function test_cannot_apply_expired_voucher()
    {
        KhuyenMai::create([
            'MA_VOUCHER' => 'EXPIRED',
            'TEN_VOUCHER' => 'Voucher Hết Hạn',
            'LOAI_GIAM' => 'TIEN_MAT',
            'GIA_TRI' => 10000,
            'DON_TOI_THIEU' => 0,
            'SO_LUONG_GIOI_HAN' => 100,
            'SO_LUONG_DA_DUNG' => 0,
            'NGAY_BAT_DAU' => now()->subDays(10),
            'NGAY_KET_THUC' => now()->subDays(1), // Expired
            'TRANG_THAI' => true
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/vouchers/apply', [
            'voucher_code' => 'EXPIRED',
            'subtotal' => 200000
        ]);

        $response->assertStatus(400)
                 ->assertJson([
                     'error' => 'Mã giảm giá đã hết hạn, bị vô hiệu hóa hoặc hết lượt sử dụng'
                 ]);
    }
}
