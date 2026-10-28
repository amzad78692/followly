<?php

namespace App\Events;

use App\Models\InfluencerAccount;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A sync stored posts that were not in the database before. Phase 8 notifies followers from here.
 */
class PostsSynced
{
    use Dispatchable;

    /**
     * @param  Collection<int, Post>  $posts
     */
    public function __construct(public InfluencerAccount $account, public Collection $posts) {}
}
