#!/usr/bin/env bash
# Run migrations and fresh install tasks

echo "🗄️ Running migrations..."
run_php artisan migrate --force

if $FRESH_INSTALLATION; then
    echo "🆕 Running fresh install tasks..."

    run_php artisan key:generate
    run_php artisan storage:link
    run_php artisan shield:install
    run_php artisan shield:super-admin
    run_php -d memory_limit=512M -d max_execution_time=300 artisan db:seed --class=WorldSeeder
fi

run_php artisan shield:generate --all --panel=admin --no-interaction