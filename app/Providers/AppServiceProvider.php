<?php

namespace App\Providers;

use App\Models\Site;
use App\Observers\SiteObserver;
use App\Services\Notifications\ChannelDriverRegistry;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\TelegramChannelDriver;
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
use App\Services\Telegram\TelegramConnectService;
use App\Services\Telegram\TelegramMessageFormatter;
use App\Services\TestRegistry;
use App\Services\TestService;
use App\Services\Whois\WhoisClient;
use App\Services\Whois\WhoisClientInterface;
use App\Tests\AvailabilityTest;
use App\Tests\DomainTest;
use App\Tests\SslTest;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\App;
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
        // SitemapAuditTest временно не регистрируется: парсинг sitemap и краулинг
        // работают некорректно и не должны запускаться планировщиком.
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
                $app->make(Dispatcher::class),
            );
        });

        // Telegram — привязка интерфейса к реализации (DIP)
        $this->app->singleton(TelegramBotInterface::class, function ($app) {
            $proxy = config('services.telegram.proxy');

            return new TelegramBotService(
                config('services.telegram.bot_token', ''),
                config('services.telegram.api_base_url', 'https://api.telegram.org'),
                is_string($proxy) && $proxy !== '' ? $proxy : null,
            );
        });

        $this->app->singleton(TelegramMessageFormatter::class);
        $this->app->singleton(TelegramConnectService::class);

        // Каналы уведомлений (OCP — новый драйвер = новая строка в tag)
        $this->app->tag([
            TelegramChannelDriver::class,
        ], 'notification_channel_drivers');

        $this->app->singleton(ChannelDriverRegistry::class, function ($app) {
            return new ChannelDriverRegistry($app->tagged('notification_channel_drivers'));
        });

        $this->app->singleton(NotificationDispatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Принудительно устанавливаем русский язык интерфейса
        App::setLocale('ru');
        App::setFallbackLocale('ru');

        // Регистрация Observer вместо логики в Site::boot() (SRP, DIP)
        Site::observe(SiteObserver::class);
    }
}
