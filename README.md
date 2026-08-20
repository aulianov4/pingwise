# PingWise

Сервис мониторинга доступности сайтов, SSL-сертификатов и срока регистрации доменов. Админка — Filament, уведомления — Telegram.

Стек: PHP 8.3, Laravel 13, Filament 5, Livewire 4.

Админка: `/admin` (нужен логин).

## Возможности

- Сайты живут внутри **проектов**. Роли: суперадмин, админ проекта, наблюдатель.
- Три активных типа проверок: доступность, SSL, домен. Аудит sitemap в коде есть, но **выключен** (не регистрируется в `site_tests`).
- Каналы уведомлений на уровне проекта. К каналу привязывается Telegram-группа через `/connect`. На каждом тесте сайта выбираются каналы и типы сообщений: алерт, суточное / недельное / месячное саммари.

## Проверки

### Доступность (`availability`)

- Один HTTP GET, без повторных попыток, таймаут 10 с.
- Интервал по умолчанию: 5 минут.
- В `TestResult.value`: `status_code`, `response_time_ms` (пинг), `is_up` (эта проба).
- **Проба** `success` / `failed` — результат этого запроса (2xx–3xx = успех).
- **Инцидент** (бейдж в списке сайтов и алерт): сайт недоступен, если среди последних 5 проб (или сколько уже есть) неудач ≥ 3. Пока фейлов меньше трёх — алерта нет.
- После серии фейлов инцидент снимается, когда в окне снова меньше трёх неудач.

### SSL (`ssl`)

- Интервал по умолчанию: 24 часа.
- `success` — сертификат не самоподписанный и до истечения больше 30 дней.
- `warning` — истекает через 4–30 дней.
- `failed` — самоподписанный, не удалось установить TLS, или до истечения ≤ 3 дней.

### Домен (`domain`)

- Интервал по умолчанию: 24 часа.
- WHOIS: домен должен быть старше 20 дней. Для надёжности на сервере лучше установить утилиту `whois`.

### Sitemap (`sitemap`)

Код (`SitemapAuditTest`, парсер, краулер) сохранён. В реестр тестов не входит, существующие `SiteTest` с типом sitemap выключены миграцией. В форме сайта блок скрыт.

## Уведомления Telegram

Канал создаётся в админке («Каналы уведомлений»). Бот `@pingwise_bot` добавляется в группу **обычным участником** (админ не обязателен). В группу отправляется:

```
/connect@pingwise_bot PW-XXXX
```

Код живёт 30 минут, новый выпускается кнопкой «Новый код» на карточке канала.

На тесте сайта: канал + флаги `alerts`, `daily_summary`, `weekly_summary`, `monthly_summary`. Первый прогон теста алерт не шлёт. Для доступности алерт только при смене **инцидента**, не при каждой неудачной пробе.

### Telegram из РФ

С сервера в РФ исходящий `api.telegram.org` и входящий webhook часто недоступны.

- Исходящий Bot API: `TELEGRAM_API_BASE_URL` (reverse-proxy на `api.telegram.org`) и/или `TELEGRAM_PROXY` (`socks5h://…`).
- Входящие команды: `pingwise:telegram:poll` (getUpdates через тот же прокси), раз в минуту. Webhook `POST /telegram/webhook/{secret}` оставлен, но из РФ обычно не доходит (`Connection timed out`).

После смены URL прокси:

```bash
php artisan pingwise:telegram:set-webhook
```

(имеет смысл, только если Telegram может достучаться до `APP_URL`; иначе достаточно poll.)

Целевые сайты должны **разрешать исходящий IP монитора** (файрвол/ISPmanager). Иначе будут timeout при живом сайте в браузере.

## Команды

В проде: `php artisan …`. Если окружение DDEV — `ddev artisan …`.

| Команда | Назначение |
|---------|------------|
| `pingwise:check` | Проверки: все запланированные или `--site=ID` / `--test=TYPE` |
| `pingwise:cleanup` | Удалить результаты старше года (`--days=365`) |
| `pingwise:init-tests` | Создать `SiteTest` для сайта или всех сайтов |
| `pingwise:telegram:poll` | Забрать апдейты бота (`--timeout=25`) |
| `pingwise:telegram:set-webhook` | Зарегистрировать webhook в Telegram |
| `pingwise:notifications:summary --period=daily\|weekly\|monthly` | Саммари в каналы |

```bash
php artisan pingwise:check
php artisan pingwise:check --site=1 --test=availability
php artisan pingwise:init-tests --site=1
php artisan pingwise:telegram:poll --timeout=0
```

## Расписание

Crontab:

```cron
* * * * * cd /var/www/pingwise.m-teams.ru && php artisan schedule:run >> /dev/null 2>&1
```

| Задача | Когда |
|--------|--------|
| `pingwise:check` | каждые 5 минут |
| `pingwise:telegram:poll --timeout=25` | каждую минуту |
| `pingwise:cleanup` | 03:00 |
| саммари daily | 09:00 |
| саммари weekly | понедельник 09:00 |
| саммари monthly | 1-е число 09:00 |

## Переменные окружения

Задаются в `.env`, читаются из `config/services.php`:

```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
# TELEGRAM_PROXY=socks5h://user:pass@127.0.0.1:1080
TELEGRAM_API_BASE_URL=https://work.example.com/telegram
APP_URL=https://pingwise.example
```

Секреты в репозиторий не класть.

## Админка

- `/admin` — Filament.
- Ресурсы: проекты, сайты, каналы уведомлений, результаты, пользователи (суперадмин).
- Список сайтов: колонки «Доступность» (инцидент) и «Пинг».
- Карточка сайта: график времени отклика, последние результаты тестов.

Доступ к данным — по членству в проекте (`accessibleProjectIds()`), суперадмин видит всё.

## Очередь

Алерты (`SendTestAlert`) ставятся в очередь. При `QUEUE_CONNECTION=database` нужен воркер:

```bash
php artisan queue:work
```

Планировщик (`schedule:run`) очередь не обрабатывает.

## Тесты

PHPUnit в `tests/` (не путать с доменными проверками в `app/Tests/`).

```bash
php artisan test --compact
php artisan test --compact --filter=AvailabilityIncidentEvaluator
```

Pint: `vendor/bin/pint --dirty`.

## Запуск

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

Локально достаточно `APP_URL`, `TELEGRAM_BOT_TOKEN` и при блокировке Telegram — `TELEGRAM_API_BASE_URL` или `TELEGRAM_PROXY`.
