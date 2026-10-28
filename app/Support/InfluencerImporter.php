<?php

namespace App\Support;

use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Platforms\Data\AccountData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores accounts found by the platform drivers, creating the influencer when it is new.
 */
class InfluencerImporter
{
    /**
     * @param  Collection<array-key, AccountData>  $accounts  the first one names a new influencer
     * @param  Model|null  $creator  the User or Admin adding them
     */
    public function import(Collection $accounts, ?Influencer $influencer = null, ?Model $creator = null): Influencer
    {
        return DB::transaction(function () use ($accounts, $influencer, $creator) {
            $influencer ??= $this->createInfluencer($accounts, $creator);
            $platforms = $influencer->accounts()->pluck('platform')->map->value;

            foreach ($accounts as $data) {
                // One account per platform per influencer.
                if ($platforms->contains($data->platform->value)) {
                    continue;
                }

                $account = InfluencerAccount::firstOrCreate(
                    ['platform' => $data->platform, 'external_id' => $data->externalId],
                    [...$data->toAccountAttributes(), 'influencer_id' => $influencer->id],
                );

                // Skip accounts another creator already owns.
                if ($account->influencer_id === $influencer->id) {
                    $platforms->push($data->platform->value);
                }
            }

            $influencer->refreshCounts();

            return $influencer->fresh('accounts');
        });
    }

    protected function createInfluencer(Collection $accounts, ?Model $creator): Influencer
    {
        $primary = $accounts->first();

        $influencer = new Influencer([
            'name' => $primary->name,
            'bio' => $accounts->pluck('bio')->filter()->first(),
            'avatar_url' => $accounts->pluck('avatarUrl')->filter()->first(),
            'is_verified' => $accounts->contains(fn (AccountData $account) => $account->isVerified),
        ]);

        $influencer->createdBy()->associate($creator)->save();

        return $influencer;
    }
}
