<?php

namespace App\Services\Whois;

/**
 * Парсер WHOIS-ответов (SRP).
 * Единственная ответственность — извлечение структурированных данных из текстового WHOIS.
 */
class WhoisParser
{
    /**
     * Извлечь дату регистрации из WHOIS данных
     */
    public function extractRegistrationDate(string $whoisData): ?string
    {
        $patterns = [
            '/Creation Date:\s*(.+)/i',
            '/Created:\s*(.+)/i',
            '/Registered on:\s*(.+)/i',
            '/Registration Date:\s*(.+)/i',
            '/created:\s*(.+)/i',
            '/created date:\s*(.+)/i',
            '/дата создания:\s*(.+)/i',
            '/registered:\s*(.+)/i',
        ];

        return $this->parseDateFromWhois($whoisData, $patterns);
    }

    /**
     * Извлечь дату истечения из WHOIS данных
     */
    public function extractExpirationDate(string $whoisData): ?string
    {
        $patterns = [
            '/Expiry Date:\s*(.+)/i',
            '/Expiration Date:\s*(.+)/i',
            '/Registry Expiry Date:\s*(.+)/i',
            '/Expires:\s*(.+)/i',
            '/expires:\s*(.+)/i',
            '/paid-till:\s*(.+)/i',
            '/paid till:\s*(.+)/i',
            '/дата окончания:\s*(.+)/i',
        ];

        return $this->parseDateFromWhois($whoisData, $patterns);
    }

    /**
     * Извлечь регистратора из WHOIS данных
     */
    public function extractRegistrar(string $whoisData): ?string
    {
        $patterns = [
            '/Registrar:\s*(.+)/i',
            '/Registrar Name:\s*(.+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $whoisData, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Парсинг даты из WHOIS данных
     */
    protected function parseDateFromWhois(string $whoisData, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $whoisData, $matches)) {
                $date = trim($matches[1]);
                $date = preg_replace('/\s+\(.*?\)/', '', $date);
                $date = trim($date, " \t\n\r\0\x0B.");

                // Пробуем strtotime
                $timestamp = strtotime($date);
                if ($timestamp && $timestamp > 0) {
                    return date('Y-m-d H:i:s', $timestamp);
                }

                // Если strtotime не сработал, пробуем парсить вручную
                if (preg_match('/(\d{4})[.\-](\d{2})[.\-](\d{2})/', $date, $dateMatches)) {
                    $timestamp = mktime(0, 0, 0, (int) $dateMatches[2], (int) $dateMatches[3], (int) $dateMatches[1]);
                    if ($timestamp) {
                        return date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }
        }

        return null;
    }
}


