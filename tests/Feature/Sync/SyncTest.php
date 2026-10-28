<?php

use App\Enums\Platform;
use App\Enums\PostType;
use App\Enums\SyncStatus;
use App\Events\PostsSynced;
use App\Jobs\SyncAccountPosts;
use App\Jobs\SyncApifyBatch;
use App\Jobs\SyncYouTubeProfiles;
use App\Models\AccountSnapshot;
use App\Models\Admin;
use App\Models\ApifyRun;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ApifyBudgetReached;
use App\Platforms\Data\PostData;
use App\Platforms\Exceptions\BudgetExceededException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\RateLimitedException;
use App\Platforms\PlatformManager;
use App\Sync\AccountSyncer;
use App\Sync\SyncDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function postData(string $id, string $publishedAt, array $attributes = []): PostData
{
    return new PostData(...['externalId' => $id, 'type' => PostType::Video, 'publishedAt' => CarbonImmutable::parse($publishedAt), 'title' => "Post {$id}", ...$attributes]);
}

/**
 * An account on $platform that a user tracks.
 */
function trackedAccount(Platform $platform, string $handle, array $attributes = []): InfluencerAccount
{
    $account = InfluencerAccount::factory()->create(['platform' => $platform, 'handle' => "@{$handle}", 'external_id' => "{$platform->value}-{$handle}", ...$attributes]);

    User::factory()->create()->follow($account->influencer, [$account->id]);

    return $account->fresh();
}

it('stores new posts once and only fetches posts newer than the latest one', function () {
    Event::fake(PostsSynced::class);
    $drivers = fakePlatforms();
    $account = trackedAccount(Platform::YouTube, 'chef');
    $drivers['youtube']->posts['chef'] = [postData('a', '2026-10-20 10:00'), postData('b', '2026-10-25 10:00', ['metrics' => ['views' => 5]])];

    $new = app(AccountSyncer::class)->syncPosts($account);

    expect($new->pluck('external_id')->sort()->values()->all())->toBe(['a', 'b'])
        ->and(Post::count())->toBe(2)
        ->and(Post::firstWhere('external_id', 'b'))
        ->influencer_id->toBe($account->influencer_id)
        ->platform->toBe(Platform::YouTube)
        ->metrics->toBe(['views' => 5])
        ->and($account->fresh())->sync_status->toBe(SyncStatus::Synced)->last_synced_at->not->toBeNull();

    // Same posts plus one newer: only the newer one is stored.
    $drivers['youtube']->posts['chef'][] = postData('c', '2026-10-27 10:00');
    $new = app(AccountSyncer::class)->syncPosts($account);

    expect($new->pluck('external_id')->all())->toBe(['c'])->and(Post::count())->toBe(3);

    // Nothing new: no event.
    app(AccountSyncer::class)->syncPosts($account);

    Event::assertDispatchedTimes(PostsSynced::class, 2);
    Event::assertDispatched(PostsSynced::class, fn (PostsSynced $event) => $event->posts->pluck('external_id')->all() === ['c']);
});

it('updates the profile, the daily snapshot and the avatar from the biggest account', function () {
    $influencer = Influencer::factory()->create(['avatar_url' => 'https://img/old.jpg']);
    $small = InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::X, 'handle' => '@chef', 'followers' => 10]);
    $big = InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'handle' => '@chef', 'followers' => 100]);
    $syncer = app(AccountSyncer::class);

    $syncer->syncProfile($small, profile(Platform::X, 'chef', ['followers' => 20, 'avatarUrl' => 'https://img/x.jpg']));

    expect($influencer->fresh()->avatar_url)->toBe('https://img/old.jpg');

    $syncer->syncProfile($big, profile(Platform::YouTube, 'chef', ['followers' => 150, 'avatarUrl' => 'https://img/yt.jpg']));
    $syncer->syncProfile($big, profile(Platform::YouTube, 'chef', ['followers' => 160, 'avatarUrl' => 'https://img/yt.jpg']));

    expect($influencer->fresh())->avatar_url->toBe('https://img/yt.jpg')->combined_reach->toBe(180)
        ->and($big->snapshots()->sole()->followers)->toBe(160)
        ->and($small->fresh()->followers)->toBe(20);
});

