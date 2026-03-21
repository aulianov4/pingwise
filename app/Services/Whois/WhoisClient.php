<?php

namespace App\Services\Whois;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент WHOIS (SRP) — Chain of Responsibility для получения данных.
 * Пробует: shell_exec → TCP-сокет → HTTP API.
 */
class WhoisClient implements WhoisClientInterface
{
    /**
     * Получить WHOIS-данные для домена
     */
    public function query(string $domain): ?string
    {
        return $this->queryViaShell($domain)
            ?? $this->queryViaTcp($domain)
            ?? $this->queryViaHttp($domain);
    }

    /**
     * Метод 1: Системная команда whois
     */
    protected function queryViaShell(string $domain): ?string
    {
        if (! function_exists('shell_exec')) {
            return null;
        }

        $whoisCommand = trim(shell_exec('which whois 2>/dev/null') ?: '');
        if (empty($whoisCommand)) {
            return null;
        }

        $output = @shell_exec("{$whoisCommand} ".escapeshellarg($domain).' 2>&1');

        if ($output && strlen($output) > 50 && ! $this->isEmptyWhoisResponse($output)) {
            return $output;
        }

        return null;
    }

    /**
     * Метод 2: TCP-соединение к WHOIS-серверу
     */
    protected function queryViaTcp(string $domain): ?string
    {
        $whoisServer = $this->getWhoisServer($domain);
        if (! $whoisServer) {
            return null;
        }

        $queries = [$domain];
        $parts = explode('.', $domain);
        if (count($parts) > 3) {
            $queries[] = implode('.', array_slice($parts, 1));
        }

        foreach ($queries as $query) {
            try {
                $socket = @fsockopen($whoisServer, 43, $errno, $errstr, 15);
                if (! $socket) {
                    continue;
                }

                stream_set_timeout($socket, 15);
                stream_set_blocking($socket, true);
                fwrite($socket, $query."\r\n");

                $response = '';
                $startTime = time();
                $meta = stream_get_meta_data($socket);

                while (! feof($socket) && (time() - $startTime) < 15 && ! $meta['timed_out']) {
                    $line = fgets($socket, 1024);
                    if ($line === false) {
                        break;
                    }
                    $response .= $line;
                    $meta = stream_get_meta_data($socket);
                }

                fclose($socket);

                if (! empty($response) && strlen($response) > 50 && ! $this->isEmptyWhoisResponse($response)) {
                    return $response;
                }
            } catch (\Exception $e) {
                // Продолжаем с другими методами
            }
        }

        return null;
    }

    /**
     * Метод 3: HTTP API (fallback)
     */
    protected function queryViaHttp(string $domain): ?string
    {
        try {
            $response = Http::timeout(10)
                ->get("https://ipwhois.app/json/{$domain}");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['success']) && $data['success']) {
                    $whoisText = '';
                    if (isset($data['created'])) {
                        $whoisText .= 'Creation Date: '.$data['created']."\n";
                    }
                    if (isset($data['expires'])) {
                        $whoisText .= 'Expiry Date: '.$data['expires']."\n";
                    }
                    if (isset($data['registrar'])) {
                        $whoisText .= 'Registrar: '.$data['registrar']."\n";
                    }
                    if (! empty($whoisText)) {
                        return $whoisText;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback не удался
        }

        Log::error("Не удалось получить WHOIS данные для домена: {$domain}");

        return null;
    }

    /**
     * Определить WHOIS-сервер для домена
     */
    protected function getWhoisServer(string $domain): ?string
    {
        $parts = explode('.', $domain);
        $tld = strtolower(end($parts));

        // Проверяем составные TLD
        if (count($parts) >= 2) {
            $secondLevel = strtolower($parts[count($parts) - 2]);
            $combinedTld = $secondLevel.'.'.$tld;

            $ruCombinedTlds = ['com.ru', 'org.ru', 'net.ru', 'pp.ru', 'msk.ru', 'spb.ru'];
            if (in_array($combinedTld, $ruCombinedTlds)) {
                return 'whois.tcinet.ru';
            }
        }

        $servers = [
            'ru' => 'whois.tcinet.ru',
            'рф' => 'whois.tcinet.ru',
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
     * Проверить, является ли WHOIS-ответ «пустым»
     */
    protected function isEmptyWhoisResponse(string $response): bool
    {
        return (bool) preg_match('/No entries found|No match|not found|NOT FOUND|No data found/i', $response);
    }
}
