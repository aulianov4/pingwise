<?php

namespace App\Providers;

use App\Events\TestStatusChanged;
use App\Listeners\SendTelegramAlert;
use App\Models\Site;
use App\Observers\SiteObserver;
use App\Services\Telegram\TelegramBotInterface;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramMessageFormatter;
use App\Services\TestRegistry;
use App\Services\TestService;
use App\Services\Whois\WhoisClient;
use App\Services\Whois\WhoisClientInterface;
use App\Services\Whois\WhoisParser;
use App\Tests\AvailabilityTest;
use App\Tests\DomainTest;
use App\Tests\SslTest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Привязки к контейнеру (DIP): зависимости конфигурируются здесь,
     * а не создаются через new внутри классов.
     */
    public function register(): void
    {
        // WHOIS — привязка интерфейса к реализации (DIP)
        $this->app->bind(WhoisClientInterface::class, WhoisClient::class);

        // Регистрация отдельных тестов (OCP — для добавления нового теста
        // достаточно добавить строку сюда и создать класс, не меняя существующий код)
        $this->app->tag([
            AvailabilityTest::class,
            SslTest::class,
            DomainTest::class,
        ], 'site_tests');

        // Реестр тестов — получает тесты через tagged bindings
        $this->app->singleton(TestRegistry::class, function ($app) {
            return new TestRegistry($app->tagged('site_tests'));
        });

        // Сервис запуска тестов
        $this->app->singleton(TestService::class, function ($app) {
            return new TestService(
                $app->make(TestRegistry::class),
                $app->make(\Illuminate\Contracts\Events\Dispatcher::class),
            );
        });

        // Telegram — привязка интерфейса к реализации (DIP)
        $this->app->singleton(TelegramBotInterface::class, function ($app) {
            return new TelegramBotService(
                config('services.telegram.bot_token', ''),
            );
        });

        $this->app->singleton(TelegramMessageFormatter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрация Observer вместо логики в Site::boot() (SRP, DIP)
        Site::observe(SiteObserver::class);

        // Telegram-алерт при смене статуса теста
        Event::listen(TestStatusChanged::class, SendTelegramAlert::class);
    }
}
