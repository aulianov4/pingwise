# PingWise

PingWise — сервис мониторинга доступности сайтов и SEO-показателей.

## Развёртывание в продакшн

1. Клонирование и настройка:
```bash
cd /var/www
sudo git clone <repository-url> pingwise
cd pingwise
sudo chown -R www-data:www-data .
```

2. Установка зависимостей:
```bash
composer install --optimize-autoloader --no-dev
npm ci
npm run build
```

3. Настройка окружения:
```bash
cp .env.example .env
php artisan key:generate
```

4. Настройка `.env` для продакшн:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pingwise
DB_USERNAME=pingwise_user
DB_PASSWORD=secure_password
```

5. Создание базы данных:
```bash
mysql -u root -p
```
```sql
CREATE DATABASE pingwise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pingwise_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON pingwise.* TO 'pingwise_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

6. Выполнение миграций:
```bash
php artisan migrate --force
```

7. Оптимизация приложения:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

8. Настройка прав доступа:
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```
