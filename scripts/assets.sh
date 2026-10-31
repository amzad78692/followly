#!/usr/bin/env bash
# Build the CSS/JS bundle (public/build is not in git)

if $IS_PRODUCTION; then
    # A leftover hot file makes Laravel load assets from a Vite dev server instead of public/build.
    rm -f public/hot
fi

if command -v npm >/dev/null 2>&1; then
    echo "🎨 Building front-end assets..."
    npm ci --no-audit --no-fund
    npm run build
elif [ -f "public/build/manifest.json" ]; then
    echo "⚠️ npm not found; keeping the existing public/build, which may be out of date."
    echo "   Install Node.js (in cPanel: Setup Node.js App) or run 'npm run build' locally and upload public/build."
else
    echo "❌ npm not found and public/build is missing, so pages would load without CSS or JS."
    echo "   Install Node.js (in cPanel: Setup Node.js App) or run 'npm run build' locally and upload public/build."
    exit 1
fi