it('queues only tracked accounts that are due, biggest first', function () {
    Queue::fake();
    fakePlatforms();
    config(['platforms.sync.max_per_run.youtube' => 2]);

    $big = trackedAccount(Platform::YouTube, 'big', ['followers' => 300]);
    $mid = trackedAccount(Platform::YouTube, 'mid', ['followers' => 200]);
    $small = trackedAccount(Platform::YouTube, 'small', ['followers' => 100]);
    trackedAccount(Platform::YouTube, 'fresh', ['followers' => 999, 'last_synced_at' => now()->subMinutes(5)]);
    trackedAccount(Platform::YouTube, 'broken', ['followers' => 999, 'sync_status' => SyncStatus::Failed]);
    InfluencerAccount::factory()->create(['platform' => Platform::YouTube, 'followers' => 999]);

    expect(app(SyncDispatcher::class)->posts([Platform::YouTube]))->toBe(['youtube' => 2]);

    Queue::assertPushed(SyncAccountPosts::class, 2);
    Queue::assertPushed(SyncAccountPosts::class, fn (SyncAccountPosts $job) => $job->account->is($big) && $job->queue === 'platforms');
    Queue::assertPushed(SyncAccountPosts::class, fn (SyncAccountPosts $job) => $job->account->is($mid));
    expect($big->fresh()->sync_status)->toBe(SyncStatus::Syncing)
        ->and($small->fresh()->sync_status)->toBe(SyncStatus::Pending);

    // Queued accounts are not queued again while their job waits.
    app(SyncDispatcher::class)->posts([Platform::YouTube]);
    Queue::assertPushed(SyncAccountPosts::class, fn (SyncAccountPosts $job) => $job->account->is($small));
    Queue::assertPushed(SyncAccountPosts::class, 3);
});

it('batches Apify accounts into one job per batch', function () {
    Queue::fake();
    config(['platforms.sync.apify_batch_size' => 2]);

    foreach (['a', 'b', 'c'] as $handle) {
        trackedAccount(Platform::Instagram, $handle);
    }

    app(SyncDispatcher::class)->posts([Platform::Instagram]);

    Queue::assertPushed(SyncApifyBatch::class, 2);
    Queue::assertPushed(SyncApifyBatch::class, fn (SyncApifyBatch $job) => $job->platform === Platform::Instagram && count($job->accountIds) === 2);
});

it('syncs a batch of Instagram accounts with one actor run', function () {
    config(['services.apify.token' => 'apify-test']);
    $one = trackedAccount(Platform::Instagram, 'one');
    $two = trackedAccount(Platform::Instagram, 'Two');
    $gone = trackedAccount(Platform::Instagram, 'gone');

    $item = fn (string $username, string $postId) => [
        'id' => "id-{$username}", 'username' => $username, 'fullName' => ucfirst($username), 'followersCount' => 500,
        'latestPosts' => [['id' => $postId, 'type' => 'Image', 'displayUrl' => "https://cdn/{$postId}.jpg", 'timestamp' => '2026-10-27T10:00:00.000Z']],
    ];

    Http::fake([
        'api.apify.com/v2/acts/*' => Http::response(['data' => ['id' => 'run-1', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'ds-1', 'usageTotalUsd' => 0.02]]),
        'api.apify.com/v2/datasets/ds-1/items*' => Http::response([$item('one', 'p1'), $item('two', 'p2')]),
    ]);

    SyncApifyBatch::dispatch(Platform::Instagram, [$one->id, $two->id, $gone->id]);

    Http::assertSent(fn (Request $request) => ($request['usernames'] ?? null) === ['one', 'two', 'gone']);
    Http::assertSentCount(2);

    expect($one->fresh())->followers->toBe(500)->sync_status->toBe(SyncStatus::Synced)
        ->and($two->posts()->sole()->external_id)->toBe('p2')
        ->and($two->snapshots()->count())->toBe(1)
        ->and($gone->fresh())->sync_status->toBe(SyncStatus::Failed)->sync_error->toContain('was not found');
});

it('pauses Apify syncs and emails admins once when the budget is reached', function () {
    Queue::fake();
    Notification::fake();
    config(['services.apify.monthly_budget_usd' => 1.0]);
    ApifyRun::create(['actor' => 'apify/instagram-profile-scraper', 'status' => 'SUCCEEDED', 'usage_usd' => 1.2]);
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password']);

    trackedAccount(Platform::YouTube, 'chef');
    trackedAccount(Platform::X, 'chef');

    expect(app(SyncDispatcher::class)->posts())->toMatchArray(['youtube' => 1, 'x' => 0]);
    app(SyncDispatcher::class)->posts();

    Queue::assertPushed(SyncAccountPosts::class, 1);
    Queue::assertNotPushed(SyncApifyBatch::class);
    Notification::assertSentToTimes($admin, ApifyBudgetReached::class, 1);
});

