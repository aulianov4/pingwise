<?php

namespace App\Tests;

use App\Models\Site;
use App\Services\Whois\WhoisClientInterface;
use App\Services\Whois\WhoisParser;

/**
 * Тест регистрации домена (SRP).
 * Ответственность: только бизнес-логика оценки данных домена.
 * Получение и парсинг WHOIS делегированы WhoisClient и WhoisParser (DIP).
 */
class DomainTest extends BaseTest
{
    public function __construct(
        protected readonly WhoisClientInterface $whoisClient,
        protected readonly WhoisParser $whoisParser,
    ) {}

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
            $whoisData = $this->whoisClient->query($domain);

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

            $registeredAt = $this->whoisParser->extractRegistrationDate($whoisData);
            $expiresAt = $this->whoisParser->extractExpirationDate($whoisData);
            $registrar = $this->whoisParser->extractRegistrar($whoisData);

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
}
