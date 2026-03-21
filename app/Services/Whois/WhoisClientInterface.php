<?php

namespace App\Services\Whois;

/**
 * Интерфейс клиента WHOIS (DIP).
 * Позволяет подменять реализацию и тестировать в изоляции.
 */
interface WhoisClientInterface
{
    /**
     * Получить WHOIS-данные для домена.
     */
    public function query(string $domain): ?string;
}
