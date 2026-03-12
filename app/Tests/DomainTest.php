<?php

namespace App\Tests;

use App\Models\Site;

class DomainTest extends BaseTest
{
    public function getType(): string
    {
        return 'domain';
    }

    public function getName(): string
    {
        return 'Регистрация домена';
    }

    public function getDefaultInterval(): int
    {
        return 24 * 60; // 24 часа
    }

    protected function execute(Site $site): array
    {
        $url = parse_url($site->url, PHP_URL_HOST);
        if (!$url) {
            return [
                'status' => 'failed',
                'message' => 'Не удалось извлечь домен из URL',
            ];
        }

        // Убираем www. если есть
        $domain = preg_replace('/^www\./', '', $url);

        try {
            $whoisData = $this->getWhoisData($domain);

            if (!$whoisData) {
                return [
                    'status' => 'failed',
                    'value' => [
                        'is_valid' => false,
                        'error' => 'whois_unavailable',
                    ],
                    'message' => 'Не удалось получить данные WHOIS',
                ];
            }

            $registeredAt = $this->extractRegistrationDate($whoisData);
            $expiresAt = $this->extractExpirationDate($whoisData);
            $registrar = $this->extractRegistrar($whoisData);

            if (!$registeredAt) {
                return [
                    'status' => 'failed',
                    'value' => [
                        'is_valid' => false,
                        'error' => 'no_registration_date',
                    ],
                    'message' => 'Не удалось определить дату регистрации домена',
                ];
            }

            $daysSinceRegistration = floor((time() - strtotime($registeredAt)) / 86400);
            $isValid = $daysSinceRegistration > 20;

            $daysUntilExpiry = null;
            if ($expiresAt) {
                $daysUntilExpiry = floor((strtotime($expiresAt) - time()) / 86400);
                $isWarning = $daysUntilExpiry <= 30 && $daysUntilExpiry > 0;
            } else {
                $isWarning = false;
            }

            return [
                'status' => $this->determineStatus($isValid, $isWarning),
                'value' => [
                    'domain' => $domain,
                    'registered_at' => $registeredAt,
                    'expires_at' => $expiresAt,
                    'days_since_registration' => $daysSinceRegistration,
                    'days_until_expiry' => $daysUntilExpiry,
                    'registrar' => $registrar,
                ],
                'message' => $isValid
                    ? ($daysUntilExpiry !== null
                        ? "Домен зарегистрирован {$daysSinceRegistration} дней назад, истекает через {$daysUntilExpiry} дней"
                        : "Домен зарегистрирован {$daysSinceRegistration} дней назад")
                    : "Домен зарегистрирован менее 20 дней назад ({$daysSinceRegistration} дней)",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'value' => [
                    'is_valid' => false,
                    'error' => 'exception',
                ],
                'message' => 'Ошибка при проверке домена: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Получить данные WHOIS
     */
    protected function getWhoisData(string $domain): ?string
    {
        // Используем системную команду whois, если доступна
        if (function_exists('shell_exec') && !empty(shell_exec('which whois'))) {
            $output = @shell_exec("whois " . escapeshellarg($domain) . " 2>&1");
            return $output ?: null;
        }

        // Пробуем использовать TCP-соединение к WHOIS серверу
        try {
            $whoisServer = $this->getWhoisServer($domain);
            if (!$whoisServer) {
                return null;
            }

            $socket = @fsockopen($whoisServer, 43, $errno, $errstr, 10);
            if (!$socket) {
                return null;
            }

            // Отправляем запрос
            fwrite($socket, $domain . "\r\n");

            // Читаем ответ
            $response = '';
            while (!feof($socket)) {
                $response .= fgets($socket, 1024);
            }

            fclose($socket);

            return !empty($response) ? $response : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Определить WHOIS сервер для домена
     */
    protected function getWhoisServer(string $domain): ?string
    {
        // Определяем TLD домена
        $parts = explode('.', $domain);
        $tld = strtolower(end($parts));

        // Маппинг TLD на WHOIS серверы
        $servers = [
            'ru' => 'whois.tcinet.ru',
            'рф' => 'whois.tcinet.ru', // Для кириллических доменов
            'su' => 'whois.tcinet.ru',
            'com' => 'whois.verisign-grs.com',
            'net' => 'whois.verisign-grs.com',
            'org' => 'whois.pir.org',
            'info' => 'whois.afilias.net',
            'biz' => 'whois.neulevel.biz',
            'us' => 'whois.nic.us',
            'uk' => 'whois.nic.uk',
            'de' => 'whois.denic.de',
            'fr' => 'whois.afnic.fr',
            'it' => 'whois.nic.it',
            'nl' => 'whois.domain-registry.nl',
            'pl' => 'whois.dns.pl',
            'ua' => 'whois.ua',
            'by' => 'whois.cctld.by',
            'kz' => 'whois.nic.kz',
        ];

        return $servers[$tld] ?? null;
    }

    /**
     * Извлечь дату регистрации из WHOIS данных
     */
    protected function extractRegistrationDate(string $whoisData): ?string
    {
        // Паттерны для разных регистраторов
        $patterns = [
            '/Creation Date:\s*(.+)/i',
            '/Created:\s*(.+)/i',
            '/Registered on:\s*(.+)/i',
            '/Registration Date:\s*(.+)/i',
            '/created:\s*(.+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $whoisData, $matches)) {
                $date = trim($matches[1]);
                $timestamp = strtotime($date);
                if ($timestamp) {
                    return date('Y-m-d H:i:s', $timestamp);
                }
            }
        }

        return null;
    }

    /**
     * Извлечь дату истечения из WHOIS данных
     */
    protected function extractExpirationDate(string $whoisData): ?string
    {
        $patterns = [
            '/Expiry Date:\s*(.+)/i',
            '/Expiration Date:\s*(.+)/i',
            '/Registry Expiry Date:\s*(.+)/i',
            '/Expires:\s*(.+)/i',
            '/expires:\s*(.+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $whoisData, $matches)) {
                $date = trim($matches[1]);
                $timestamp = strtotime($date);
                if ($timestamp) {
                    return date('Y-m-d H:i:s', $timestamp);
                }
            }
        }

        return null;
    }

    /**
     * Извлечь регистратора из WHOIS данных
     */
    protected function extractRegistrar(string $whoisData): ?string
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
}
