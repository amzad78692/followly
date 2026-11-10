<?php

use App\Events\PostsSynced;
use App\Feed\SmartFeed;
use App\Models\Collection;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->influencer = Influencer::factory()->create(['name' => 'Story Teller']);
    $this->youtube = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => 'youtube']);
    $this->x = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => 'x']);
    $this->instagram = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => 'instagram']);
});

function storyPost(InfluencerAccount $account, array $attributes = []): Post
{
    return Post::factory()->for($account, 'account')->create($attributes + ['permalink' => null, 'metrics' => null]);
}

function dispatchSynced(InfluencerAccount $account, Post ...$posts): void
{
    PostsSynced::dispatch($account, new EloquentCollection($posts));
}

it('groups the same story told on two platforms by similar captions', function () {
    $video = storyPost($this->youtube, ['title' => 'I spent 7 days alone in the Sahara desert', 'body' => null, 'published_at' => now()->subHours(3)]);
    $tweet = storyPost($this->x, ['title' => null, 'body' => 'I spent 7 days alone in the Sahara desert!! 🔥 #travel', 'published_at' => now()->subHours(2)]);

    dispatchSynced($this->x, $tweet);

    expect($video->fresh()->group_id)->toBe($video->id)
        ->and($tweet->fresh()->group_id)->toBe($video->id);
});

it('groups posts that share a link, adding later versions to the same group', function () {
    $video = storyPost($this->youtube, ['title' => 'Desert vlog', 'body' => null, 'permalink' => 'https://www.youtube.com/watch?v=abc123', 'published_at' => now()->subHours(5)]);
    $tweet = storyPost($this->x, ['title' => null, 'body' => 'New one is up, go watch https://youtube.com/watch?v=abc123', 'published_at' => now()->subHours(4)]);
    $reel = storyPost($this->instagram, ['title' => null, 'body' => 'Link in bio: youtube.com/watch?v=abc123 https://youtube.com/watch?v=abc123/', 'published_at' => now()->subHours(3)]);

    dispatchSynced($this->x, $tweet);
    dispatchSynced($this->instagram, $reel);

    expect([$video, $tweet, $reel])->each(fn ($post) => $post->fresh()->group_id->toBe($video->id));
});

it('keeps different stories apart', function (array $other) {
    $video = storyPost($this->youtube, ['title' => 'I spent 7 days alone in the Sahara desert', 'body' => null, 'published_at' => now()->subHours(30)]);
    $tweet = storyPost($this->x, $other + ['title' => null, 'body' => 'I spent 7 days alone in the Sahara desert', 'published_at' => now()->subHours(29)]);

    dispatchSynced($this->x, $tweet);

    expect($video->fresh()->group_id)->toBeNull()
        ->and($tweet->fresh()->group_id)->toBeNull();
})->with([
    'different caption' => [['body' => 'Cooking the best pasta of my life tonight']],
    'more than 24 hours apart' => [['published_at' => now()->subHours(2)]],
    'too short to compare' => [['body' => 'New video!']],
]);

it('does not group posts from different influencers', function () {
    $video = storyPost($this->youtube, ['title' => 'I spent 7 days alone in the Sahara desert', 'body' => null, 'published_at' => now()->subHour()]);
    $copycat = InfluencerAccount::factory()->create(['platform' => 'x']);
    $tweet = storyPost($copycat, ['title' => null, 'body' => 'I spent 7 days alone in the Sahara desert', 'published_at' => now()]);

    dispatchSynced($copycat, $tweet);

    expect($tweet->fresh()->group_id)->toBeNull();
});

it('backfills groups for existing posts', function () {
    $video = storyPost($this->youtube, ['title' => 'I spent 7 days alone in the Sahara desert', 'body' => null, 'published_at' => now()->subHours(3)]);
    $tweet = storyPost($this->x, ['title' => null, 'body' => 'I spent 7 days alone in the Sahara desert', 'published_at' => now()->subHours(2)]);

    $this->artisan('posts:smartlink')->expectsOutput('2 posts grouped.')->assertSuccessful();

    expect($tweet->fresh()->group_id)->toBe($video->id)->and($video->fresh()->group_id)->toBe($video->id);
});

