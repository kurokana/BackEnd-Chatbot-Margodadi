<?php

namespace Tests\Feature;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\OperatorRole;
use App\Enums\PriorityLevel;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Operator;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConversationApiTest extends TestCase
{
    use RefreshDatabase;

    private Operator $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Operator::factory()->create([
            'email' => 'admin@margodadi.desa.id',
            'role' => OperatorRole::ADMIN,
        ]);
        $this->token = $this->admin->createToken('test_token')->plainTextToken;
    }

    public function test_can_list_conversations_with_filtering(): void
    {
        $user = User::factory()->create();
        $category = ServiceCategory::create([
            'name' => 'Layanan Kependudukan',
            'slug' => 'layanan-kependudukan',
            'domain' => \App\Enums\ServiceDomain::PUBLIC_SERVICE,
            'is_active' => true,
        ]);

        Conversation::create([
            'user_id' => $user->user_id,
            'category_id' => $category->category_id,
            'external_conversation_id' => 'CV-00999',
            'channel' => ChannelType::WHATSAPP,
            'status' => ConversationStatus::OPEN,
            'priority' => PriorityLevel::HIGH,
            'needs_human' => true,
            'citizen_name' => 'Warga Test',
            'started_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/admin/conversations?status=NEED_HUMAN');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'conversation_id',
                        'citizen_name',
                        'channel',
                        'category',
                        'status',
                        'needs_human',
                        'priority',
                    ],
                ],
                'meta',
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_view_conversation_detail(): void
    {
        $user = User::factory()->create();
        $conv = Conversation::create([
            'user_id' => $user->user_id,
            'external_conversation_id' => 'CV-00123',
            'channel' => ChannelType::WEB,
            'status' => ConversationStatus::OPEN,
            'priority' => PriorityLevel::MEDIUM,
            'citizen_name' => 'Bpk. Joko',
            'started_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conv->conversation_id,
            'content' => 'Halo tanya syarat SKU',
            'sender_type' => SenderType::USER,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/admin/conversations/CV-00123');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => 'CV-00123',
                    'citizen_name' => 'Bpk. Joko',
                ],
            ]);
    }

    public function test_operator_can_reply_and_resolve_conversation(): void
    {
        $user = User::factory()->create();
        $conv = Conversation::create([
            'user_id' => $user->user_id,
            'external_conversation_id' => 'CV-00123',
            'channel' => ChannelType::WEB,
            'status' => ConversationStatus::OPEN,
            'needs_human' => true,
            'priority' => PriorityLevel::HIGH,
            'started_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/admin/conversations/CV-00123/reply', [
                'content' => 'Selamat pagi, syarat SKU adalah fotokopi KTP dan pengantar RT.',
                'close_conversation' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'conversation_status' => 'RESOLVED',
                    'needs_human' => false,
                ],
            ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conv->conversation_id,
            'sender_type' => 'OPERATOR',
            'content' => 'Selamat pagi, syarat SKU adalah fotokopi KTP dan pengantar RT.',
        ]);
    }
}