it('refuses Apify runs over budget without calling Apify', function () {
    Notification::fake();
    Http::fake();
    config(['services.apify.token' => 'apify-test', 'services.apify.monthly_budget_usd' => 0.5]);
    ApifyRun::create(['actor' => 'x', 'status' => 'SUCCEEDED', 'usage_usd' => 0.5]);

    expect(fn () => app(PlatformManager::class)->driver('tiktok')->resolve('@chef'))->toThrow(BudgetExceededException::class);

    Http::assertNothingSent();
});

it('saves errors and retries, and backs off on rate limits', function () {
    $drivers = fakePlatforms();
    $drivers['youtube']->fails = true;
    $account = trackedAccount(Platform::YouTube, 'chef');

    $job = (new SyncAccountPosts($account))->withFakeQueueInteractions();

    expect(fn () => $job->handle(app(AccountSyncer::class)))->toThrow(PlatformException::class);
    expect($account->fresh())->sync_status->toBe(SyncStatus::Failed)->sync_error->toBe('YouTube: service unavailable');

    // A rate limit releases the job instead of failing it.
    $limited = new class(app(PlatformManager::class)) extends AccountSyncer
    {
        public function syncPosts(InfluencerAccount $account, ?Collection $posts = null): Illuminate\Database\Eloquent\Collection
        {
            throw new RateLimitedException(Platform::YouTube, 120);
        }
    };

    $job = (new SyncAccountPosts($account))->withFakeQueueInteractions();
    $job->handle($limited);
    $job->assertReleased(120);
});

it('refreshes YouTube profiles 50 at a time', function () {
    Queue::fake();
    collect(range(1, 51))->each(fn ($i) => trackedAccount(Platform::YouTube, "chef{$i}"));
    trackedAccount(Platform::TikTok, 'chef');

    expect(app(SyncDispatcher::class)->profiles())->toBe(51);

    Queue::assertPushed(SyncYouTubeProfiles::class, 2);
    Queue::assertPushed(SyncYouTubeProfiles::class, fn (SyncYouTubeProfiles $job) => count($job->accountIds) === 50);
});

it('runs the YouTube profile job', function () {
    $drivers = fakePlatforms(profile(Platform::YouTube, 'chef', ['followers' => 4242]));
    $account = trackedAccount(Platform::YouTube, 'chef');
    $missing = trackedAccount(Platform::YouTube, 'gone');

    SyncYouTubeProfiles::dispatch([$account->id, $missing->id]);

    expect($account->fresh()->followers)->toBe(4242)
        ->and($account->snapshots()->count())->toBe(1)
        ->and($missing->fresh()->sync_status)->toBe(SyncStatus::Failed);
});

it('has a command that queues syncs', function () {
    Queue::fake();
    fakePlatforms();
    trackedAccount(Platform::YouTube, 'chef');

    $this->artisan('platforms:sync', ['--platform' => ['youtube']])
        ->expectsTable(['Platform', 'Accounts queued'], [['youtube', 1]])
        ->assertSuccessful();

    $this->artisan('platforms:sync profiles')->expectsOutputToContain('1 YouTube accounts')->assertSuccessful();
    $this->artisan('platforms:sync', ['--platform' => ['myspace']])->assertFailed();
    $this->artisan('platforms:sync nonsense')->assertFailed();
});

it('prunes old snapshots and Apify runs', function () {
    $account = InfluencerAccount::factory()->create();
    AccountSnapshot::create(['influencer_account_id' => $account->id, 'followers' => 1, 'captured_on' => today()->subDays(500)]);
    AccountSnapshot::create(['influencer_account_id' => $account->id, 'followers' => 2, 'captured_on' => today()]);
    ApifyRun::create(['actor' => 'x', 'status' => 'SUCCEEDED'])->forceFill(['created_at' => now()->subDays(500)])->save();

    $this->artisan('model:prune', ['--model' => [AccountSnapshot::class, ApifyRun::class]])->assertSuccessful();

    expect(AccountSnapshot::pluck('followers')->all())->toBe([2])->and(ApifyRun::count())->toBe(0);
});
