<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\TaiKhoan;
use App\Models\KhachHang;
use App\Models\CuaHang;
use App\Models\TonKhoCuaHang;
use Tymon\JWTAuth\Facades\JWTAuth;

class CartTest extends TestCase
{
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Tạo customer
        $user = TaiKhoan::create([
            'USERNAME' => 'testcustomer',
            'PASSWORD' => bcrypt('123456'),
            'MAROLE' => 3,
            'EMAIL' => 'testcustomer@example.com',
        ]);

        KhachHang::create([
            'USERNAME' => 'testcustomer',
            'MAKH' => 'KH999',
            'TENKH' => 'Test Customer',
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
    }

    /**
     * Test get cart (initially cart has cartId and empty items)
     */
    public function test_can_get_cart()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/cart');

        $response->assertStatus(200)
                 ->assertJsonStructure(['cartId', 'items']);
    }

    /**
     * Test add product to cart
     * CartController validates param name 'quantity' (not 'soluong')
     */
    public function test_can_add_to_cart()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/cart/add', [
            'masp' => 'SP001',
            'quantity' => 2
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Đã thêm vào giỏ hàng',
                 ]);
    }

    /**
     * Test update cart quantity
     */
    public function test_can_update_cart_quantity()
    {
        // Add first
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/cart/add', [
            'masp' => 'SP001',
            'quantity' => 1
        ]);

        // Update
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/cart/update', [
            'masp' => 'SP001',
            'quantity' => 5
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Đã cập nhật giỏ hàng',
                 ]);
    }

    /**
     * Test remove from cart
     */
    public function test_can_remove_from_cart()
    {
        // Add first
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/cart/add', [
            'masp' => 'SP001',
            'quantity' => 1
        ]);

        // Remove
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/cart/remove/SP001');

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Đã xóa khỏi giỏ hàng',
                 ]);
    }
}
