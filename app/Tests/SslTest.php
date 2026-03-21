<?php

namespace App\Tests;

use App\Models\Site;

class SslTest extends BaseTest
{
    public function getType(): string
    {
        return 'ssl';
    }

    public function getName(): string
    {
        return 'SSL сертификат';
    }

    public function getDefaultInterval(): int
    {
        return 24 * 60; // 24 часа
    }

    protected function execute(Site $site): array
    {
        $url = parse_url($site->url, PHP_URL_HOST);
        if (! $url) {
            return [
                'status' => 'failed',
                'message' => 'Не удалось извлечь домен из URL',
            ];
        }

        $port = parse_url($site->url, PHP_URL_PORT) ?: 443;

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $socket = @stream_socket_client(
                "ssl://{$url}:{$port}",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (! $socket) {
                return [
                    'status' => 'failed',
                    'value' => [
                        'is_valid' => false,
                        'error' => 'connection_failed',
                    ],
                    'message' => "Не удалось подключиться к SSL: {$errstr} ({$errno})",
                ];
            }

            $params = stream_context_get_params($socket);
            $cert = $params['options']['ssl']['peer_certificate'];

            if (! $cert) {
                return [
                    'status' => 'failed',
                    'value' => [
                        'is_valid' => false,
                        'error' => 'no_certificate',
                    ],
                    'message' => 'SSL сертификат не найден',
                ];
            }

            $certData = openssl_x509_parse($cert);
            fclose($socket);

            if (! $certData) {
                return [
                    'status' => 'failed',
                    'value' => [
                        'is_valid' => false,
                        'error' => 'parse_error',
                    ],
                    'message' => 'Ошибка парсинга SSL сертификата',
                ];
            }

            // Проверка на самоподписанный сертификат
            $isSelfSigned = isset($certData['issuer']['CN']) &&
                           isset($certData['subject']['CN']) &&
                           $certData['issuer']['CN'] === $certData['subject']['CN'];

            $validTo = $certData['validTo_time_t'];
            $expiresAt = date('Y-m-d H:i:s', $validTo);
            $daysUntilExpiry = floor(($validTo - time()) / 86400);

            // Проверка условий: не самоподписанный и срок больше 3 дней
            $isValid = ! $isSelfSigned && $daysUntilExpiry > 3;
            $isWarning = $daysUntilExpiry <= 30 && $daysUntilExpiry > 3; // Предупреждение если меньше 30 дней

            return [
                'status' => $this->determineStatus($isValid, $isWarning),
                'value' => [
                    'is_valid' => $isValid,
                    'is_self_signed' => $isSelfSigned,
                    'issuer' => $certData['issuer']['CN'] ?? 'Unknown',
                    'subject' => $certData['subject']['CN'] ?? 'Unknown',
                    'expires_at' => $expiresAt,
                    'days_until_expiry' => $daysUntilExpiry,
                ],
                'message' => $isSelfSigned
                    ? 'SSL сертификат самоподписанный'
                    : ($daysUntilExpiry <= 3
                        ? "SSL сертификат истекает через {$daysUntilExpiry} дней"
                        : ($isWarning
                            ? "SSL сертификат действителен, но истекает через {$daysUntilExpiry} дней"
                            : "SSL сертификат действителен, истекает через {$daysUntilExpiry} дней")),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'value' => [
                    'is_valid' => false,
                    'error' => 'exception',
                ],
                'message' => 'Ошибка при проверке SSL: '.$e->getMessage(),
            ];
        }
    }
}
