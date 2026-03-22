<?php

namespace Tests\Unit\Services;

use App\Services\Sitemap\SiteCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteCrawlerTest extends TestCase
{
    public function test_crawls_single_page(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('<html><body><h1>Home</h1></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $this->assertEquals(1, $result['crawled_count']);
        $this->assertFalse($result['crawl_limited']);
        $this->assertEquals('https://example.com/', $result['pages'][0]['url']);
        $this->assertEquals(200, $result['pages'][0]['status_code']);
    }

    public function test_follows_internal_links(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="/about">About</a><a href="/contact">Contact</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://example.com/about' => Http::response(
                '<html><body><h1>About</h1></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://example.com/contact' => Http::response(
                '<html><body><h1>Contact</h1></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $this->assertEquals(3, $result['crawled_count']);

        $urls = array_column($result['pages'], 'url');
        $this->assertContains('https://example.com/', $urls);
        $this->assertContains('https://example.com/about', $urls);
        $this->assertContains('https://example.com/contact', $urls);
    }

    public function test_does_not_follow_external_links(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="https://external.com/page">External</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $this->assertEquals(1, $result['crawled_count']);
        $urls = array_column($result['pages'], 'url');
        $this->assertNotContains('https://external.com/page', $urls);
    }

    public function test_respects_max_pages_limit(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="/a">A</a><a href="/b">B</a><a href="/c">C</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://example.com/a' => Http::response('<html><body>A</body></html>', 200, ['Content-Type' => 'text/html']),
            'https://example.com/b' => Http::response('<html><body>B</body></html>', 200, ['Content-Type' => 'text/html']),
            'https://example.com/c' => Http::response('<html><body>C</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 2, timeoutSeconds: 10);

        $this->assertEquals(2, $result['crawled_count']);
        $this->assertTrue($result['crawl_limited']);
    }

    public function test_extracts_canonical(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><head><link rel="canonical" href="https://example.com/canonical-page"></head><body>Home</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $this->assertEquals('https://example.com/canonical-page', $result['pages'][0]['canonical']);
    }

    public function test_handles_404_pages(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="/missing">Link</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://example.com/missing' => Http::response('Not Found', 404, ['Content-Type' => 'text/html']),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $missingPage = collect($result['pages'])->firstWhere('url', 'https://example.com/missing');
        $this->assertNotNull($missingPage);
        $this->assertEquals(404, $missingPage['status_code']);
    }

    public function test_skips_non_html_files(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="/image.jpg">Image</a><a href="/doc.pdf">PDF</a><a href="/page">Page</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://example.com/page' => Http::response('<html><body>Page</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $urls = array_column($result['pages'], 'url');
        $this->assertNotContains('https://example.com/image.jpg', $urls);
        $this->assertNotContains('https://example.com/doc.pdf', $urls);
        $this->assertContains('https://example.com/page', $urls);
    }

    public function test_does_not_visit_same_url_twice(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="/">Home</a><a href="/about">About</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://example.com/about' => Http::response(
                '<html><body><a href="/">Home</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $this->assertEquals(2, $result['crawled_count']);
    }

    public function test_skips_mailto_and_tel_links(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="mailto:test@example.com">Email</a><a href="tel:+1234567890">Phone</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $this->assertEquals(1, $result['crawled_count']);
    }

    public function test_handles_empty_base_host(): void
    {
        $crawler = new SiteCrawler;
        $result = $crawler->crawl('not-a-valid-url', maxPages: 10, timeoutSeconds: 10);

        $this->assertEquals(0, $result['crawled_count']);
        $this->assertEmpty($result['pages']);
    }

    public function test_normalizes_urls_during_crawl(): void
    {
        Http::fake([
            'https://example.com/' => Http::response(
                '<html><body><a href="/About/">About</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://example.com/About' => Http::response(
                '<html><body>About page</body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $crawler = new SiteCrawler;
        $result = $crawler->crawl('https://example.com/', maxPages: 10, timeoutSeconds: 10);

        $urls = array_column($result['pages'], 'url');
        // Trailing slash should be normalized, so /About/ → /About
        $this->assertContains('https://example.com/About', $urls);
    }
}
