<?php

namespace App\Services\Ssl;

use Illuminate\Support\Facades\Log;

/**
 * Реализация проверки SSL-сертификата через stream_socket_client (SRP).
 * Ответственность: только получение данных сертификата через сеть.
 */
class SslChecker implements SslCheckerInterface
{
    /**
     * Максимальное количество попыток подключения.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Задержка между повторными попытками (мс).
     */
    private const RETRY_DELAY_MS = 500;

    /**
     * Таймаут одного TLS-подключения (секунды).
     */
    private const CONNECT_TIMEOUT = 10;

    /**
     * {@inheritDoc}
     */
    public function getCertificateInfo(string $host, int $port = 443): ?array
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 1) {
                usleep(self::RETRY_DELAY_MS * 1000);
            }

            $result = $this->attempt($host, $port, $lastError);

            if ($result !== null) {
                return $result;
            }
        }

        Log::warning('SSL certificate fetch failed after retries', [
            'host' => $host,
            'port' => $port,
            'attempts' => self::MAX_ATTEMPTS,
            'error' => $lastError,
        ]);

        return null;
    }

    /**
     * Одна попытка TLS-подключения и разбора сертификата.
     *
     * @param-out string|null $lastError
     *
     * @return array{issuer_cn: string, subject_cn: string, valid_to: int, is_self_signed: bool}|null
     */
    protected function attempt(string $host, int $port, ?string &$lastError): ?array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $socket) {
            $lastError = trim($errstr !== '' ? $errstr : "errno {$errno}");

            return null;
        }

        $params = stream_context_get_params($socket);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! $cert) {
            fclose($socket);
            $lastError = 'peer certificate missing';

            return null;
        }

        $certData = openssl_x509_parse($cert);
        fclose($socket);

        if (! $certData) {
            $lastError = 'openssl_x509_parse failed';

            return null;
        }

        $isSelfSigned = isset($certData['issuer']['CN'])
            && isset($certData['subject']['CN'])
            && $certData['issuer']['CN'] === $certData['subject']['CN'];

        return [
            'issuer_cn' => $certData['issuer']['CN'] ?? 'Unknown',
            'subject_cn' => $certData['subject']['CN'] ?? 'Unknown',
            'valid_to' => $certData['validTo_time_t'],
            'is_self_signed' => $isSelfSigned,
        ];
    }
}
