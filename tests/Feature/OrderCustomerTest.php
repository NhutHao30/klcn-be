<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\TaiKhoan;
use App\Models\KhachHang;
use App\Models\HdBan;
use App\Models\CuaHang;
use App\Models\TonKhoCuaHang;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderCustomerTest extends TestCase
{
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        $user = TaiKhoan::create([
            'USERNAME' => 'testcustomer2',
            'PASSWORD' => bcrypt('123456'),
            'MAROLE' => 3,
            'EMAIL' => 'testcustomer2@example.com',
        ]);

        KhachHang::create([
            'USERNAME' => 'testcustomer2',
            'MAKH' => 'KH998',
            'TENKH' => 'Test Customer 2',
            'SDT' => '0123456789'
        ]);

        $this->token = JWTAuth::fromUser($user);

        $cuahang = CuaHang::create([
            'TENCUAHANG' => 'Cửa hàng Test',
            'DIACHI' => '123 Test',
            'SDT' => '0111111111',
            'TRANGTHAI' => 'Đang hoạt động',
        ]);
        // DatabaseSeeder đã tạo sẵn SP001 nên không cần tạo thêm SanPham

        TonKhoCuaHang::create([
            'MACUAHANG' => $cuahang->MACUAHANG,
            'MASP' => 'SP001',
            'SOLUONG_TON' => 100,
        ]);

        // Gọi setup-vouchers để tạo table nếu chưa có
        $this->getJson('/api/setup-vouchers');
    }

    /**
     * Test checkout order
     * checkoutOnline uses params: paymentMethod, address, note
     */
    public function test_can_checkout_order()
    {
        // Thêm vào giỏ hàng trước
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/cart/add', [
            'masp' => 'SP001',
            'quantity' => 2
        ]);

        // Đặt hàng
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/cart/checkout', [
            'paymentMethod' => 'COD',
            'address' => '123 Đường Test',
            'note' => 'Giao vao buoi sang'
        ]);

        $response->assertStatus(200);

        // Đảm bảo đơn hàng được tạo trong DB
        $this->assertDatabaseHas('hdban', [
            'MAKH' => 'KH998',
            'PHUONGTHUCTHANHTOAN' => 'COD (Giao hàng)',
            'TRANGTHAITHANHTOAN' => 'Chờ xử lý',
        ]);
    }

    /**
     * Test customer can fetch their orders
     * myOrders trả về { "content": [...] }
     */
    public function test_can_fetch_my_orders()
    {
        HdBan::create([
            'MAHD' => 'HDTEST999',
            'MAKH' => 'KH998',
            'MACN' => 'CHTEST',
            'NGAYLAP' => now(),
            'TONGTIEN' => 100000,
            'PHUONGTHUCTHANHTOAN' => 'COD',
            'TRANGTHAITHANHTOAN' => 'Chờ xử lý',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/my-orders');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'content' => [
                         '*' => [
                             'maHD',
                             'ngayLap',
                             'tongTien',
                             'trangThai'
                         ]
                     ]
                 ]);
    }

    /**
     * Test customer can cancel their pending order
     */
    public function test_can_cancel_pending_order()
    {
        HdBan::create([
            'MAHD' => 'HDTEST888',
            'MAKH' => 'KH998',
            'MACN' => 'CHTEST',
            'NGAYLAP' => now(),
            'TONGTIEN' => 100000,
            'PHUONGTHUCTHANHTOAN' => 'COD',
            'TRANGTHAITHANHTOAN' => 'Chờ xử lý',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/my-orders/HDTEST888/cancel');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Hủy đơn hàng thành công'
                 ]);

        $this->assertDatabaseHas('hdban', [
            'MAHD' => 'HDTEST888',
            'TRANGTHAITHANHTOAN' => 'Đã hủy'
        ]);
    }

    /**
     * Test cannot cancel a shipped order
     */
    public function test_cannot_cancel_shipped_order()
    {
        HdBan::create([
            'MAHD' => 'HDTEST777',
            'MAKH' => 'KH998',
            'MACN' => 'CHTEST',
            'NGAYLAP' => now(),
            'TONGTIEN' => 100000,
            'PHUONGTHUCTHANHTOAN' => 'COD',
            'TRANGTHAITHANHTOAN' => 'Đang giao',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/my-orders/HDTEST777/cancel');

        $response->assertStatus(400)
                 ->assertJson([
                     'error' => 'Chỉ có thể hủy đơn hàng đang chờ xử lý'
                 ]);
    }
}
