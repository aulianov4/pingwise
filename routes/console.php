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

Schedule::command('pingwise:servers:check-silence')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Очистка старых данных ежедневно в 3:00
Schedule::command('pingwise:cleanup')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('pingwise:telegram:poll --timeout=25')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Саммари уведомлений: время задаётся на канале (summary_time)
Schedule::command('pingwise:notifications:summary --due')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
