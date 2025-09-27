#!/bin/sh
set -e

envsubst "$(printf '${%s} ' $(env | sed 's/=.*//'))" < .env.example > .env

# Даем права на storage и bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Если нет vendor — ставим зависимости
composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan config:clear
php artisan cache:clear

# Запускаем миграции
php artisan migrate

exec "$@"