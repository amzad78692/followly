#!/usr/bin/env bash
# Background work. Cron runs the scheduler every minute; the scheduler (routes/console.php)
# starts the queue worker and queues the platform syncs.
# With Supervisor instead, run `artisan queue:work --queue=default,platforms --timeout=0`
# as a program and remove the queue:work entry from routes/console.php.

if $IS_PRODUCTION; then
    CRON_LINE="* * * * * cd $(pwd) && $PHP_CMD artisan schedule:run >> /dev/null 2>&1"

    echo "⏱️ Checking the scheduler cron..."
    if command -v crontab >/dev/null 2>&1; then
        if crontab -l 2>/dev/null | grep -Fq "artisan schedule:run"; then
            echo "✅ Scheduler cron is installed"
        else
            (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
            echo "✅ Installed scheduler cron: $CRON_LINE"
        fi
    else
        echo "⚠️ crontab not found. Add this cron entry by hand (in cPanel: Cron Jobs):"
        echo "   $CRON_LINE"
    fi

    # Workers finish their current job and exit; the next scheduler run starts one on the new code.
    echo "🔁 Restarting queue workers..."
    run_php artisan queue:restart
fi
