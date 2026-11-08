<?php

namespace App\Console\Commands;

use App\Jobs\SendPush;
use App\Models\User;
use App\Watch\DailySummary;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendDailySummaries extends Command
{
    protected $signature = 'notifications:daily-summary';

    protected $description = "Push today's new post summary to users whose local evening has arrived";

    public function handle(): int
    {
        $sent = 0;

        User::where('push_daily_summary', true)
            ->whereHas('deviceTokens', fn ($q) => $q->whereNotNull('timezone'))
            ->with('deviceTokens')
            ->chunkById(200, function ($users) use (&$sent) {
                foreach ($users as $user) {
                    $timezone = $user->deviceTokens->whereNotNull('timezone')->sortByDesc('last_seen_at')->first()->timezone;

                    if (now($timezone)->hour !== config('notifications.daily_summary_hour')) {
                        continue;
                    }

                    $summary = DailySummary::for($user, $timezone);

                    if ($summary['posts'] > 0) {
                        SendPush::dispatch($user->id, "Today's update", sprintf(
                            '%d new %s from %d %s',
                            $summary['posts'],
                            Str::plural('post', $summary['posts']),
                            $summary['creators'],
                            Str::plural('creator', $summary['creators']),
                        ), ['type' => 'daily-summary']);

                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} daily summaries.");

        return self::SUCCESS;
    }
}
