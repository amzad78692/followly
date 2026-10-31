#!/usr/bin/env bash
# Front-end assets. public/build is committed, because the production server cannot run npm:
# run `npm run build` locally and commit public/build whenever CSS, JS or Blade classes change.

if $IS_PRODUCTION; then
    # A leftover hot file makes Laravel load assets from a Vite dev server instead of public/build.
    rm -f public/hot

    if [ ! -f "public/build/manifest.json" ]; then
        echo "❌ public/build is missing, so pages would load without CSS or JS."
        echo "   Run 'npm run build' locally, commit public/build and deploy again."
        exit 1
    fi

    echo "🎨 Using the committed front-end build"
elif command -v npm >/dev/null 2>&1; then
    echo "🎨 Building front-end assets..."
    npm ci --no-audit --no-fund
    npm run build
fi
