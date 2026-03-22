<?php

namespace App\Providers;

use App\Events\TestStatusChanged;
use App\Listeners\SendTelegramAlert;
use App\Models\Site;
use App\Observers\SiteObserver;
use App\Services\Sitemap\SiteCrawler;
use App\Services\Sitemap\SiteCrawlerInterface;
use App\Services\Sitemap\SitemapChecker;
use App\Services\Sitemap\SitemapCheckerInterface;
use App\Services\Sitemap\SitemapParser;
use App\Services\Sitemap\SitemapParserInterface;
use App\Services\Ssl\SslChecker;
use App\Services\Ssl\SslCheckerInterface;
use App\Services\Telegram\TelegramBotInterface;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramMessageFormatter;
use App\Services\TestRegistry;
use App\Services\TestService;
use App\Services\Whois\WhoisClient;
use App\Services\Whois\WhoisClientInterface;
use App\Tests\AvailabilityTest;
use App\Tests\DomainTest;
use App\Tests\SitemapAuditTest;
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

        // SSL — привязка интерфейса к реализации (DIP)
        $this->app->bind(SslCheckerInterface::class, SslChecker::class);

        // Sitemap — привязка интерфейсов к реализациям (DIP)
        $this->app->bind(SitemapParserInterface::class, SitemapParser::class);
        $this->app->bind(SitemapCheckerInterface::class, SitemapChecker::class);
        $this->app->bind(SiteCrawlerInterface::class, SiteCrawler::class);

        // Регистрация отдельных тестов (OCP — для добавления нового теста
        // достаточно добавить строку сюда и создать класс, не меняя существующий код)
        $this->app->tag([
            AvailabilityTest::class,
            SslTest::class,
            DomainTest::class,
            SitemapAuditTest::class,
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
