<?php

namespace App\Services\Ssl;

/**
 * Интерфейс получения информации о SSL-сертификате (DIP).
 * Позволяет подменять реализацию в тестах.
 *
 * @phpstan-type CertificateInfo array{
 *     issuer_cn: string,
 *     subject_cn: string,
 *     valid_to: int,
 *     is_self_signed: bool,
 * }
 */
interface SslCheckerInterface
{
    /**
     * Получить информацию о SSL-сертификате.
     *
     * @return array{issuer_cn: string, subject_cn: string, valid_to: int, is_self_signed: bool}|null
     */
    public function getCertificateInfo(string $host, int $port = 443): ?array;
}
