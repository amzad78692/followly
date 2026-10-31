#!/usr/bin/env bash
# Run final optimization on production only
echo "🧹 Clearing caches..."
# Keep the app cache: it holds the YouTube quota count, Apify lookup results and rate limits.
run_php artisan optimize:clear --except=cache
run_php artisan filament:optimize-clear

if $IS_PRODUCTION; then
    echo "🚀 Optimizing application..."

    run_php artisan optimize
    run_php artisan filament:optimize
fi
