<?php

namespace App\Jobs;

use App\Models\User;
use App\Watch\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes to a user's devices, away from the sync listener so a creator with many followers
 * doesn't hold it up. Null title and body make it a silent refresh for watch complications.
 */
class SendPush implements ShouldQueue
{
    use Queueable;

    // A retry would alert devices that already got it, and the next refresh catches up anyway.
    public int $tries = 1;

    public function __construct(
        public int $userId,
        public ?string $title,
        public ?string $body,
        public array $data = [],
    ) {}

    public function handle(PushSender $sender): void
    {
        $user = User::with('deviceTokens')->find($this->userId);

        if (! $user) {
            return;
        }

        $watches = $user->deviceTokens->filter(fn ($device) => $device->platform->isWatch());

        // A watch app alerts by itself, so a phone copy would only be a duplicate.
        $devices = $watches->isNotEmpty() || $this->title === null ? $watches : $user->deviceTokens;

        $data = [...$this->data, 'unread_count' => $user->unreadNotifications()->count()];

        foreach ($devices as $device) {
            $sender->send($device, $this->title, $this->body, $data);
        }
    }
}
