#!/usr/bin/env bash
# Setup PHP, Composer commands and detect Herd

PHP_CMD="php"
COMPOSER_CMD="composer"
IS_HERD=false
IS_CPANEL=false
PHP_VERSION=""

# Function to detect PHP version from composer.json
detect_php_version() {
    if [ -f "composer.json" ]; then
        # Extract PHP version requirement from composer.json
        PHP_REQUIREMENT=$(grep -o '"php":[[:space:]]*"[^"]*"' composer.json | cut -d'"' -f4)
        if [ -n "$PHP_REQUIREMENT" ]; then
            # Extract major.minor version (e.g., "^8.2" -> "8.2", ">=8.1" -> "8.1")
            PHP_VERSION=$(echo "$PHP_REQUIREMENT" | grep -o '[0-9]\+\.[0-9]\+' | head -1)
            echo "📦 Detected PHP version requirement: $PHP_REQUIREMENT (using $PHP_VERSION)"
        fi
    fi
}

# Function to detect cPanel environment
detect_cpanel() {
    # Check for cPanel indicators
    if [ -d "/usr/local/cpanel" ] || [ -f "/etc/cpanel-release" ] || command -v /usr/local/cpanel/cpanel >/dev/null 2>&1; then
        IS_CPANEL=true
        echo "🖥️  Detected cPanel environment"
        
        # Find composer path
        COMPOSER_PATH=$(which composer 2>/dev/null)
        if [ -z "$COMPOSER_PATH" ]; then
            # Check common cPanel composer locations
            for path in "/usr/local/bin/composer" "/opt/cpanel/composer/bin/composer" "/usr/bin/composer"; do
                if [ -f "$path" ]; then
                    COMPOSER_PATH="$path"
                    break
                fi
            done
        fi
        
        # Set PHP command based on detected version
        if [ -n "$PHP_VERSION" ]; then
            PHP_MAJOR_MINOR=$(echo "$PHP_VERSION" | tr -d '.')
            CPANEL_PHP_CMD="ea-php${PHP_MAJOR_MINOR}"
            
            # Check if the specific ea-php version exists
            if command -v "$CPANEL_PHP_CMD" >/dev/null 2>&1; then
                PHP_CMD="$CPANEL_PHP_CMD"
                if [ -n "$COMPOSER_PATH" ]; then
                    COMPOSER_CMD="$CPANEL_PHP_CMD $COMPOSER_PATH"
                    echo "✅ Using cPanel PHP: $CPANEL_PHP_CMD"
                    echo "✅ Using cPanel Composer: $CPANEL_PHP_CMD $COMPOSER_PATH"
                else
                    COMPOSER_CMD="$CPANEL_PHP_CMD composer"
                    echo "✅ Using cPanel PHP: $CPANEL_PHP_CMD"
                    echo "⚠️  Composer path not found, using: $CPANEL_PHP_CMD composer"
                fi
            else
                echo "⚠️  Warning: $CPANEL_PHP_CMD not found, falling back to system PHP"
            fi
        else
            # Default to ea-php83 if no version detected
            if command -v "ea-php83" >/dev/null 2>&1; then
                PHP_CMD="ea-php83"
                if [ -n "$COMPOSER_PATH" ]; then
                    COMPOSER_CMD="ea-php83 $COMPOSER_PATH"
                    echo "✅ Using default cPanel PHP: ea-php83"
                    echo "✅ Using default cPanel Composer: ea-php83 $COMPOSER_PATH"
                else
                    COMPOSER_CMD="ea-php83 composer"
                    echo "✅ Using default cPanel PHP: ea-php83"
                    echo "⚠️  Composer path not found, using: ea-php83 composer"
                fi
            fi
        fi
    fi
}

# Detect PHP version and environment
detect_php_version
detect_cpanel

if $IS_MAC && [ -d "/Applications/Herd.app" ]; then
    if command -v herd >/dev/null 2>&1; then
        IS_HERD=true
        PHP_CMD="herd php"
        COMPOSER_CMD="herd composer"
    else
        echo "⚠️ Warning: Herd app found but 'herd' CLI not in PATH."
    fi
fi

if $IS_WINDOWS; then
    USER_NAME="${USER:-$(whoami)}"
    HERD_PATH="/c/Users/$USER_NAME/.config/herd/bin/herd.bat"
    if [ -f "$HERD_PATH" ]; then
        IS_HERD=true
        COMPOSER_CMD=( "$HERD_PATH" composer )
        PHP_CMD=( "$HERD_PATH" php )
    else
        echo "⚠️ Warning: Herd CLI not found at $HERD_PATH"
    fi
fi

# Unified command runners
run_php() {
    if [ "$IS_WINDOWS" = true ] && [ "$IS_HERD" = true ]; then
        "${PHP_CMD[@]}" "$@"
    else
        $PHP_CMD "$@"
    fi
}

run_composer() {
    if [ "$IS_WINDOWS" = true ] && [ "$IS_HERD" = true ]; then
        "${COMPOSER_CMD[@]}" "$@"
    else
        $COMPOSER_CMD "$@"
    fi
}

export PHP_CMD COMPOSER_CMD IS_HERD IS_CPANEL PHP_VERSION run_php run_composer
