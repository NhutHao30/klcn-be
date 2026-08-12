<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\TaiKhoan;

class AuthTest extends TestCase
{
    /**
     * Test user can login with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        // The DatabaseSeeder creates an 'admin' user with password '123456'
        $response = $this->postJson('/api/login', [
            'USERNAME' => 'admin',
            'PASSWORD' => '123456',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'access_token',
                     'token_type',
                     'expires_in',
                     'user' => [
                         'USERNAME',
                         'MAROLE'
                     ]
                 ]);
    }

    /**
     * Test login fails with wrong password.
     */
    public function test_user_cannot_login_with_wrong_password(): void
    {
        $response = $this->postJson('/api/login', [
            'USERNAME' => 'admin',
            'PASSWORD' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'error' => 'Tài khoản hoặc mật khẩu không chính xác'
                 ]);
    }

    /**
     * Test register a new customer.
     * Register trả về token (200) chứ không trả 201.
     */
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'USERNAME' => 'newuser123',
            'PASSWORD' => 'Password@123456789',
            'HOTEN' => 'New User',
            'EMAIL' => 'newuser@example.com',
            'SDT' => '0987654321',
        ]);

        // Register trả về respondWithToken (status 200)
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'access_token',
                     'token_type',
                     'expires_in',
                     'user'
                 ]);

        $this->assertDatabaseHas('taikhoan', [
            'USERNAME' => 'newuser123',
            'EMAIL' => 'newuser@example.com',
            'MAROLE' => 3 // Khách hàng
        ]);

        $this->assertDatabaseHas('khachhang', [
            'USERNAME' => 'newuser123',
        ]);
    }

    /**
     * Test register fails with weak password.
     */
    public function test_register_fails_with_weak_password(): void
    {
        $response = $this->postJson('/api/register', [
            'USERNAME' => 'weakuser',
            'PASSWORD' => 'short',
            'HOTEN' => 'Weak User',
            'EMAIL' => 'weak@example.com',
            'SDT' => '0987654321',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['PASSWORD']);
    }

    /**
     * Test register fails with duplicate username.
     */
    public function test_register_fails_with_duplicate_username(): void
    {
        $response = $this->postJson('/api/register', [
            'USERNAME' => 'admin', // Already exists from seeder
            'PASSWORD' => 'Password@123456789',
            'HOTEN' => 'Dup User',
            'EMAIL' => 'dup@example.com',
            'SDT' => '0987654321',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['USERNAME']);
    }
}
