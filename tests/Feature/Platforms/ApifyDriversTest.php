<?php

use App\Enums\Platform;
use App\Enums\PostType;
use App\Models\ApifyRun;
use App\Models\InfluencerAccount;
use App\Platforms\Exceptions\AccountNotFoundException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\RateLimitedException;
use App\Platforms\PlatformManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.apify.token' => 'apify-test', 'services.apify.wait_seconds' => 1, 'services.apify.timeout_seconds' => 300]);
});

/**
 * Fake a finished actor run returning $items.
 */
function fakeApify(array $items, array $run = []): void
{
    Http::fake([
        'api.apify.com/v2/acts/*' => Http::response(['data' => [
            'id' => 'run-1', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'ds-1', 'usageTotalUsd' => 0.0123, ...$run,
        ]]),
        'api.apify.com/v2/datasets/ds-1/items*' => Http::response($items),
    ]);
}

function instagramProfile(): array
{
    return [
        'id' => '2278169415',
        'username' => 'mrbeast',
        'fullName' => 'MrBeast',
        'biography' => 'I want to make the world a better place',
        'externalUrls' => [['url' => 'https://www.youtube.com/@mrbeast']],
        'followersCount' => 65000000,
        'verified' => true,
        'profilePicUrl' => 'https://cdn.instagram.com/small.jpg',
        'profilePicUrlHD' => 'https://cdn.instagram.com/hd.jpg',
        'latestPosts' => [
            ['id' => '1', 'type' => 'Image', 'shortCode' => 'AAA', 'caption' => 'Old one', 'url' => 'https://www.instagram.com/p/AAA/', 'displayUrl' => 'https://cdn/1.jpg', 'likesCount' => 10, 'commentsCount' => 2, 'timestamp' => '2026-09-01T10:00:00.000Z'],
            ['id' => '2', 'type' => 'Sidecar', 'shortCode' => 'BBB', 'caption' => 'Carousel', 'url' => 'https://www.instagram.com/p/BBB/', 'displayUrl' => 'https://cdn/2.jpg', 'images' => ['https://cdn/2a.jpg', 'https://cdn/2b.jpg'], 'likesCount' => -1, 'commentsCount' => 5, 'timestamp' => '2026-10-26T10:00:00.000Z'],
            ['id' => '3', 'type' => 'Video', 'shortCode' => 'CCC', 'caption' => 'Reel', 'url' => 'https://www.instagram.com/p/CCC/', 'displayUrl' => 'https://cdn/3.jpg', 'videoDuration' => 31.6, 'videoViewCount' => 9000, 'likesCount' => 300, 'commentsCount' => 7, 'timestamp' => '2026-10-27T10:00:00.000Z'],
        ],
    ];
}

it('resolves an Instagram profile and logs the run cost', function () {
    fakeApify([instagramProfile()]);

    $account = app(PlatformManager::class)->driver('instagram')->resolve('https://www.instagram.com/mrbeast/?hl=en');

    expect($account->platform)->toBe(Platform::Instagram)
        ->and($account->externalId)->toBe('2278169415')
        ->and($account->handle)->toBe('mrbeast')
        ->and($account->name)->toBe('MrBeast')
        ->and($account->followers)->toBe(65000000)
        ->and($account->isVerified)->toBeTrue()
        ->and($account->avatarUrl)->toBe('https://cdn.instagram.com/hd.jpg')
        ->and($account->links)->toBe(['https://www.youtube.com/@mrbeast']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'acts/apify~instagram-profile-scraper/runs?waitForFinish=1')
        && $request['usernames'] === ['mrbeast']
        && $request->hasHeader('Authorization', 'Bearer apify-test'));

    $run = ApifyRun::sole();
    expect($run->platform)->toBe(Platform::Instagram)
        ->and($run->run_id)->toBe('run-1')
        ->and($run->status)->toBe('SUCCEEDED')
        ->and($run->items_count)->toBe(1)
        ->and($run->usage_usd)->toBe(0.0123)
        ->and(ApifyRun::spentThisMonth())->toBe(0.0123);
});

