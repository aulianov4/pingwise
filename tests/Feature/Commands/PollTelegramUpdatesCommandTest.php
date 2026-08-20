<?php

namespace Tests\Feature\Commands;

use App\Models\NotificationChannel;
use App\Services\Telegram\TelegramBotInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

class PollTelegramUpdatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_connects_channel_from_update(): void
    {
        $channel = NotificationChannel::factory()->create([
            'connect_token' => 'PW-POLL',
            'connect_token_expires_at' => now()->addMinutes(30),
        ]);

        $this->mock(TelegramBotInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getUpdates')->once()->andReturn([
                [
                    'update_id' => 42,
                    'message' => [
                        'text' => '/connect PW-POLL',
                        'chat' => [
                            'id' => -100777,
                            'type' => 'supergroup',
                            'title' => 'Ops',
                        ],
                    ],
                ],
            ]);
            $mock->shouldReceive('sendMessage')->once()->andReturn(true);
        });

        $this->artisan('pingwise:telegram:poll', ['--timeout' => 0])
            ->expectsOutput('Обработано обновлений: 1')
            ->assertSuccessful();

        $channel->refresh();

        $this->assertSame(-100777, $channel->telegram_chat_id);
        $this->assertSame(43, Cache::get('telegram.update_offset'));
    }
}