it('ranks newer first, boosts the watchlist and high engagement, and shows a group once', function () {
    $user = User::factory()->create();
    $watched = Influencer::factory()->create();
    $watchedAccount = InfluencerAccount::factory()->for($watched)->create(['platform' => 'youtube']);
    $user->follow($this->influencer);
    $user->follow($watched)->update(['watchlisted_at' => now()]);

    $newest = storyPost($this->x, ['published_at' => now()->subHour()]);
    // 10 hours old, but the 12 hour watchlist boost puts it first; the 6 hour engagement boost puts $popular second.
    $watchlisted = storyPost($watchedAccount, ['published_at' => now()->subHours(10)]);
    // Usual engagement on this account is low; this one is well above it.
    storyPost($this->instagram, ['published_at' => now()->subDays(5), 'metrics' => ['likes' => 10, 'comments' => 0]]);
    storyPost($this->instagram, ['published_at' => now()->subDays(6), 'metrics' => ['likes' => 10, 'comments' => 0]]);
    $popular = storyPost($this->instagram, ['published_at' => now()->subHours(5), 'metrics' => ['likes' => 500, 'comments' => 40]]);
    $plain = storyPost($this->youtube, ['published_at' => now()->subHours(3)]);
    $grouped = storyPost($this->youtube, ['published_at' => now()->subHours(20), 'group_id' => null]);
    $grouped->update(['group_id' => $grouped->id]);
    storyPost($this->x, ['published_at' => now()->subHours(19), 'group_id' => $grouped->id]);

    $ids = app(SmartFeed::class)->query($user)->pluck('id')->all();

    expect(array_slice($ids, 0, 5))->toBe([$watchlisted->id, $popular->id, $newest->id, $plain->id, $grouped->id])
        ->and($ids)->toHaveCount(7);
});

it('filters by watchlist, collection and platform', function () {
    $user = User::factory()->create();
    $watched = Influencer::factory()->create();
    $watchedAccount = InfluencerAccount::factory()->for($watched)->create(['platform' => 'youtube']);
    $user->follow($this->influencer);
    $user->follow($watched)->update(['watchlisted_at' => now()]);

    $tweet = storyPost($this->x);
    $video = storyPost($this->youtube);
    $watchedVideo = storyPost($watchedAccount);

    $collection = Collection::create(['user_id' => $user->id, 'name' => 'Travel']);
    $collection->influencers()->attach($this->influencer);

    $feed = app(SmartFeed::class);

    expect($feed->query($user, watchlist: true)->pluck('id')->all())->toBe([$watchedVideo->id])
        ->and($feed->query($user, collection: $collection)->pluck('id')->sort()->values()->all())->toBe(collect([$tweet->id, $video->id])->sort()->values()->all())
        ->and($feed->query($user, \App\Enums\Platform::X)->pluck('id')->all())->toBe([$tweet->id]);
});

it('renders the dashboard with a SmartLink card and its empty states', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/dashboard')->assertOk()->assertSee('follow anyone yet');

    $user->follow($this->influencer);
    Volt::test('dashboard')->assertSee('No posts yet');

    $video = storyPost($this->youtube, ['title' => 'Sahara week', 'permalink' => 'https://youtube.com/watch?v=1', 'published_at' => now()->subHours(2)]);
    $video->update(['group_id' => $video->id]);
    storyPost($this->x, ['body' => 'Sahara week thread', 'permalink' => 'https://x.com/a/status/1', 'group_id' => $video->id, 'published_at' => now()->subHour()]);

    Volt::test('dashboard')
        ->assertSee('Sahara week')
        ->assertDontSee('Sahara week thread')
        ->assertSee('Open on X')
        ->assertSee('Open on YouTube')
        ->set('platform', 'instagram')
        ->assertSee('No posts match this filter');

    $other = Collection::create(['user_id' => User::factory()->create()->id, 'name' => 'Not mine']);
    $this->get('/dashboard?collection='.$other->id)->assertOk()->assertDontSee('Not mine');
});
