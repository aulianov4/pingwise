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
     * Автоматически добавляет has_deep_pages, max_crawl_depth и depth к страницам если не заданы.
     *
     * @param  array{pages: list<array{url: string, status_code: int, canonical: ?string, redirect_target: ?string, depth?: int}>, crawled_count: int, crawl_limited: bool, has_deep_pages?: bool, max_crawl_depth?: int}  $result
     */
    protected function mockSiteCrawler(array $result): void
    {
        $result['has_deep_pages'] ??= false;
        $result['max_crawl_depth'] ??= 0;
        $result['pages'] = array_map(
            fn (array $page): array => array_merge(['depth' => 0], $page),
            $result['pages'],
        );

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
        $this->assertEquals(0, $result->value['dead_count']);
        $this->assertEquals(0, $result->value['missing_count']);
        $this->assertEquals(0, $result->value['canonical_count']);

        // Все страницы записаны в audit_pages
        $this->assertDatabaseCount('audit_pages', 2);
        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/',
            'in_sitemap' => true,
            'in_crawl' => true,
            'status_code' => 200,
        ]);
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
        $this->assertEquals(1, $result->value['non_200_count']);
        $this->assertStringContainsString('битых страниц', $result->message);

        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/old-page',
            'in_sitemap' => true,
            'status_code' => 404,
        ]);
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
        $this->assertEquals(1, $result->value['missing_count']);
        $this->assertStringContainsString('не в sitemap', $result->message);

        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/new-page',
            'in_sitemap' => false,
            'in_crawl' => true,
        ]);
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
        $this->assertEquals(1, $result->value['redirect_count']);

        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/moved',
            'in_sitemap' => true,
            'status_code' => 301,
            'redirect_target' => 'https://example.com/new-location',
        ]);
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
        $this->assertEquals(1, $result->value['canonical_count']);
        $this->assertStringContainsString('canonical', $result->message);

        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/page',
            'canonical' => 'https://example.com/other-page',
        ]);
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
        $this->assertEquals(1, $result->value['dead_count']);

        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/unreachable',
            'in_sitemap' => true,
            'status_code' => 0,
        ]);
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

        $this->assertEquals(1, $result->value['redirect_count']);

        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/old',
            'in_sitemap' => true,
            'redirect_target' => 'https://example.com/new',
        ]);
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
            ->with('https://example.com', 50, 15, 5)
            ->willReturn(['pages' => [], 'crawled_count' => 0, 'crawl_limited' => false, 'has_deep_pages' => false, 'max_crawl_depth' => 0]);
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
        $this->assertEquals(1, $result->value['non_200_count']);

        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/error',
            'in_sitemap' => true,
            'status_code' => 500,
        ]);
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
        $this->assertGreaterThan(0, $result->value['non_200_count']);
        $this->assertGreaterThan(0, $result->value['redirect_count']);
        $this->assertGreaterThan(0, $result->value['missing_count']);

        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id, 'url' => 'https://example.com/dead', 'in_sitemap' => true, 'status_code' => 404,
        ]);
        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id, 'url' => 'https://example.com/moved', 'in_sitemap' => true, 'redirect_target' => 'https://example.com/new',
        ]);
        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id, 'url' => 'https://example.com/unlisted', 'in_sitemap' => false, 'in_crawl' => true,
        ]);
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
        $this->mockSitemapParser([
            'urls' => [
                'https://example.com/',
                'https://example.com/catalog/product/',
                'https://example.com/about/',
            ],
            'has_sitemap' => true,
            'errors' => [],
        ]);

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
        $this->assertEquals(0, $result->value['redirect_count']);
        $this->assertEquals(0, $result->value['dead_count']);
        $this->assertEquals(0, $result->value['non_200_count']);
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
        $this->assertEquals(0, $result->value['redirect_count']);
    }

    public function test_audit_pages_upserted_on_second_run(): void
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
                ['url' => 'https://example.com/', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
                ['url' => 'https://example.com/page', 'status_code' => 200, 'canonical' => null, 'redirect_target' => null],
            ],
            'crawled_count' => 2,
            'crawl_limited' => false,
        ]);

        $site = Site::factory()->createQuietly(['url' => 'https://example.com']);
        $test = $this->app->make(SitemapAuditTest::class);
        $test->run($site);

        // Второй прогон — /page исчезает из sitemap
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
            'crawl_limited' => false,
        ]);

        // Новый экземпляр берёт обновлённые моки из контейнера
        $test = $this->app->make(SitemapAuditTest::class);
        $test->run($site);

        // Записей всё ещё 2 (upsert, не дублируем)
        $this->assertDatabaseCount('audit_pages', 2);

        // /page помечена как не в sitemap и не в crawl
        $this->assertDatabaseHas('audit_pages', [
            'site_id' => $site->id,
            'url' => 'https://example.com/page',
            'in_sitemap' => false,
            'in_crawl' => false,
        ]);
    }
}
