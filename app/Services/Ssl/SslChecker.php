<?php

namespace App\Services\Ssl;

/**
 * Реализация проверки SSL-сертификата через stream_socket_client (SRP).
 * Ответственность: только получение данных сертификата через сеть.
 */
class SslChecker implements SslCheckerInterface
{
    /**
     * {@inheritDoc}
     */
    public function getCertificateInfo(string $host, int $port = 443): ?array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $socket) {
            return null;
        }

        $params = stream_context_get_params($socket);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! $cert) {
            fclose($socket);

            return null;
        }

        $certData = openssl_x509_parse($cert);
        fclose($socket);

        if (! $certData) {
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
