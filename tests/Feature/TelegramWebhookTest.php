<?php

namespace Tests\Feature;

use App\Models\NotificationChannel;
use App\Models\Project;
use App\Services\Telegram\TelegramBotInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_invalid_secret(): void
    {
        $this->postJson('/telegram/webhook/wrong-secret', [])
            ->assertForbidden();
    }

    public function test_connect_binds_group_to_channel(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $project = Project::factory()->create(['name' => 'Acme']);
        $channel = NotificationChannel::factory()->create([
            'project_id' => $project->id,
            'name' => 'DevOps',
            'connect_token' => 'PW-TEST',
            'connect_token_expires_at' => now()->addMinutes(30),
        ]);

        $this->postJson('/telegram/webhook/test-webhook-secret', [
            'message' => [
                'text' => '/connect PW-TEST',
                'chat' => [
                    'id' => -100123,
                    'type' => 'supergroup',
                    'title' => 'Ops',
                ],
            ],
        ])->assertOk();

        $channel->refresh();

        $this->assertSame(-100123, $channel->telegram_chat_id);
        $this->assertNull($channel->connect_token);
        $this->assertTrue($channel->isConnected());
        $this->assertSame('Ops', $channel->connectedChatTitle());
    }

    public function test_connect_accepts_bot_mention_prefix(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $channel = NotificationChannel::factory()->create([
            'connect_token' => 'PW-MENT',
            'connect_token_expires_at' => now()->addMinutes(30),
        ]);

        $this->postJson('/telegram/webhook/test-webhook-secret', [
            'message' => [
                'text' => '@pingwise_bot /connect PW-MENT',
                'chat' => [
                    'id' => -100555,
                    'type' => 'supergroup',
                    'title' => 'Ops',
                ],
            ],
        ])->assertOk();

        $channel->refresh();

        $this->assertSame(-100555, $channel->telegram_chat_id);
    }

    public function test_connect_accepts_command_with_bot_suffix(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $channel = NotificationChannel::factory()->create([
            'connect_token' => 'PW-SUFX',
            'connect_token_expires_at' => now()->addMinutes(30),
        ]);

        $this->postJson('/telegram/webhook/test-webhook-secret', [
            'message' => [
                'text' => '/connect@pingwise_bot PW-SUFX',
                'chat' => [
                    'id' => -100556,
                    'type' => 'supergroup',
                    'title' => 'Ops',
                ],
            ],
        ])->assertOk();

        $channel->refresh();

        $this->assertSame(-100556, $channel->telegram_chat_id);
    }

    public function test_expired_token_does_not_connect(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $channel = NotificationChannel::factory()->expiredToken()->create([
            'connect_token' => 'PW-DEAD',
        ]);

        $this->postJson('/telegram/webhook/test-webhook-secret', [
            'message' => [
                'text' => '/connect PW-DEAD',
                'chat' => [
                    'id' => -100999,
                    'type' => 'supergroup',
                    'title' => 'Ops',
                ],
            ],
        ])->assertOk();

        $channel->refresh();

        $this->assertNull($channel->telegram_chat_id);
        $this->assertFalse($channel->isConnected());
    }

    public function test_unknown_token_does_not_connect(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $this->postJson('/telegram/webhook/test-webhook-secret', [
            'message' => [
                'text' => '/connect PW-NOPE',
                'chat' => [
                    'id' => -100111,
                    'type' => 'supergroup',
                    'title' => 'Ops',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('notification_channels', 0);
    }

    public function test_private_chat_is_rejected(): void
    {
        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $channel = NotificationChannel::factory()->create([
            'connect_token' => 'PW-PRIV',
            'connect_token_expires_at' => now()->addMinutes(30),
        ]);

        $this->postJson('/telegram/webhook/test-webhook-secret', [
            'message' => [
                'text' => '/connect PW-PRIV',
                'chat' => [
                    'id' => 555,
                    'type' => 'private',
                    'username' => 'user',
                ],
            ],
        ])->assertOk();

        $channel->refresh();

        $this->assertNull($channel->telegram_chat_id);
    }
}
