#!/usr/bin/env bash
# Detect OS and environment flags

OS_TYPE="$(uname -s)"
IS_MAC=false
IS_LINUX=false
IS_WINDOWS=false
IS_PRODUCTION=false
IS_LOCAL=true

case "$OS_TYPE" in
    Darwin*) IS_MAC=true ;;
    Linux*) IS_LINUX=true ;;
    MINGW*|MSYS*|CYGWIN*) IS_WINDOWS=true ;;
esac

if [ -f ".env" ]; then
    if grep -q "^APP_ENV=production" .env; then
        IS_PRODUCTION=true
        IS_LOCAL=false
    else
        IS_PRODUCTION=false
        IS_LOCAL=true
    fi
else
    IS_PRODUCTION=false
    IS_LOCAL=true
fi

# --- Detect Environment ---
if [ -f ".env" ]; then
    ENVIRONMENT=$(grep "^APP_ENV=" .env | cut -d '=' -f2)
else
    ENVIRONMENT="local"
fi

echo "🖥️  Detected OS: $OS_TYPE"
echo "🌐 Environment: $ENVIRONMENT"
