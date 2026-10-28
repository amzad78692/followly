<?php

namespace App\Sync;

use App\Enums\SyncStatus;
use App\Events\PostsSynced;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Platforms\Data\AccountData;
use App\Platforms\Data\PostData;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\PlatformManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores what the drivers fetch. Pass data in when it was already fetched in a batch;
 * otherwise the account's driver is called.
 */
class AccountSyncer
{
    public function __construct(protected PlatformManager $platforms) {}

    /**
     * Update followers, handle and the influencer's avatar, and record today's snapshot.
     *
     * @throws PlatformException
     */
    public function syncProfile(InfluencerAccount $account, ?AccountData $data = null): void
    {
        $data ??= $this->platforms->driver($account->platform)->fetchProfile($account);

        $account->applyProfile($data);
        $account->snapshot();

        $this->refreshAvatar($account, $data);
    }

    /**
     * Store posts newer than the account's latest stored post.
     *
     * @param  Collection<int, PostData>|null  $posts
     * @return EloquentCollection<int, Post> the posts that were new
     *
     * @throws PlatformException
     */
    public function syncPosts(InfluencerAccount $account, ?Collection $posts = null): EloquentCollection
    {
        $latest = $account->posts()->max('published_at');
        $since = $latest ? CarbonImmutable::parse($latest) : null;

        $posts = ($posts ?? $this->platforms->driver($account->platform)->fetchRecentPosts($account, $since))
            ->filter(fn (PostData $post) => ! $since || $post->publishedAt->gt($since));

        $new = DB::transaction(function () use ($account, $posts) {
            $new = new EloquentCollection;

            foreach ($posts as $data) {
                $post = Post::firstOrNew(['platform' => $account->platform, 'external_id' => $data->externalId]);
                $post->fill([...$data->toPostAttributes(), 'influencer_account_id' => $account->id, 'influencer_id' => $account->influencer_id]);

                if (! $post->exists) {
                    $new->push($post);
                }

                $post->save();
            }

            $account->forceFill(['last_synced_at' => now(), 'sync_status' => SyncStatus::Synced, 'sync_error' => null])->save();

            return $new;
        });

        if ($new->isNotEmpty()) {
            PostsSynced::dispatch($account, $new);
        }

        return $new;
    }

    /**
     * Keep the influencer's avatar from their biggest account (platform image URLs expire).
     */
    protected function refreshAvatar(InfluencerAccount $account, AccountData $data): void
    {
        $influencer = $account->influencer;

        if (! $data->avatarUrl || ! $influencer) {
            return;
        }

        $biggest = $influencer->accounts()->reorder()->orderByDesc('followers')->value('id');

        if (! $influencer->avatar_url || $biggest === $account->id) {
            $influencer->forceFill(['avatar_url' => $data->avatarUrl])->saveQuietly();
        }
    }
}
