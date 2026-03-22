<?php

namespace Tests\Unit\Services;

use App\Services\Sitemap\SitemapParser;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SitemapParserTest extends TestCase
{
    public function test_parses_simple_sitemap(): void
    {
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response($this->simpleSitemap([
                'https://example.com/',
                'https://example.com/about',
                'https://example.com/contact',
            ])),
        ]);

        $parser = new SitemapParser;
        $result = $parser->parse('https://example.com/sitemap.xml');

        $this->assertTrue($result['has_sitemap']);
        $this->assertEmpty($result['errors']);
        $this->assertCount(3, $result['urls']);
        $this->assertContains('https://example.com/', $result['urls']);
        $this->assertContains('https://example.com/about', $result['urls']);
    }

    public function test_parses_sitemap_index(): void
    {
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response($this->sitemapIndex([
                'https://example.com/sitemap-pages.xml',
                'https://example.com/sitemap-posts.xml',
            ])),
            'https://example.com/sitemap-pages.xml' => Http::response($this->simpleSitemap([
                'https://example.com/',
                'https://example.com/about',
            ])),
            'https://example.com/sitemap-posts.xml' => Http::response($this->simpleSitemap([
                'https://example.com/blog/post-1',
                'https://example.com/blog/post-2',
            ])),
        ]);

        $parser = new SitemapParser;
        $result = $parser->parse('https://example.com/sitemap.xml');

        $this->assertTrue($result['has_sitemap']);
        $this->assertEmpty($result['errors']);
        $this->assertCount(4, $result['urls']);
    }

    public function test_missing_sitemap_returns_not_found(): void
    {
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response('Not Found', 404),
        ]);

        $parser = new SitemapParser;
        $result = $parser->parse('https://example.com/sitemap.xml');

        $this->assertFalse($result['has_sitemap']);
        $this->assertEmpty($result['urls']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_invalid_xml_returns_error(): void
    {
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response('this is not xml'),
        ]);

        $parser = new SitemapParser;
        $result = $parser->parse('https://example.com/sitemap.xml');

        $this->assertTrue($result['has_sitemap']);
        $this->assertEmpty($result['urls']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_deduplicates_urls(): void
    {
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response($this->simpleSitemap([
                'https://example.com/page',
                'https://example.com/page',
                'https://example.com/page/',
            ])),
        ]);

        $parser = new SitemapParser;
        $result = $parser->parse('https://example.com/sitemap.xml');

        $this->assertCount(1, $result['urls']);
    }

    public function test_normalizes_urls(): void
    {
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response($this->simpleSitemap([
                'https://example.com/About/',
                'HTTPS://EXAMPLE.COM/contact',
            ])),
        ]);

        $parser = new SitemapParser;
        $result = $parser->parse('https://example.com/sitemap.xml');

        $this->assertContains('https://example.com/About', $result['urls']);
        $this->assertContains('https://example.com/contact', $result['urls']);
    }

    public function test_empty_sitemap_returns_no_urls(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';

        Http::fake([
            'https://example.com/sitemap.xml' => Http::response($xml),
        ]);

        $parser = new SitemapParser;
        $result = $parser->parse('https://example.com/sitemap.xml');

        $this->assertTrue($result['has_sitemap']);
        $this->assertEmpty($result['urls']);
        $this->assertEmpty($result['errors']);
    }

    public function test_connection_error_returns_error(): void
    {
        Http::fake([
            'https://example.com/sitemap.xml' => Http::response('', 500),
        ]);

        $parser = new SitemapParser;
        $result = $parser->parse('https://example.com/sitemap.xml');

        $this->assertFalse($result['has_sitemap']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Создать простой sitemap XML.
     *
     * @param  list<string>  $urls
     */
    protected function simpleSitemap(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $xml .= "<url><loc>{$url}</loc></url>";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Создать sitemap index XML.
     *
     * @param  list<string>  $sitemaps
     */
    protected function sitemapIndex(array $sitemaps): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($sitemaps as $sitemap) {
            $xml .= "<sitemap><loc>{$sitemap}</loc></sitemap>";
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }
}
