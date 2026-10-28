<?php

namespace App\Discovery;

use App\Models\Influencer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calculates each influencer's trending score and stores it on influencers.trending_score,
 * so Explore can sort on a column instead of scoring on every page view.
 *
 * Score = new Followly followers in the window × follow_weight
 *       + platform follower growth in percent (capped) × growth_weight.
 */
class TrendingRanker
{
    /**
     * Recalculate every score. Returns how many influencers are trending (score other than 0).
     */
    public function rank(): int
    {
        $scores = $this->scores();

        DB::transaction(function () use ($scores) {
            // toBase(): leave updated_at alone, the score is not an edit to the influencer.
            Influencer::withTrashed()->where('trending_score', '!=', 0)->toBase()->update(['trending_score' => 0]);

            foreach ($scores as $influencerId => $score) {
                Influencer::withTrashed()->whereKey($influencerId)->toBase()->update(['trending_score' => $score]);
            }
        });

        return $scores->count();
    }

    /**
     * @return Collection<int, float> score per influencer id, zero scores left out
     */
    public function scores(): Collection
    {
        $config = config('discovery.trending');
        $follows = $this->newFollows($config['days']);
        $growth = $this->growth($config['days']);

        return $follows->keys()->merge($growth->keys())->unique()
            ->mapWithKeys(function (int $id) use ($follows, $growth, $config) {
                $percent = max(-$config['growth_cap'], min($config['growth_cap'], $growth[$id] ?? 0));

                return [$id => round(($follows[$id] ?? 0) * $config['follow_weight'] + $percent * $config['growth_weight'], 2)];
            })
            ->reject(fn (float $score) => $score == 0);
    }

    /**
     * @return Collection<int, int> follows created in the last $days days per influencer id
     */
    protected function newFollows(int $days): Collection
    {
        return DB::table('follows')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('influencer_id')
            ->selectRaw('influencer_id, count(*) as total')
            ->pluck('total', 'influencer_id')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * Follower growth in percent per influencer id, across all their accounts. Each account is
     * compared between its first and last snapshot in the window, so accounts with only one
     * snapshot in it (new or not synced lately) are left out.
     *
     * @return Collection<int, float>
     */
    protected function growth(int $days): Collection
    {
        $bounds = DB::table('account_snapshots')
            ->whereDate('captured_on', '>=', today()->subDays($days))
            ->groupBy('influencer_account_id')
            ->havingRaw('min(captured_on) < max(captured_on)')
            ->selectRaw('influencer_account_id, min(captured_on) as first_on, max(captured_on) as last_on');

        return DB::table('influencer_accounts as accounts')
            ->joinSub($bounds, 'bounds', 'bounds.influencer_account_id', '=', 'accounts.id')
            ->join('account_snapshots as first', fn ($join) => $join
                ->on('first.influencer_account_id', '=', 'accounts.id')
                ->on('first.captured_on', '=', 'bounds.first_on'))
            ->join('account_snapshots as last', fn ($join) => $join
                ->on('last.influencer_account_id', '=', 'accounts.id')
                ->on('last.captured_on', '=', 'bounds.last_on'))
            ->groupBy('accounts.influencer_id')
            ->selectRaw('accounts.influencer_id, sum(first.followers) as start_followers, sum(last.followers) as end_followers')
            ->get()
            ->filter(fn ($row) => $row->start_followers > 0)
            ->mapWithKeys(fn ($row) => [$row->influencer_id => ($row->end_followers - $row->start_followers) / $row->start_followers * 100]);
    }
}
