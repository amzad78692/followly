<?php

namespace App\Events;

use App\Models\InfluencerAccount;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A sync stored posts that were not in the database before. NotifyFollowersOfNewPosts tells followers.
 */
class PostsSynced
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, Post>  $posts
     */
    public function __construct(public InfluencerAccount $account, public Collection $posts) {}
}
