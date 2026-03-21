<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Запуск проверок каждые 5 минут (минимальный интервал)
Schedule::command('pingwise:check')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Очистка старых данных ежедневно в 3:00
Schedule::command('pingwise:cleanup')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Синхронизация Telegram-групп каждые 5 минут
Schedule::command('pingwise:telegram:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Ежесуточное саммари в Telegram в 09:00
Schedule::command('pingwise:telegram:summary')
    ->dailyAt('09:00')
    ->withoutOverlapping();
