<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TaiKhoan;
use Illuminate\Support\Facades\DB;

class TransferTest extends TestCase
{
    /**
     * Test admin can access transfers list.
     */
    public function test_admin_can_get_transfers(): void
    {
        // Login as admin
        $loginResponse = $this->postJson('/api/login', [
            'USERNAME' => 'admin',
            'PASSWORD' => '123456',
        ]);
        
        $token = $loginResponse->json('access_token');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/transfers');
        
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /**
     * Test admin can access transfer requests.
     */
    public function test_admin_can_get_transfer_requests(): void
    {
        // Login as admin
        $loginResponse = $this->postJson('/api/login', [
            'USERNAME' => 'admin',
            'PASSWORD' => '123456',
        ]);
        
        $token = $loginResponse->json('access_token');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/transfers/requests');
        
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /**
     * Test creating a transfer request fails if source store has insufficient stock.
     */
    public function test_create_transfer_fails_insufficient_stock(): void
    {
        // Login as admin
        $loginResponse = $this->postJson('/api/login', [
            'USERNAME' => 'admin',
            'PASSWORD' => '123456',
        ]);
        $token = $loginResponse->json('access_token');

        // Create a new product and add 5 to store 1
        // Grab an existing product or create one
        $product = \App\Models\SanPham::first();
        if (!$product) {
            $product = \App\Models\SanPham::create([
                'TENSP' => 'Test Product',
                'MOTA' => 'Test Description',
                'GIABAN' => 100000,
                'HINHANH' => 'test.png',
                'LOAISP' => 1,
            ]);
        }
        
        \Illuminate\Support\Facades\DB::table('kho')->insert([
            'MACN' => 1,
            'MASP' => $product->MASP,
            'SOLUONG' => 5
        ]);

        // Try to transfer 10 from store 1 to store 2
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/admin/transfers/requests', [
            'TuChiNhanh' => 1,
            'DenChiNhanh' => 2,
            'ChiTiet' => [
                [
                    'MASP' => $product->MASP,
                    'SOLUONG' => 10
                ]
            ]
        ]);

        // Should return 400 Bad Request
        $response->assertStatus(400);
        $response->assertJsonFragment(['error' => 'Not enough stock for product ' . $product->TENSP . ' at source store']);
    }

    /**
     * Test creating a transfer request succeeds if source store has sufficient stock.
     */
    public function test_create_transfer_success(): void
    {
        // Login as admin
        $loginResponse = $this->postJson('/api/login', [
            'USERNAME' => 'admin',
            'PASSWORD' => '123456',
        ]);
        $token = $loginResponse->json('access_token');

        // Create a new product and add 15 to store 1
        // Grab an existing product or create one
        $product = \App\Models\SanPham::first();
        if (!$product) {
            $product = \App\Models\SanPham::create([
                'TENSP' => 'Test Product',
                'MOTA' => 'Test Description',
                'GIABAN' => 100000,
                'HINHANH' => 'test.png',
                'LOAISP' => 1,
            ]);
        }
        
        \Illuminate\Support\Facades\DB::table('kho')->insert([
            'MACN' => 1,
            'MASP' => $product->MASP,
            'SOLUONG' => 15
        ]);

        // Try to transfer 10 from store 1 to store 2
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/admin/transfers/requests', [
            'TuChiNhanh' => 1,
            'DenChiNhanh' => 2,
            'GhiChu' => 'Test transfer',
            'ChiTiet' => [
                [
                    'MASP' => $product->MASP,
                    'SOLUONG' => 10
                ]
            ]
        ]);

        // Should return 200 OK
        $response->assertStatus(200);
        
        // Stock at store 1 should now be 5
        $stock = \Illuminate\Support\Facades\DB::table('kho')
            ->where('MACN', 1)
            ->where('MASP', $product->MASP)
            ->value('SOLUONG');
            
        $this->assertEquals(5, $stock);
    }
}
