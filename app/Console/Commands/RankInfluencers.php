<?php

namespace App\Console\Commands;

use App\Discovery\TrendingRanker;
use App\Models\Influencer;
use Illuminate\Console\Command;

class RankInfluencers extends Command
{
    protected $signature = 'influencers:rank';

    protected $description = 'Recalculate trending scores and the cached Followly follower counts';

    public function handle(TrendingRanker $ranker): int
    {
        Influencer::recountFollowers();

        $this->info("{$ranker->rank()} influencers are trending.");

        return self::SUCCESS;
    }
}
