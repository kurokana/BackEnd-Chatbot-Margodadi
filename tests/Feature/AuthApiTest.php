<?php

namespace Tests\Feature;

use App\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Aparatur Margodadi',
            'email' => 'operator@margodadi.desa.id',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '081234567890',
            'role' => 'OPERATOR',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'operator' => ['operator_id', 'name', 'email', 'role', 'status'],
                    'access_token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('operators', [
            'email' => 'operator@margodadi.desa.id',
        ]);
    }

    public function test_operator_can_login(): void
    {
        $operator = Operator::factory()->create([
            'email' => 'admin@margodadi.desa.id',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@margodadi.desa.id',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'operator',
                    'access_token',
                    'token_type',
                ],
            ]);
    }

    public function test_operator_can_login_with_username_alias(): void
    {
        $operator = Operator::factory()->create([
            'email' => 'admin@margodadi.desa.id',
            'password' => bcrypt('123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => '123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_authenticated_operator_can_fetch_profile(): void
    {
        $operator = Operator::factory()->create();
        $token = $operator->createToken('operator_auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'operator' => [
                        'operator_id' => $operator->operator_id,
                        'email' => $operator->email,
                    ],
                ],
            ]);
    }

    public function test_authenticated_operator_can_logout(): void
    {
        $operator = Operator::factory()->create();
        $token = $operator->createToken('operator_auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Successfully logged out',
            ]);

        $this->assertCount(0, $operator->fresh()->tokens);
    }
}
