<?php

namespace App\Platforms\Contracts;

use App\Enums\Platform;
use App\Models\InfluencerAccount;
use App\Platforms\Data\AccountData;
use App\Platforms\Data\PostData;
use App\Platforms\Exceptions\PlatformException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface PlatformDriver
{
    public function platform(): Platform;

    public function matchesUrl(string $url): bool;

    /**
     * Find a profile from a link (profile, post or video) or a handle. Null when it does not exist.
     *
     * @throws PlatformException
     */
    public function resolve(string $urlOrHandle): ?AccountData;

    /**
     * @throws PlatformException
     */
    public function fetchProfile(InfluencerAccount $account): AccountData;

    /**
     * Newest first, only posts published after $since when given.
     *
     * @return Collection<int, PostData>
     *
     * @throws PlatformException
     */
    public function fetchRecentPosts(InfluencerAccount $account, ?CarbonInterface $since = null): Collection;
}
