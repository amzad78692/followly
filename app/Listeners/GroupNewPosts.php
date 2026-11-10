<?php

namespace App\Listeners;

use App\Events\PostsSynced;
use App\Feed\SmartLink;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * SmartLink: group each newly synced post with the same story on the influencer's other platforms.
 */
class GroupNewPosts implements ShouldQueue
{
    public function __construct(protected SmartLink $smartLink) {}

    public function handle(PostsSynced $event): void
    {
        $event->posts->sortBy('published_at')->map->fresh()->filter()->each(fn ($post) => $this->smartLink->group($post));
    }
}