it('logs pay-per-event charges that usageTotalUsd does not show yet', function () {
    fakeApify([instagramProfile()], [
        'usageTotalUsd' => 0,
        'pricingInfo' => ['pricingModel' => 'PAY_PER_EVENT', 'pricingPerEvent' => ['actorChargeEvents' => ['profile' => ['eventPriceUsd' => 0.0026]]]],
        'chargedEventCounts' => ['profile' => 2, 'unpriced' => 5],
    ]);

    app(PlatformManager::class)->driver('instagram')->resolve('mrbeast');

    expect(ApifyRun::sole()->usage_usd)->toBe(0.0052);
});

it('fills in the cost of recent runs once Apify has charged them', function () {
    $pending = ApifyRun::create(['platform' => Platform::X, 'actor' => 'apidojo/tweet-scraper', 'run_id' => 'run-1', 'status' => 'SUCCEEDED', 'usage_usd' => 0]);
    $old = ApifyRun::create(['platform' => Platform::X, 'actor' => 'apidojo/tweet-scraper', 'run_id' => 'run-2', 'status' => 'SUCCEEDED', 'usage_usd' => 0]);
    $old->forceFill(['created_at' => now()->subDays(2)])->save();
    ApifyRun::create(['platform' => Platform::X, 'actor' => 'apidojo/tweet-scraper', 'run_id' => 'run-3', 'status' => 'SUCCEEDED', 'usage_usd' => 0.5]);

    Http::fake(['api.apify.com/v2/actor-runs/run-1' => Http::response(['data' => ['id' => 'run-1', 'usageTotalUsd' => 0.0012]])]);

    $this->artisan('apify:costs')->expectsOutput('1 Apify run costs updated.')->assertSuccessful();

    expect($pending->fresh()->usage_usd)->toBe(0.0012)
        ->and($old->fresh()->usage_usd)->toBe(0.0);
    Http::assertSentCount(1);
});

it('maps Instagram posts and reuses the run for the profile', function () {
    fakeApify([instagramProfile()]);
    $driver = app(PlatformManager::class)->driver('instagram');
    $account = InfluencerAccount::make(['platform' => Platform::Instagram, 'handle' => '@mrbeast']);

    $posts = $driver->fetchRecentPosts($account, CarbonImmutable::parse('2026-10-01'));
    $driver->fetchProfile($account);

    expect($posts->pluck('externalId')->all())->toBe(['3', '2'])
        ->and($posts[0]->type)->toBe(PostType::Video)
        ->and($posts[0]->durationSeconds)->toBe(32)
        ->and($posts[0]->metrics)->toBe(['views' => 9000, 'likes' => 300, 'comments' => 7])
        ->and($posts[1]->type)->toBe(PostType::Carousel)
        ->and($posts[1]->images)->toBe(['https://cdn/2a.jpg', 'https://cdn/2b.jpg'])
        ->and($posts[1]->metrics)->toBe(['comments' => 5])
        ->and($posts[1]->permalink)->toBe('https://www.instagram.com/p/BBB/');

    // One run (start + dataset) for both calls.
    Http::assertSentCount(2);
});

it('does not run an actor for links without a username', function (string $platform, string $url) {
    Http::fake();

    expect(app(PlatformManager::class)->driver($platform)->resolve($url))->toBeNull();

    Http::assertNothingSent();
})->with([
    ['instagram', 'https://www.instagram.com/p/AAA/'],
    ['tiktok', 'https://vm.tiktok.com/ZMabc123/'],
    ['x', 'https://x.com/search?q=mrbeast'],
    ['x', 'https://www.youtube.com/@mrbeast'],
]);

