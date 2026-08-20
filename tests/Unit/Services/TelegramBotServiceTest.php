<?php

namespace Tests\Unit\Services;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotServiceTest extends TestCase
{
    public function test_send_message_posts_to_bot_api(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        $bot = new TelegramBotService('test-token');

        $this->assertTrue($bot->sendMessage(-100, 'Привет'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && (int) $request['chat_id'] === -100
                && $request['text'] === 'Привет';
        });
    }

    public function test_send_message_uses_custom_api_base_url(): void
    {
        Http::fake([
            'https://tg.example.test/bottest-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        $bot = new TelegramBotService('test-token', 'https://tg.example.test');

        $this->assertTrue($bot->sendMessage(1, 'ok'));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://tg.example.test/bottest-token/sendMessage');
    }

    public function test_send_message_succeeds_when_proxy_is_configured(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        $bot = new TelegramBotService(
            'test-token',
            'https://api.telegram.org',
            'socks5h://127.0.0.1:1080',
        );

        $this->assertTrue($bot->sendMessage(1, 'ok'));
    }

    public function test_set_webhook_posts_to_bot_api(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-token/setWebhook' => Http::response(['ok' => true]),
        ]);

        $bot = new TelegramBotService('test-token');

        $this->assertTrue($bot->setWebhook('https://pingwise.example/telegram/webhook/secret'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/setWebhook'
                && $request['url'] === 'https://pingwise.example/telegram/webhook/secret';
        });
    }

    public function test_get_updates_retries_after_webhook_conflict(): void
    {
        Http::fake([
            'https://api.telegram.org/bottest-token/getUpdates' => Http::sequence()
                ->push(['ok' => false, 'error_code' => 409, 'description' => 'Conflict'], 409)
                ->push(['ok' => true, 'result' => [['update_id' => 10]]]),
            'https://api.telegram.org/bottest-token/deleteWebhook' => Http::response(['ok' => true]),
        ]);

        $bot = new TelegramBotService('test-token');

        $this->assertSame([['update_id' => 10]], $bot->getUpdates(null, 0));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.telegram.org/bottest-token/deleteWebhook');
    }
}
