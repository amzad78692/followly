<?php

namespace App\Console\Commands;

use App\Enums\Platform;
use App\Sync\SyncDispatcher;
use Illuminate\Console\Command;

class SyncPlatforms extends Command
{
    protected $signature = 'platforms:sync
                            {type=posts : "posts" (accounts that are due) or "profiles" (daily YouTube refresh)}
                            {--platform=* : Only these platforms (youtube, instagram, tiktok, x)}';

    protected $description = 'Queue sync jobs for tracked creator accounts';

    public function handle(SyncDispatcher $dispatcher): int
    {
        $type = $this->argument('type');

        if (! in_array($type, ['posts', 'profiles'], true)) {
            $this->error('Type must be "posts" or "profiles".');

            return self::INVALID;
        }

        if ($type === 'profiles') {
            $this->info("Queued profile refresh for {$dispatcher->profiles()} YouTube accounts.");

            return self::SUCCESS;
        }

        $platforms = $this->option('platform') ? array_map(fn ($value) => Platform::tryFrom($value), $this->option('platform')) : null;

        if ($platforms && in_array(null, $platforms, true)) {
            $this->error('Unknown platform. Use: '.implode(', ', array_column(Platform::cases(), 'value')));

            return self::INVALID;
        }

        $this->table(['Platform', 'Accounts queued'], collect($dispatcher->posts($platforms))->map(fn ($count, $platform) => [$platform, $count])->values());

        return self::SUCCESS;
    }
}