it('resolves a TikTok profile and maps its videos', function () {
    fakeApify([
        [
            'id' => '7300000000000000001', 'text' => 'Newest', 'createTimeISO' => '2026-10-27T12:00:00.000Z',
            'webVideoUrl' => 'https://www.tiktok.com/@mrbeast/video/7300000000000000001',
            'playCount' => 1000, 'diggCount' => 100, 'commentCount' => 10, 'shareCount' => 5,
            'videoMeta' => ['duration' => 58, 'coverUrl' => 'https://cdn/tt1.jpg'],
            'authorMeta' => ['id' => '6614519312189947909', 'name' => 'mrbeast', 'nickName' => 'MrBeast', 'verified' => true, 'signature' => 'yt: https://youtube.com/@mrbeast', 'bioLink' => 'beast.games/watch', 'avatar' => 'https://cdn/tt-avatar.jpg', 'fans' => 110000000],
        ],
        [
            'id' => '7300000000000000002', 'text' => 'Slides', 'createTimeISO' => '2026-10-20T12:00:00.000Z', 'isSlideshow' => true,
            'videoMeta' => ['duration' => 0, 'coverUrl' => 'https://cdn/tt2.jpg'],
            'slideshowImageLinks' => [['tiktokLink' => 'https://cdn/s1-tt.jpg', 'downloadLink' => 'https://cdn/s1.jpg'], ['tiktokLink' => 'https://cdn/s2.jpg']],
            'authorMeta' => ['id' => '6614519312189947909', 'name' => 'mrbeast'],
        ],
    ]);

    $driver = app(PlatformManager::class)->driver('tiktok');
    $account = $driver->resolve('https://www.tiktok.com/@mrbeast/video/7300000000000000001');
    $posts = $driver->fetchRecentPosts(InfluencerAccount::make(['handle' => '@mrbeast']));

    expect($account->externalId)->toBe('6614519312189947909')
        ->and($account->name)->toBe('MrBeast')
        ->and($account->followers)->toBe(110000000)
        ->and($account->links)->toBe(['https://beast.games/watch', 'https://youtube.com/@mrbeast'])
        ->and($posts)->toHaveCount(2)
        ->and($posts[0]->type)->toBe(PostType::Video)
        ->and($posts[0]->durationSeconds)->toBe(58)
        ->and($posts[0]->images)->toBe(['https://cdn/tt1.jpg'])
        ->and($posts[0]->metrics)->toBe(['views' => 1000, 'likes' => 100, 'comments' => 10, 'shares' => 5])
        ->and($posts[1]->type)->toBe(PostType::Carousel)
        ->and($posts[1]->durationSeconds)->toBeNull()
        ->and($posts[1]->images)->toBe(['https://cdn/s1.jpg', 'https://cdn/s2.jpg']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'clockworks~tiktok-scraper') && $request['profiles'] === ['mrbeast']);
});

it('resolves an X profile and leaves out retweets and replies', function () {
    $author = [
        'id' => '2455740283', 'userName' => 'MrBeast', 'name' => 'MrBeast', 'isBlueVerified' => true, 'followers' => 33000000,
        'profilePicture' => 'https://pbs.twimg.com/profile_images/1/a_normal.jpg', 'description' => 'I want to make the world a better place',
        'entities' => ['url' => ['urls' => [['expanded_url' => 'https://www.youtube.com/@mrbeast']]]],
    ];

    fakeApify([
        ['type' => 'tweet', 'id' => '10', 'text' => 'Plain tweet', 'url' => 'https://x.com/MrBeast/status/10', 'createdAt' => 'Tue Oct 27 17:49:36 +0000 2026', 'likeCount' => 50, 'replyCount' => 4, 'retweetCount' => 3, 'quoteCount' => 1, 'viewCount' => 9000, 'author' => $author],
        ['type' => 'tweet', 'id' => '11', 'text' => 'Pics', 'createdAt' => 'Mon Oct 26 17:49:36 +0000 2026', 'author' => $author, 'extendedEntities' => ['media' => [
            ['type' => 'photo', 'media_url_https' => 'https://pbs.twimg.com/media/a.jpg'],
            ['type' => 'photo', 'media_url_https' => 'https://pbs.twimg.com/media/b.jpg'],
        ]]],
        ['type' => 'tweet', 'id' => '12', 'text' => 'Clip', 'createdAt' => 'Sun Oct 25 17:49:36 +0000 2026', 'author' => $author, 'extendedEntities' => ['media' => [
            ['type' => 'video', 'media_url_https' => 'https://pbs.twimg.com/thumb.jpg', 'video_info' => ['duration_millis' => 61500]],
        ]]],
        ['type' => 'tweet', 'id' => '13', 'text' => 'RT', 'isRetweet' => true, 'createdAt' => 'Sun Oct 25 10:00:00 +0000 2026', 'author' => $author],
        ['type' => 'tweet', 'id' => '14', 'text' => '@someone yes', 'isReply' => true, 'createdAt' => 'Sun Oct 25 09:00:00 +0000 2026', 'author' => $author],
        ['noResults' => true],
    ]);

    $driver = app(PlatformManager::class)->driver('x');
    $account = $driver->resolve('https://twitter.com/MrBeast/status/10');
    $posts = $driver->fetchRecentPosts(InfluencerAccount::make(['handle' => '@MrBeast']));

    expect($account->externalId)->toBe('2455740283')
        ->and($account->isVerified)->toBeTrue()
        ->and($account->avatarUrl)->toBe('https://pbs.twimg.com/profile_images/1/a_400x400.jpg')
        ->and($account->links)->toBe(['https://www.youtube.com/@mrbeast'])
        ->and($posts->pluck('externalId')->all())->toBe(['10', '11', '12'])
        ->and($posts->pluck('type')->all())->toBe([PostType::Text, PostType::Carousel, PostType::Video])
        ->and($posts[0]->metrics)->toBe(['views' => 9000, 'likes' => 50, 'comments' => 4, 'shares' => 4])
        ->and($posts[2]->durationSeconds)->toBe(61);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'apidojo~tweet-scraper')
        && $request['searchTerms'] === ['from:mrbeast -filter:retweets -filter:replies']);
});

