#!/usr/bin/env bash
set -e

if [ ! -f init.lock ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader

    php artisan storage:link --relative
    php artisan key:generate

    php artisan octane:install --server=frankenphp --no-interaction

    php artisan migrate --force --no-interaction --seed

    bun install --frozen-lockfile
    bun run build

    touch init.lock
fi

composer dumpautoload --no-interaction --optimize

php artisan event:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan optimize --no-interaction

sudo php artisan octane:frankenphp --caddyfile=.docker/Caddyfile
