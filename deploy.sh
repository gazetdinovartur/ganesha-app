#!/bin/sh
# Запуск на сервере из каталога ganesha-app:
#   ./deploy.sh
#
# Опционально: APP_ENV=prod (по умолчанию prod)

set -e
cd "$(dirname "$0")"

APP_ENV="${APP_ENV:-prod}"
echo "deploy: APP_ENV=$APP_ENV"

echo "update project"
git pull origin main

echo "deploy: composer install"
composer install --no-dev --optimize-autoloader --no-interaction

echo "deploy: migrations"
php bin/console doctrine:migrations:migrate --no-interaction --env="$APP_ENV"

echo "deploy: cache"
php bin/console cache:clear --no-warmup --env="$APP_ENV"
php bin/console cache:warmup --env="$APP_ENV"

echo "deploy: assets"
php bin/console assets:install public --no-interaction --env="$APP_ENV"

echo "deploy: ok"
