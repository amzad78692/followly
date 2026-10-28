<?php

namespace App\Support;

use App\Models\Influencer;
use App\Platforms\Data\AccountData;
use Illuminate\Support\Collection;

/**
 * What the Add page found: an influencer already in the database, accounts that are not
 * stored yet (keyed by platform), or both when a new account belongs to a known creator.
 */
final readonly class LookupResult
{
    /**
     * @param  Collection<string, AccountData>  $candidates
     */
    public function __construct(
        public ?Influencer $influencer = null,
        public Collection $candidates = new Collection,
    ) {}

    public function isEmpty(): bool
    {
        return ! $this->influencer && $this->candidates->isEmpty();
    }
}
