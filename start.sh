#!/usr/bin/env bash
set -e

if [ ! -f init.lock ]; then
    composer install --no-interaction

    php artisan storage:link
    php artisan key:generate

    php artisan octane:install --server=frankenphp
    php artisan filament:install --panels --no-interaction

    php artisan migrate:fresh --seed

    bun install --frozen-lockfile
    bun run build

    touch init.lock
fi

composer dumpautoload

php artisan event:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan optimize
php artisan filament:optimize

php artisan octane:frankenphp --caddyfile=./Caddyfile
