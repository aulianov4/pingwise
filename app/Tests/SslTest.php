<?php

namespace App\Tests;

use App\Models\Site;
use App\Services\Ssl\SslCheckerInterface;

class SslTest extends BaseTest
{
    public function __construct(
        protected readonly SslCheckerInterface $sslChecker,
    ) {}

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
        $host = parse_url($site->url, PHP_URL_HOST);
        if (! $host) {
            return [
                'status' => 'failed',
                'message' => 'Не удалось извлечь домен из URL',
            ];
        }

        $port = parse_url($site->url, PHP_URL_PORT) ?: 443;

        try {
            $certInfo = $this->sslChecker->getCertificateInfo($host, $port);

            if (! $certInfo) {
                return [
                    'status' => 'failed',
                    'value' => [
                        'is_valid' => false,
                        'error' => 'connection_failed',
                    ],
                    'message' => 'Не удалось получить SSL сертификат',
                ];
            }

            $isSelfSigned = $certInfo['is_self_signed'];
            $validTo = $certInfo['valid_to'];
            $expiresAt = date('Y-m-d H:i:s', $validTo);
            $daysUntilExpiry = (int) floor(($validTo - time()) / 86400);

            $isValid = ! $isSelfSigned && $daysUntilExpiry > 3;
            $isWarning = $daysUntilExpiry <= 30 && $daysUntilExpiry > 3;

            return [
                'status' => $this->determineStatus($isValid, $isWarning),
                'value' => [
                    'is_valid' => $isValid,
                    'is_self_signed' => $isSelfSigned,
                    'issuer' => $certInfo['issuer_cn'],
                    'subject' => $certInfo['subject_cn'],
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
