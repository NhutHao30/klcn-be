<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\TaiKhoan;

class StoreTest extends TestCase
{
    /**
     * Test getting public stores.
     */
    public function test_can_get_public_stores(): void
    {
        $response = $this->getJson('/api/stores');
        
        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }

    /**
     * Test getting admin stores without auth.
     */
    public function test_cannot_get_admin_stores_without_auth(): void
    {
        $response = $this->getJson('/api/admin/stores');
        
        $response->assertStatus(401);
    }

    /**
     * Test admin can get admin stores.
     */
    public function test_admin_can_get_stores(): void
    {
        // Get token
        $loginResponse = $this->postJson('/api/login', [
            'USERNAME' => 'admin',
            'PASSWORD' => '123456',
        ]);
        
        $token = $loginResponse->json('access_token');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/stores');
        
        $response->assertStatus(200);
    }
}
