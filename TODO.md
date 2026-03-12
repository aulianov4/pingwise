# TODO: PHPUnit тесты для PingWise

## Подготовка

### Фабрики
- [ ] `database/factories/SiteFactory.php` — поля: `name`, `url` (https://), `user_id` (User::factory()), `is_active`
- [ ] `database/factories/SiteTestFactory.php` — поля: `site_id` (Site::factory()), `test_type`, `is_enabled`, `settings`
- [ ] `database/factories/TestResultFactory.php` — поля: `site_id` (Site::factory()), `test_type`, `status`, `value`, `message`, `checked_at`

---

## Unit-тесты

### `tests/Unit/Services/TestServiceTest.php`
- [ ] `test_registers_default_tests` — конструктор регистрирует 3 типа: availability, ssl, domain
- [ ] `test_get_test_returns_instance_by_type` — `getTest('availability')` возвращает AvailabilityTest
- [ ] `test_get_test_returns_null_for_unknown_type` — `getTest('nonexistent')` возвращает null
- [ ] `test_should_run_test_returns_true_when_never_checked` — нет результатов → нужно проверять
- [ ] `test_should_run_test_returns_false_when_interval_not_elapsed` — последний результат 1 минуту назад, интервал 5 минут → рано
- [ ] `test_should_run_test_returns_false_when_test_disabled` — тест выключен → false
- [ ] `test_initialize_tests_for_site_creates_all_test_types` — создаёт 3 записи SiteTest для сайта

### `tests/Unit/Tests/AvailabilityTestTest.php`
- [ ] `test_successful_response_returns_success` — Http::fake 200 → status=success, value содержит status_code и response_time_ms
- [ ] `test_server_error_returns_failed` — Http::fake 500 → status=failed
- [ ] `test_connection_error_returns_failed` — Http::fake throws ConnectionException → status=failed, error=connection_error

### `tests/Unit/Tests/DomainTestTest.php`
Тестируем парсинг WHOIS через partial mock (переопределяем `getWhoisData()`):
- [ ] `test_extracts_registration_date` — WHOIS с "Creation Date: 2020-01-15" → корректная дата
- [ ] `test_extracts_expiration_date` — WHOIS с "Registry Expiry Date: 2027-01-15" → корректная дата
- [ ] `test_extracts_registrar` — WHOIS с "Registrar: Example Registrar" → корректный регистратор
- [ ] `test_old_domain_returns_success` — домен зарегистрирован 100+ дней назад → status=success
- [ ] `test_young_domain_returns_failed` — домен зареги��трирован 5 дней назад → status=failed
- [ ] `test_invalid_url_returns_failed` — невалидный URL → "Не удалось извлечь домен"

### `tests/Unit/Tests/SslTestTest.php`
- [ ] `test_invalid_url_returns_failed` — сайт с URL без хоста → status=failed

### `tests/Unit/Models/SiteTestTest.php`
- [ ] `test_get_interval_minutes_from_settings` — settings содержит interval_minutes=10 → возвращает 10
- [ ] `test_get_interval_minutes_defaults_for_availability` — нет settings → 5 для availability
- [ ] `test_get_interval_minutes_defaults_for_ssl` — нет settings → 1440 для ssl

### `tests/Unit/Models/TestResultTest.php`
- [ ] `test_scope_of_type_filters_by_test_type` — ofType('ssl') возвращает только ssl-записи
- [ ] `test_scope_of_status_filters_by_status` — ofStatus('failed') возвращает только failed
- [ ] `test_scope_for_period_week` — forPeriod('week') не включает записи старше недели

---

## Feature-тесты

### `tests/Feature/Commands/RunTestsCommandTest.php`
- [ ] `test_check_command_exits_successfully` — `pingwise:check` → exit code 0
- [ ] `test_check_command_with_invalid_site_returns_failure` — `--site=999` → exit code FAILURE, сообщение "не найден"

### `tests/Feature/Commands/CleanupCommandTest.php`
- [ ] `test_cleanup_deletes_results_older_than_one_year` — результат 2 года назад удалён, свежий — остался

### `tests/Feature/Commands/InitTestsCommandTest.php`
- [ ] `test_init_tests_creates_tests_for_site` — для сайта создаются 3 записи SiteTest

### `tests/Feature/Filament/SiteResourceTest.php`
- [ ] `test_user_sees_only_own_sites` — user1 видит свои сайты, не видит сайты user2
- [ ] `test_user_sees_only_own_test_results` — аналогично для TestResultResource

---

## Итого: ~28 тестов

### Порядок реализации:
1. Фабрики (3 файла) — без них тесты не написать
2. Unit/Models — простые, без моков
3. Unit/Services/TestServiceTest — ядро бизнес-логики
4. Unit/Tests/* — моки Http::fake и WHOIS
5. Feature/Commands — интеграционные
6. Feature/Filament — Filament-специфика

