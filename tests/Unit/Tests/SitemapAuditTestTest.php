<?php

namespace Tests\Unit\Tests;

use App\Models\Site;
use App\Models\SiteTest;
use App\Services\Sitemap\SiteCrawlerInterface;
use App\Services\Sitemap\SitemapCheckerInterface;
use App\Services\Sitemap\SitemapParserInterface;
use App\Tests\SitemapAuditTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapAuditTestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Подготовить мок SitemapParserInterface.
     *
     * @param  array{urls: list<string>, has_sitemap: bool, errors: list<string>}  $result
     */
    protected function mockSitemapParser(array $result): void
    {
        $mock = $this->createMock(SitemapParserInterface::class);
        $mock->method('parse')->willReturn($result);
        $this->app->instance(SitemapParserInterface::class, $mock);
    }

    /**
     * Подготовить мок SitemapCheckerInterface.
     *
     * @param  list<array{url: string, status_code: int, redirect_target: ?string}>  $results
     */
    protected function mockSitemapChecker(array $results): void
    {
        $mock = $this->createMock(SitemapCheckerInterface::class);
        $mock->method('checkUrls')->willReturn($results);
        $this->app->instance(SitemapCheckerInterface::class, $mock);
    }

    /**
     * Подготовить мок SiteCrawlerInterface.
     *
     * @param  array{pages: list<array{url: string, status_code: int, canonical: ?string, redirect_target: ?string}>, crawled_count: int, crawl_limited: bool}  $result
     */
    protected function mockSiteCrawler(array $result): void
    {
        $mock = $this->createMock(SiteCrawlerInterface::class);
        $mock->method('crawl')->willReturn($result);
        $this->app->instance(SiteCrawlerInterface::class, $mock);
    }

    public function test_metadata(): void
    {
        $this->mockSitemapParser(['urls' => [], 'has_sitemap' => false, 'errors' => []]);
        $this->mockSitemapChecker([]);
        $this->mockSiteCrawler(['pages' => [], 'crawled_count' => 0, 'crawl_limited' => false]);

        $test = $this->app->make(SitemapAuditTest::class);

        $this->assertEquals('sitemap', $test->getType());
        $this->assertEquals('Аудит карты сайта', $test->getName());
        $this->assertEquals(1440, $test->getDefaultInterval());
    }

    public function test_successful_audit_with_no_issues(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/about'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/about', 'status_code' => 200, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => 'https://example.com/', 'redirect_target' => null],
                ['url' => 'https://example.com/about', 'status_code' => 200, 'canonical' => 'https://example.com/about', 'redirect_target' => null],
            ],
            'crawled_count' => 2,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('success', $result->status);
        $this->assertEquals(2, $result->value['sitemap_urls_count']);
        $this->assertEquals(2, $result->value['crawled_urls_count']);
        $this->assertEquals(2, $result->value['checked_urls_count']);
        $this->assertEmpty($result->value['dead_pages']);
        $this->assertEmpty($result->value['missing_from_sitemap']);
        $this->assertEmpty($result->value['canonical_issues']);
    }

    public function test_missing_sitemap_returns_failed(): void
    {
        $this->mockSitemapParser([
            'urls' => [],
            'has_sitemap' => false,
            'errors' => ['Не удалось загрузить sitemap.xml: HTTP 404'],
        ]);

        $this->mockSitemapChecker([]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 1,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertFalse($result->value['has_sitemap']);
        $this->assertStringContainsString('Sitemap не найден', $result->message);
    }

    public function test_dead_pages_from_head_check(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/old-page'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/old-page', 'status_code' => 404, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 1,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertArrayHasKey('https://example.com/old-page', $result->value['non_200_pages']);
        $this->assertStringContainsString('битых страниц', $result->message);
    }

    public function test_missing_from_sitemap_returns_warning(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
                ['url' => 'https://example.com/new-page', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 2,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('warning', $result->status);
        $this->assertContains('https://example.com/new-page', $result->value['missing_from_sitemap']);
        $this->assertStringContainsString('не в sitemap', $result->message);
    }

    public function test_redirecting_in_sitemap_via_status_code(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/moved'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/moved', 'status_code' => 301, 'redirect_target' => 'https://example.com/new-location'],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 1,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('warning', $result->status);
        $this->assertContains('https://example.com/moved', $result->value['redirecting_in_sitemap']);
    }

    public function test_canonical_issues_from_crawler(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/page'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/page', 'status_code' => 200, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => 'https://example.com/', 'redirect_target' => null],
                ['url' => 'https://example.com/page', 'status_code' => 200, 'canonical' => 'https://example.com/other-page', 'redirect_target' => null],
            ],
            'crawled_count' => 2,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('warning', $result->status);
        $this->assertContains('https://example.com/page', $result->value['canonical_issues']);
        $this->assertStringContainsString('canonical', $result->message);
    }

    public function test_unreachable_page_via_head_check(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/unreachable'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/unreachable', 'status_code' => 0, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 1,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertContains('https://example.com/unreachable', $result->value['dead_pages']);
    }

    public function test_redirect_via_redirect_target_detected(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/old'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/old', 'status_code' => 200, 'redirect_target' => 'https://example.com/new'],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 1,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertContains('https://example.com/old', $result->value['redirecting_in_sitemap']);
    }

    public function test_crawl_limited_flag_is_propagated(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 1,
            'crawl_limited' => true,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertTrue($result->value['crawl_limited']);
    }

    public function test_uses_custom_settings_from_site_test(): void
    {
        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        SiteTest::factory()->sitemap()->createQuietly([
            'site_id' => $site->id,
            'settings' => [
                'interval_minutes' => 1440,
                'max_crawl_pages' => 50,
                'crawl_timeout_seconds' => 15,
                'sitemap_url' => '/custom-sitemap.xml',
                'check_concurrency' => 5,
            ],
        ]);

        $parserMock = $this->createMock(SitemapParserInterface::class);
        $parserMock->expects($this->once())
            ->method('parse')
            ->with('https://example.com/custom-sitemap.xml')
            ->willReturn(['urls' => [], 'has_sitemap' => true, 'errors' => []]);
        $this->app->instance(SitemapParserInterface::class, $parserMock);

        $checkerMock = $this->createMock(SitemapCheckerInterface::class);
        $checkerMock->expects($this->once())
            ->method('checkUrls')
            ->with([], 5)
            ->willReturn([]);
        $this->app->instance(SitemapCheckerInterface::class, $checkerMock);

        $crawlerMock = $this->createMock(SiteCrawlerInterface::class);
        $crawlerMock->expects($this->once())
            ->method('crawl')
            ->with('https://example.com', 50, 15)
            ->willReturn(['pages' => [], 'crawled_count' => 0, 'crawl_limited' => false]);
        $this->app->instance(SiteCrawlerInterface::class, $crawlerMock);

        $test = $this->app->make(SitemapAuditTest::class);
        $test->run($site);
    }

    public function test_server_error_pages_returns_failed(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/error'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/error', 'status_code' => 500, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 1,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertArrayHasKey('https://example.com/error', $result->value['non_200_pages']);
        $this->assertEquals(500, $result->value['non_200_pages']['https://example.com/error']);
    }

    public function test_multiple_issues_combined(): void
    {
        $this->mockSitemapParser([
            'urls' => [
                'https://example.com/',
                'https://example.com/dead',
                'https://example.com/moved',
            ],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/dead', 'status_code' => 404, 'redirect_target' => null],
            ['url' => 'https://example.com/moved', 'status_code' => 301, 'redirect_target' => 'https://example.com/new'],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
                ['url' => 'https://example.com/unlisted', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 2,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('failed', $result->status);
        $this->assertNotEmpty($result->value['non_200_pages']);
        $this->assertNotEmpty($result->value['redirecting_in_sitemap']);
        $this->assertNotEmpty($result->value['missing_from_sitemap']);
    }

    public function test_checked_urls_count_in_result(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/a', 'https://example.com/b'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/a', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/b', 'status_code' => 200, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
                ['url' => 'https://example.com/a', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
                ['url' => 'https://example.com/b', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 3,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals(3, $result->value['checked_urls_count']);
        $this->assertEquals(3, $result->value['sitemap_urls_count']);
    }

    /**
     * Trailing-slash URL из sitemap НЕ должен считаться редиректом.
     * Регрессия: UrlNormalizer убирал trailing slash, HEAD-запрос шёл на URL без слэша,
     * сервер возвращал 301 → URL со слэшем — ложный «редирект в sitemap».
     */
    public function test_trailing_slash_url_is_not_false_positive_redirect(): void
    {
        // Sitemap содержит URL с trailing slash (как реальный admiralpools.ru/sitemap.xml)
        $this->mockSitemapParser([
            'urls' => [
                'https://example.com/',
                'https://example.com/catalog/product/',
                'https://example.com/about/',
            ],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        // Checker получает ОРИГИНАЛЬНЫЕ URL (с trailing slash) → сервер отвечает 200 без редиректа
        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/catalog/product/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/about/', 'status_code' => 200, 'redirect_target' => null],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
                ['url' => 'https://example.com/catalog/product', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
                ['url' => 'https://example.com/about', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 3,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('success', $result->status);
        $this->assertEmpty($result->value['redirecting_in_sitemap']);
        $this->assertEmpty($result->value['dead_pages']);
        $this->assertEmpty($result->value['non_200_pages']);
    }

    /**
     * Trailing-slash редирект (redirect_target после нормализации совпадает с URL)
     * не должен считаться реальным редиректом — safety check.
     */
    public function test_trivial_trailing_slash_redirect_is_ignored(): void
    {
        $this->mockSitemapParser([
            'urls' => ['https://example.com/', 'https://example.com/page'],
            'has_sitemap' => true,
            'errors' => [],
        ]);

        // Сервер делает 200 но с redirect_target, который после нормализации совпадает с исходным URL
        // (например, /page → /page/ → normalized обратно в /page)
        $this->mockSitemapChecker([
            ['url' => 'https://example.com/', 'status_code' => 200, 'redirect_target' => null],
            ['url' => 'https://example.com/page', 'status_code' => 200, 'redirect_target' => 'https://example.com/page'],
        ]);

        $this->mockSiteCrawler([
            'pages' => [
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
                ['url' => 'https://example.com/page', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 2,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $result = $test->run($site);

        $this->assertEquals('success', $result->status);
        $this->assertEmpty($result->value['redirecting_in_sitemap']);
    }
}
