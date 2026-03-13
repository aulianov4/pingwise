<?php

namespace App\Tests;

use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        // Метод 1: Используем системную команду whois, если доступна
        if (function_exists('shell_exec')) {
            $whoisCommand = trim(shell_exec('which whois 2>/dev/null') ?: '');
            if (!empty($whoisCommand)) {
                $output = @shell_exec("{$whoisCommand} " . escapeshellarg($domain) . " 2>&1");
                if ($output && strlen($output) > 50 && !preg_match('/No entries found|No match|not found|NOT FOUND|No data found/i', $output)) {
                    return $output;
                }
            }
        }

        // Метод 2: TCP-соединение к WHOIS серверу
        $whoisServer = $this->getWhoisServer($domain);
        if ($whoisServer) {
            // Для доменов с поддоменами пробуем также без первого поддомена
            $queries = [$domain];
            $parts = explode('.', $domain);
            if (count($parts) > 3) {
                $queries[] = implode('.', array_slice($parts, 1));
            }
            
            foreach ($queries as $query) {
                try {
                    $socket = @fsockopen($whoisServer, 43, $errno, $errstr, 15);
                    if ($socket) {
                        stream_set_timeout($socket, 15);
                        stream_set_blocking($socket, true);
                        fwrite($socket, $query . "\r\n");
                        
                        $response = '';
                        $startTime = time();
                        $meta = stream_get_meta_data($socket);
                        
                        while (!feof($socket) && (time() - $startTime) < 15 && !$meta['timed_out']) {
                            $line = fgets($socket, 1024);
                            if ($line === false) {
                                break;
                            }
                            $response .= $line;
                            $meta = stream_get_meta_data($socket);
                        }

                        fclose($socket);

                        if (!empty($response) && strlen($response) > 50 && !preg_match('/No entries found|No match|not found|NOT FOUND|No data found/i', $response)) {
                            return $response;
                        }
                    }
                } catch (\Exception $e) {
                    // Продолжаем попытки с другими методами
                }
            }
        }

        // Метод 3: HTTP API через внешние сервисы (fallback)
        $httpResult = $this->getWhoisViaHttp($domain);
        if ($httpResult) {
            return $httpResult;
        }

        Log::error("Не удалось получить WHOIS данные для домена: {$domain}");
        return null;
    }

    /**
     * Получить WHOIS через HTTP API (fallback метод)
     */
    protected function getWhoisViaHttp(string $domain): ?string
    {
        // Пробуем бесплатный API сервис
        try {
            $response = Http::timeout(10)
                ->get("https://ipwhois.app/json/{$domain}");
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success']) {
                    $whoisText = '';
                    if (isset($data['created'])) {
                        $whoisText .= "Creation Date: " . $data['created'] . "\n";
                    }
                    if (isset($data['expires'])) {
                        $whoisText .= "Expiry Date: " . $data['expires'] . "\n";
                    }
                    if (isset($data['registrar'])) {
                        $whoisText .= "Registrar: " . $data['registrar'] . "\n";
                    }
                    if (!empty($whoisText)) {
                        return $whoisText;
                    }
                }
            }
        } catch (\Exception $e) {
            // Продолжаем без этого метода
        }

        return null;
    }

    /**
     * Определить WHOIS сервер для домена
     */
    protected function getWhoisServer(string $domain): ?string
    {
        // Определяем TLD домена с учетом составных TLD (com.ru, org.ru, net.ru и т.д.)
        $parts = explode('.', $domain);
        $tld = strtolower(end($parts));
        
        // Проверяем составные TLD для российских доменов
        if (count($parts) >= 2) {
            $secondLevel = strtolower($parts[count($parts) - 2]);
            $combinedTld = $secondLevel . '.' . $tld;
            
            // Составные TLD для .ru доменов
            $ruCombinedTlds = ['com.ru', 'org.ru', 'net.ru', 'pp.ru', 'msk.ru', 'spb.ru'];
            if (in_array($combinedTld, $ruCombinedTlds)) {
                // Для всех .ru доменов (включая составные) используем один сервер
                return 'whois.tcinet.ru';
            }
        }

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
    protected function extractExpirationDate(string $whoisData): ?string
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
                if (preg_match('/(\d{4})[\.\-](\d{2})[\.\-](\d{2})/', $date, $dateMatches)) {
                    $timestamp = mktime(0, 0, 0, (int)$dateMatches[2], (int)$dateMatches[3], (int)$dateMatches[1]);
                    if ($timestamp) {
                        return date('Y-m-d H:i:s', $timestamp);
                    }
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
