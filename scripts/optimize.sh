#!/usr/bin/env bash
# Run final optimization on production only
echo "🧹 Clearing caches..."
run_php artisan optimize:clear
run_php artisan filament:optimize-clear

if $IS_PRODUCTION; then
    echo "🚀 Optimizing application..."

    run_php artisan optimize
    run_php artisan filament:optimize
fi
