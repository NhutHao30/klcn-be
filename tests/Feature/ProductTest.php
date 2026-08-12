<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductTest extends TestCase
{
    /**
     * Test fetching products list.
     */
    public function test_can_fetch_products_list(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'current_page',
                     'data' => [
                         '*' => [
                             'MASP',
                             'TENSP',
                             'GIABAN',
                             'HINHANH',
                             // ... other fields
                         ]
                     ],
                     'total'
                 ]);
    }

    /**
     * Test fetching single product details.
     */
    public function test_can_fetch_product_details(): void
    {
        // DatabaseSeeder creates SP001
        $response = $this->getJson('/api/products/SP001');

        $response->assertStatus(200)
                 ->assertJson([
                     'MASP' => 'SP001',
                 ]);
    }

    /**
     * Test product not found.
     */
    public function test_product_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/products/SP999');

        $response->assertStatus(404)
                 ->assertJson([
                     'error' => 'Không tìm thấy sản phẩm',
                 ]);
    }

    /**
     * Test fetching categories.
     */
    public function test_can_fetch_categories(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     '*' => [
                         'MALOAI',
                         'TENLOAI'
                     ]
                 ]);
    }
}
