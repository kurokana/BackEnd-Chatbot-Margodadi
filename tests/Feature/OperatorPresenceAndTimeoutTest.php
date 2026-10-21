<?php

namespace Tests\Feature;

use App\Enums\OperatorRole;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorPresenceAndTimeoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_operator_status_online_and_records_activity(): void
    {
        $operator = Operator::factory()->create([
            'email' => 'operator1@margodadi.desa.id',
            'password' => bcrypt('password123'),
            'status' => OperatorStatus::OFFLINE,
            'last_activity_at' => null,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'operator1@margodadi.desa.id',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $operator->refresh();
        $this->assertEquals(OperatorStatus::ONLINE, $operator->status);
        $this->assertNotNull($operator->last_activity_at);
        $this->assertTrue($operator->last_activity_at->diffInSeconds(now()) < 5);
    }

    public function test_logout_sets_operator_status_offline(): void
    {
        $operator = Operator::factory()->create([
            'email' => 'operator2@margodadi.desa.id',
            'status' => OperatorStatus::ONLINE,
            'last_activity_at' => now(),
        ]);
        $token = $operator->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);

        $operator->refresh();
        $this->assertEquals(OperatorStatus::OFFLINE, $operator->status);
    }

    public function test_heartbeat_updates_activity_timestamp(): void
    {
        $operator = Operator::factory()->create([
            'email' => 'operator3@margodadi.desa.id',
            'status' => OperatorStatus::ONLINE,
            'last_activity_at' => now()->subMinutes(2),
        ]);
        $token = $operator->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/heartbeat');

        $response->assertStatus(200);

        $operator->refresh();
        $this->assertTrue($operator->last_activity_at->diffInSeconds(now()) < 5);
    }

    public function test_operator_times_out_after_five_minutes_of_inactivity(): void
    {
        $admin = Operator::factory()->create([
            'email' => 'admin@margodadi.desa.id',
            'role' => OperatorRole::ADMIN,
            'status' => OperatorStatus::ONLINE,
            'last_activity_at' => now(),
        ]);
        $token = $admin->createToken('test_token')->plainTextToken;

        // Operator inactive for 6 minutes (exceeding 5-minute timeout)
        $inactiveOp = Operator::factory()->create([
            'name' => 'Inactive Operator',
            'email' => 'inactive@margodadi.desa.id',
            'status' => OperatorStatus::ONLINE,
            'last_activity_at' => now()->subMinutes(6),
        ]);

        // Operator active 2 minutes ago (within 5-minute window)
        $activeOp = Operator::factory()->create([
            'name' => 'Active Operator',
            'email' => 'active@margodadi.desa.id',
            'status' => OperatorStatus::ONLINE,
            'last_activity_at' => now()->subMinutes(2),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/operators');

        $response->assertStatus(200);

        $data = collect($response->json('data'));
        $inactiveRow = $data->firstWhere('email', 'inactive@margodadi.desa.id');
        $activeRow = $data->firstWhere('email', 'active@margodadi.desa.id');

        $this->assertEquals('OFFLINE', $inactiveRow['status']);
        $this->assertEquals('ONLINE', $activeRow['status']);

        // Check that inactive operator was also updated to OFFLINE in database
        $inactiveOp->refresh();
        $this->assertEquals(OperatorStatus::OFFLINE, $inactiveOp->status);
    }

    public function test_operator_token_invalidated_when_idle_exceeds_sixty_minutes(): void
    {
        $operator = Operator::factory()->create([
            'email' => 'operator_idle@margodadi.desa.id',
            'role' => OperatorRole::OPERATOR,
            'status' => OperatorStatus::ONLINE,
            'last_activity_at' => now()->subMinutes(61), // Exceeded 60-minute threshold
        ]);
        $token = $operator->createToken('idle_session_token')->plainTextToken;

        // Any protected endpoint invoking UpdateOperatorActivity middleware
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
            'code' => 'IDLE_TIMEOUT',
        ]);

        // Verify operator status changed to OFFLINE and token revoked
        $operator->refresh();
        $this->assertEquals(OperatorStatus::OFFLINE, $operator->status);
        $this->assertCount(0, $operator->tokens);
    }
}
