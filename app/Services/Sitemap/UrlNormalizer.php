<?php

namespace App\Services\Sitemap;

/**
 * Нормализация URL для корректного сравнения (SRP).
 *
 * Приводит URL к единому виду: lowercase scheme/host,
 * убирает trailing slash, фрагмент, сортирует query-параметры.
 */
class UrlNormalizer
{
    /**
     * Нормализовать URL для сравнения.
     */
    public static function normalize(string $url): string
    {
        $parsed = parse_url(trim($url));

        if ($parsed === false || ! isset($parsed['host'])) {
            return trim($url);
        }

        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host']);
        $port = $parsed['port'] ?? null;
        $path = $parsed['path'] ?? '/';
        $query = $parsed['query'] ?? null;

        // Убираем trailing slash (кроме корневого пути)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Декодируем и нормализуем путь
        $path = self::normalizePath($path);

        // Сортируем query-параметры для консистентности
        if ($query !== null && $query !== '') {
            parse_str($query, $params);
            ksort($params);
            $query = http_build_query($params);
        }

        // Убираем дефолтные порты
        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = null;
        }

        $normalized = "{$scheme}://{$host}";

        if ($port !== null) {
            $normalized .= ":{$port}";
        }

        $normalized .= $path;

        if ($query !== null && $query !== '') {
            $normalized .= "?{$query}";
        }

        return $normalized;
    }

    /**
     * Проверить, принадлежит ли URL указанному хосту.
     */
    public static function isSameHost(string $url, string $baseHost): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false) {
            return false;
        }

        return strtolower($host) === strtolower($baseHost);
    }

    /**
     * Преобразовать относительный URL в абсолютный.
     */
    public static function resolveRelative(string $relative, string $baseUrl): string
    {
        // Уже абсолютный
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }

        $parsed = parse_url($baseUrl);

        if ($parsed === false || ! isset($parsed['host'])) {
            return $relative;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ":{$parsed['port']}" : '';

        // Protocol-relative
        if (str_starts_with($relative, '//')) {
            return "{$scheme}:{$relative}";
        }

        $base = "{$scheme}://{$host}{$port}";

        // Абсолютный путь от корня
        if (str_starts_with($relative, '/')) {
            return $base.$relative;
        }

        // Относительный путь
        $basePath = $parsed['path'] ?? '/';
        $baseDir = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);

        return $base.$baseDir.$relative;
    }

    /**
     * Нормализовать путь (убрать '.', '..', двойные слеши).
     */
    protected static function normalizePath(string $path): string
    {
        // Убираем двойные слеши
        $path = (string) preg_replace('#/+#', '/', $path);

        $segments = explode('/', $path);
        $result = [];

        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($result);
            } elseif ($segment !== '.' && $segment !== '') {
                $result[] = $segment;
            }
        }

        return '/'.implode('/', $result);
    }
}
