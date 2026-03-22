# TODO: PHPUnit тесты для PingWise

## Подготовка

### Фабрики
- [x] `database/factories/SiteFactory.php` — поля: `name`, `url` (https://), `user_id` (User::factory()), `is_active`
- [x] `database/factories/SiteTestFactory.php` — поля: `site_id` (Site::factory()), `test_type`, `is_enabled`, `settings`
- [x] `database/factories/TestResultFactory.php` — поля: `site_id` (Site::factory()), `test_type`, `status`, `value`, `message`, `checked_at`
- [x] `database/factories/TelegramChatFactory.php` — поля: `chat_id`, `title`, `type`

---

## Unit-тесты

### `tests/Unit/Services/TestServiceTest.php`
- [x] `test_registers_default_tests` — конструктор регистрирует 3 типа: availability, ssl, domain
- [x] `test_get_test_returns_instance_by_type` — `getTest('availability')` возвращает AvailabilityTest
- [x] `test_get_test_returns_null_for_unknown_type` — `getTest('nonexistent')` возвращает null
- [x] `test_should_run_test_returns_true_when_never_checked` — нет результатов → нужно проверять
- [x] `test_should_run_test_returns_false_when_interval_not_elapsed` — последний результат 1 минуту назад, интервал 5 минут → рано
- [x] `test_should_run_test_returns_false_when_test_disabled` — тест выключен → false
- [x] `test_initialize_tests_for_site_creates_all_test_types` — создаёт 3 записи SiteTest для сайта
- [x] `test_get_test_returns_ssl_instance` — дополнительно
- [x] `test_get_test_returns_domain_instance` — дополнительно
- [x] `test_should_run_test_returns_true_when_interval_elapsed` — дополнительно
- [x] `test_initialize_tests_for_site_does_not_duplicate` — дополнительно

### `tests/Unit/Tests/AvailabilityTestTest.php`
- [x] `test_successful_response_returns_success` — Http::fake 200 → status=success, value содержит status_code и response_time_ms
- [x] `test_server_error_returns_failed` — Http::fake 500 → status=failed
- [x] `test_connection_error_returns_failed` — Http::fake throws ConnectionException → status=failed, error=connection_error
- [x] `test_redirect_response_returns_success` — дополнительно
- [x] `test_client_error_returns_failed` — дополнительно
- [x] `test_metadata` — дополнительно

### `tests/Unit/Tests/DomainTestTest.php`
- [x] `test_extracts_registration_date` — WHOIS с "Creation Date: 2020-01-15" → корректная дата
- [x] `test_extracts_expiration_date` — WHOIS с "Registry Expiry Date: 2027-01-15" → корректная дата
- [x] `test_extracts_registrar` — WHOIS с "Registrar: Example Registrar" → корректный регистратор
- [x] `test_old_domain_returns_success` — домен зарегистрирован 100+ дней назад → status=success
- [x] `test_young_domain_returns_failed` — домен зарегистрирован 5 дней назад → status=failed
- [x] `test_invalid_url_returns_failed` — невалидный URL → "Не удалось извлечь домен"
- [x] `test_domain_expiring_soon_returns_warning` — дополнительно
- [x] `test_whois_unavailable_returns_failed` — дополнительно
- [x] `test_strips_www_prefix` — дополнительно
- [x] `test_metadata` — дополнительно

### `tests/Unit/Tests/SslTestTest.php`
- [x] `test_invalid_url_returns_failed` — сайт с URL без хоста → status=failed
- [x] `test_valid_certificate_returns_success` — дополнительно (через SslCheckerInterface мок)
- [x] `test_expiring_soon_returns_warning` — дополнительно
- [x] `test_expired_certificate_returns_failed` — дополнительно
- [x] `test_self_signed_certificate_returns_failed` — дополнительно
- [x] `test_connection_failed_returns_failed` — дополнительно
- [x] `test_metadata` — дополнительно

### `tests/Unit/Tests/SitemapAuditTestTest.php`
- [x] `test_metadata` — проверка type, name, defaultInterval
- [x] `test_successful_audit_with_no_issues` — sitemap есть, все URL 200, ничего не пропущено → success
- [x] `test_missing_sitemap_returns_failed` — sitemap не найден → failed
- [x] `test_dead_pages_from_head_check` — 404 в sitemap → failed, non_200_pages
- [x] `test_missing_from_sitemap_returns_warning` — страницы на сайте, но не в sitemap → warning
- [x] `test_redirecting_in_sitemap_via_status_code` — 301 в sitemap → warning, redirecting_in_sitemap
- [x] `test_canonical_issues_from_crawler` — canonical ≠ URL → warning, canonical_issues
- [x] `test_unreachable_page_via_head_check` — status_code=0 → failed, dead_pages
- [x] `test_redirect_via_redirect_target_detected` — redirect_target при 200 → redirecting_in_sitemap
- [x] `test_crawl_limited_flag_is_propagated` — crawl_limited=true пробрасывается в результат
- [x] `test_uses_custom_settings_from_site_test` — кастомные настройки (sitemap_url, max_crawl_pages и т.д.)
- [x] `test_server_error_pages_returns_failed` — 500 → failed, non_200_pages
- [x] `test_multiple_issues_combined` — несколько проблем одновременно → failed
- [x] `test_checked_urls_count_in_result` — проверка счётчиков checked_urls_count и sitemap_urls_count
- [x] `test_trailing_slash_url_is_not_false_positive_redirect` — URL с trailing slash из sitemap не считается ложным редиректом
- [x] `test_trivial_trailing_slash_redirect_is_ignored` — redirect_target совпадающий с URL после нормализации игнорируется
- [x] `test_get_interval_minutes_from_settings` — settings содержит interval_minutes=10 → возвращает 10
- [x] `test_get_interval_minutes_defaults_when_no_settings` — нет settings → 60 (безопасный fallback)
- [x] `test_get_interval_minutes_defaults_when_empty_settings` — дополнительно
- [x] `test_belongs_to_site` — дополнительно

### `tests/Unit/Models/TestResultTest.php`
- [x] `test_scope_of_type_filters_by_test_type` — ofType('ssl') возвращает только ssl-записи
- [x] `test_scope_of_status_filters_by_status` — ofStatus('failed') возвращает только failed
- [x] `test_scope_for_period_week` — forPeriod('week') не включает записи старше недели
- [x] `test_scope_for_period_month` — дополнительно
- [x] `test_scope_latest_for_site_test` — дополнительно
- [x] `test_belongs_to_site` — дополнительно

---

## Feature-тесты

### `tests/Feature/Commands/RunTestsCommandTest.php`
- [x] `test_check_command_exits_successfully` — `pingwise:check` → exit code 0
- [x] `test_check_command_with_invalid_site_returns_failure` — `--site=999` → exit code FAILURE, сообщение "не найден"
- [x] `test_check_command_with_invalid_test_returns_failure` — дополнительно
- [x] `test_check_command_with_site_and_test_runs_specific_test` — дополнительно
- [x] `test_check_command_with_site_only_runs_all_enabled_tests` — дополнительно
- [x] `test_check_command_test_without_site_returns_failure` — дополнительно

### `tests/Feature/Commands/CleanupCommandTest.php`
- [x] `test_cleanup_deletes_results_older_than_one_year` — результат 2 года назад удалён, свежий — остался
- [x] `test_cleanup_respects_custom_days_option` — дополнительно
- [x] `test_cleanup_with_no_old_results_deletes_nothing` — дополнительно

### `tests/Feature/Commands/InitTestsCommandTest.php`
- [x] `test_init_tests_creates_tests_for_site` — для сайта создаются 3 записи SiteTest
- [x] `test_init_tests_for_all_sites` — дополнительно
- [x] `test_init_tests_with_invalid_site_returns_failure` — дополнительно
- [x] `test_init_tests_does_not_duplicate_existing` — дополнительно

### `tests/Feature/Filament/SiteResourceTest.php`
- [x] `test_user_sees_only_own_sites` — user1 видит свои сайты, не видит сайты user2
- [x] `test_user_sees_only_own_test_results` — аналогично для TestResultResource
- [x] `test_unauthenticated_user_sees_no_sites` — дополнительно

---

## Итого: 117 тестов (изначально планировалось ~28)

### Выполненные улучшения кода:
- [x] Фабрики для всех моделей (Site, SiteTest, TestResult, TelegramChat)
- [x] HasFactory в TelegramChat
- [x] Исправлен N+1 запрос в SiteResource (last_test_status)
- [x] Исправлен RunTestsCommand: --site без --test запускает все тесты для сайта, --test без --site → ошибка
- [x] TestResultResource form() упрощена до view-only
- [x] Eager-load telegramChat в SendTelegramAlert
- [x] Конфигурируемый retention period в CleanupOldResultsCommand (--days)
- [x] Индекс на status в test_results
- [x] SslCheckerInterface — SSL-логика извлечена в абстракцию для тестируемости
- [x] SendTelegramAlert → ShouldQueue (асинхронная отправка)
- [x] Default для notification_settings в миграции
- [x] Health check endpoint /health