it('throws when an account is not found', function () {
    fakeApify([['noResults' => true]]);

    app(PlatformManager::class)->driver('x')->fetchProfile(InfluencerAccount::make(['handle' => '@ghost']));
})->throws(AccountNotFoundException::class);

it('polls slow runs until they finish', function () {
    Http::fake([
        'api.apify.com/v2/acts/*' => Http::response(['data' => ['id' => 'run-1', 'status' => 'RUNNING', 'defaultDatasetId' => 'ds-1']]),
        'api.apify.com/v2/actor-runs/run-1*' => Http::sequence()
            ->push(['data' => ['id' => 'run-1', 'status' => 'RUNNING', 'defaultDatasetId' => 'ds-1']])
            ->push(['data' => ['id' => 'run-1', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'ds-1', 'usageTotalUsd' => 0.05]]),
        'api.apify.com/v2/datasets/ds-1/items*' => Http::response([instagramProfile()]),
    ]);

    expect(app(PlatformManager::class)->driver('instagram')->resolve('@mrbeast')?->handle)->toBe('mrbeast')
        ->and(ApifyRun::sole()->usage_usd)->toBe(0.05);

    Http::assertSentCount(4);
});

it('aborts runs that pass the timeout', function () {
    config(['services.apify.timeout_seconds' => 0]);
    Http::fake([
        'api.apify.com/v2/acts/*' => Http::response(['data' => ['id' => 'run-1', 'status' => 'RUNNING', 'defaultDatasetId' => 'ds-1', 'usageTotalUsd' => 0.01]]),
        'api.apify.com/v2/actor-runs/run-1/abort' => Http::response(['data' => ['id' => 'run-1', 'status' => 'ABORTING']]),
    ]);

    expect(fn () => app(PlatformManager::class)->driver('instagram')->resolve('@mrbeast'))
        ->toThrow(PlatformException::class, 'did not finish');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'actor-runs/run-1/abort'));
    expect(ApifyRun::sole())->status->toBe('TIMED-OUT')->usage_usd->toBe(0.01);
});

it('logs failed runs', function () {
    fakeApify([], ['status' => 'FAILED']);

    expect(fn () => app(PlatformManager::class)->driver('tiktok')->resolve('@mrbeast'))
        ->toThrow(PlatformException::class, 'ended with status FAILED');

    expect(ApifyRun::sole())->status->toBe('FAILED')->error->toContain('FAILED');
});

it('needs an Apify token', function () {
    config(['services.apify.token' => null]);
    Http::fake();

    expect(fn () => app(PlatformManager::class)->driver('x')->resolve('@mrbeast'))
        ->toThrow(PlatformException::class, 'APIFY_TOKEN is not set');

    Http::assertNothingSent();
});

it('rate limits each platform', function () {
    config(['platforms.rate_limits.x' => 1]);
    fakeApify([['noResults' => true]]);
    $driver = app(PlatformManager::class)->driver('x');

    $driver->resolve('@first');

    $driver->resolve('@second');
})->throws(RateLimitedException::class);
