<?php

namespace App\Services\Notifications;

/**
 * Реестр драйверов каналов уведомлений (SRP).
 * Драйверы инжектируются через tagged bindings (OCP, DIP).
 */
class ChannelDriverRegistry
{
    /**
     * @var array<string, NotificationChannelDriver>
     */
    protected array $drivers = [];

    /**
     * @param  iterable<NotificationChannelDriver>  $drivers
     */
    public function __construct(iterable $drivers = [])
    {
        foreach ($drivers as $driver) {
            $this->register($driver);
        }
    }

    /**
     * Зарегистрировать драйвер.
     */
    public function register(NotificationChannelDriver $driver): void
    {
        $this->drivers[$driver->type()] = $driver;
    }

    /**
     * Получить драйвер по типу канала.
     */
    public function get(string $type): ?NotificationChannelDriver
    {
        return $this->drivers[$type] ?? null;
    }
}
