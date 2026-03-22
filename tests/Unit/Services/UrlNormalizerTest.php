<?php

namespace Tests\Unit\Services;

use App\Services\Sitemap\UrlNormalizer;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    public function test_removes_trailing_slash(): void
    {
        $this->assertEquals('https://example.com/about', UrlNormalizer::normalize('https://example.com/about/'));
    }

    public function test_keeps_root_path_slash(): void
    {
        $this->assertEquals('https://example.com/', UrlNormalizer::normalize('https://example.com/'));
    }

    public function test_lowercases_scheme_and_host(): void
    {
        $this->assertEquals('https://example.com/Page', UrlNormalizer::normalize('HTTPS://EXAMPLE.COM/Page'));
    }

    public function test_removes_fragment(): void
    {
        // Fragments are stripped by parse_url — they don't appear in normalized output
        $this->assertEquals('https://example.com/page', UrlNormalizer::normalize('https://example.com/page#section'));
    }

    public function test_sorts_query_parameters(): void
    {
        $this->assertEquals(
            'https://example.com/search?a=1&b=2',
            UrlNormalizer::normalize('https://example.com/search?b=2&a=1')
        );
    }

    public function test_removes_default_https_port(): void
    {
        $this->assertEquals('https://example.com/', UrlNormalizer::normalize('https://example.com:443/'));
    }

    public function test_removes_default_http_port(): void
    {
        $this->assertEquals('http://example.com/', UrlNormalizer::normalize('http://example.com:80/'));
    }

    public function test_keeps_non_default_port(): void
    {
        $this->assertEquals('https://example.com:8080/page', UrlNormalizer::normalize('https://example.com:8080/page'));
    }

    public function test_normalizes_double_slashes_in_path(): void
    {
        $this->assertEquals('https://example.com/a/b', UrlNormalizer::normalize('https://example.com//a//b'));
    }

    public function test_resolves_dot_segments(): void
    {
        $this->assertEquals('https://example.com/a/c', UrlNormalizer::normalize('https://example.com/a/b/../c'));
    }

    public function test_is_same_host_true(): void
    {
        $this->assertTrue(UrlNormalizer::isSameHost('https://example.com/page', 'example.com'));
    }

    public function test_is_same_host_case_insensitive(): void
    {
        $this->assertTrue(UrlNormalizer::isSameHost('https://EXAMPLE.COM/page', 'example.com'));
    }

    public function test_is_same_host_false(): void
    {
        $this->assertFalse(UrlNormalizer::isSameHost('https://other.com/page', 'example.com'));
    }

    public function test_is_same_host_invalid_url(): void
    {
        $this->assertFalse(UrlNormalizer::isSameHost('not-a-url', 'example.com'));
    }

    public function test_resolve_relative_absolute_url_unchanged(): void
    {
        $this->assertEquals(
            'https://example.com/page',
            UrlNormalizer::resolveRelative('https://example.com/page', 'https://example.com/')
        );
    }

    public function test_resolve_relative_root_path(): void
    {
        $this->assertEquals(
            'https://example.com/about',
            UrlNormalizer::resolveRelative('/about', 'https://example.com/page')
        );
    }

    public function test_resolve_relative_relative_path(): void
    {
        $this->assertEquals(
            'https://example.com/blog/post',
            UrlNormalizer::resolveRelative('post', 'https://example.com/blog/index')
        );
    }

    public function test_resolve_relative_protocol_relative(): void
    {
        $this->assertEquals(
            'https://cdn.example.com/file',
            UrlNormalizer::resolveRelative('//cdn.example.com/file', 'https://example.com/')
        );
    }

    public function test_normalize_invalid_url_returns_trimmed(): void
    {
        $this->assertEquals('not-a-url', UrlNormalizer::normalize('  not-a-url  '));
    }

    public function test_normalize_url_without_path(): void
    {
        $this->assertEquals('https://example.com/', UrlNormalizer::normalize('https://example.com'));
    }
}
