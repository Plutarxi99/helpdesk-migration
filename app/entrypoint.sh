#!/bin/sh
set -e

echo "Запуск Laravel entrypoint..."

envsubst "$(printf '${%s} ' $(env | sed 's/=.*//'))" < .env.example > .env

# Даем права на storage и bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Если нет vendor — ставим зависимости
composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan config:clear
php artisan cache:clear

# Генерируем ключ (если нет)
php artisan key:generate --force || true

# Запускаем миграции (можно закомментить)
php artisan migrate --force || true

echo "Laravel готов! 🚀"

exec "$@"
