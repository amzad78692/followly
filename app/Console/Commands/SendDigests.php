<?php

namespace App\Console\Commands;

use App\Enums\DigestFrequency;
use App\Models\User;
use App\Notifications\NewPostsDigest;
use Illuminate\Console\Command;

class SendDigests extends Command
{
    protected $signature = 'notifications:digest {frequency : daily or weekly}';

    protected $description = 'Email users who chose this digest their unread new post notifications';

    public function handle(): int
    {
        $frequency = DigestFrequency::tryFrom($this->argument('frequency'));

        if (! $frequency) {
            $this->error('Frequency must be "daily" or "weekly".');

            return self::INVALID;
        }

        $sent = 0;

        User::where('email_digest', $frequency)->whereNotNull('email')->chunkById(200, function ($users) use ($frequency, &$sent) {
            foreach ($users as $user) {
                $since = $user->digest_sent_at && $user->digest_sent_at->gt($frequency->since()) ? $user->digest_sent_at : $frequency->since();

                $query = $user->unreadNotifications()->where('type', 'new-post')->where('created_at', '>', $since);
                $total = $query->count();

                if ($total > 0) {
                    $user->notify(new NewPostsDigest($frequency, $query->limit(config('notifications.digest.max_items'))->get(), $total));
                    $sent++;
                }

                $user->forceFill(['digest_sent_at' => now()])->saveQuietly();
            }
        });

        $this->info("Sent {$sent} {$frequency->value} digests.");

        return self::SUCCESS;
    }
}
