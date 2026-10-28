<?php

use App\Enums\PostType;
use App\Models\InfluencerAccount;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\RateLimitedException;
use App\Platforms\PlatformManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const CHANNEL_ID = 'UCX6OQ3DkcsbYNE6H8uQQuVA';

beforeEach(function () {
    config(['services.youtube.key' => 'test-key', 'services.youtube.daily_quota' => 10000]);
    $this->driver = app(PlatformManager::class)->driver('youtube');
});

function ytChannel(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => CHANNEL_ID,
        'snippet' => [
            'title' => 'MrBeast',
            'customUrl' => '@mrbeast',
            'description' => "New videos every week!\nInstagram: https://www.instagram.com/mrbeast.",
            'thumbnails' => ['default' => ['url' => 'https://yt3.ggpht.com/small.jpg'], 'high' => ['url' => 'https://yt3.ggpht.com/big.jpg']],
        ],
        'statistics' => ['subscriberCount' => '312000000', 'hiddenSubscriberCount' => false],
    ], $overrides);
}

function ytVideo(string $id, string $publishedAt, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $id,
        'snippet' => [
            'publishedAt' => $publishedAt,
            'title' => "Video {$id}",
            'description' => 'Watch till the end',
            'thumbnails' => ['high' => ['url' => "https://i.ytimg.com/vi/{$id}/hq.jpg"], 'maxres' => ['url' => "https://i.ytimg.com/vi/{$id}/max.jpg"]],
            'liveBroadcastContent' => 'none',
        ],
        'contentDetails' => ['duration' => 'PT1H2M3S'],
        'statistics' => ['viewCount' => '1500', 'likeCount' => '200', 'commentCount' => '30'],
    ], $overrides);
}

it('resolves a handle link to a channel', function () {
    Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [ytChannel()]])]);

    $account = $this->driver->resolve('https://www.youtube.com/@MrBeast/videos');

    expect($account->externalId)->toBe(CHANNEL_ID)
        ->and($account->handle)->toBe('mrbeast')
        ->and($account->name)->toBe('MrBeast')
        ->and($account->followers)->toBe(312000000)
        ->and($account->avatarUrl)->toBe('https://yt3.ggpht.com/big.jpg')
        ->and($account->url)->toBe('https://www.youtube.com/@mrbeast')
        ->and($account->links)->toBe(['https://www.instagram.com/mrbeast']);

    Http::assertSent(fn (Request $request) => $request['forHandle'] === '@MrBeast' && $request['key'] === 'test-key');
    expect($this->driver->quotaUsedToday())->toBe(1);
});

it('resolves channel links, bare handles and channel ids', function (string $input, string $param, string $value) {
    Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [ytChannel()]])]);

    expect($this->driver->resolve($input)?->externalId)->toBe(CHANNEL_ID);

    Http::assertSent(fn (Request $request) => $request[$param] === $value);
})->with([
    ['https://www.youtube.com/channel/'.CHANNEL_ID, 'id', CHANNEL_ID],
    ['youtube.com/user/MrBeast6000', 'forUsername', 'MrBeast6000'],
    ['https://www.youtube.com/c/MrBeast', 'forHandle', '@MrBeast'],
    ['@mrbeast', 'forHandle', '@mrbeast'],
    [CHANNEL_ID, 'id', CHANNEL_ID],
]);

it('resolves a video link through the video\'s channel', function (string $url) {
    Http::fake([
        'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [['id' => 'abc123', 'snippet' => ['channelId' => CHANNEL_ID]]]]),
        'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [ytChannel()]]),
    ]);

    expect($this->driver->resolve($url)?->externalId)->toBe(CHANNEL_ID);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/videos') && $request['id'] === 'abc123');
})->with([
    'https://www.youtube.com/watch?v=abc123&t=10s',
    'https://youtu.be/abc123',
    'https://www.youtube.com/shorts/abc123',
]);

it('returns null for unknown channels and other sites, and caches lookups', function () {
    Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => []])]);

    expect($this->driver->resolve('@nobody'))->toBeNull()
        ->and($this->driver->resolve('https://www.instagram.com/mrbeast'))->toBeNull();

    Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [ytChannel()]])]);

    $this->driver->resolve('@mrbeast');
    $this->driver->resolve('@MrBeast');

    // One call for @nobody (nulls are not cached), one for @mrbeast; the second @mrbeast is cached.
    Http::assertSentCount(2);
});

it('reads new uploads from the uploads playlist', function () {
    Http::fake([
        'www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [
            ['contentDetails' => ['videoId' => 'new1', 'videoPublishedAt' => '2026-10-27T10:00:00Z']],
            ['contentDetails' => ['videoId' => 'soon', 'videoPublishedAt' => '2026-10-26T10:00:00Z']],
            ['contentDetails' => ['videoId' => 'new2', 'videoPublishedAt' => '2026-10-25T10:00:00Z']],
            ['contentDetails' => ['videoId' => 'old', 'videoPublishedAt' => '2026-10-01T10:00:00Z']],
        ]]),
        'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [
            ytVideo('new2', '2026-10-25T10:00:00Z', ['contentDetails' => ['duration' => 'PT45S']]),
            ytVideo('new1', '2026-10-27T10:00:00Z'),
            ytVideo('soon', '2026-10-26T10:00:00Z', ['snippet' => ['liveBroadcastContent' => 'upcoming']]),
        ]]),
    ]);

    $account = InfluencerAccount::make(['external_id' => CHANNEL_ID, 'handle' => '@mrbeast']);
    $posts = $this->driver->fetchRecentPosts($account, CarbonImmutable::parse('2026-10-20'));

    expect($posts->pluck('externalId')->all())->toBe(['new1', 'new2']);

    $post = $posts->first();
    expect($post->type)->toBe(PostType::Video)
        ->and($post->title)->toBe('Video new1')
        ->and($post->images)->toBe(['https://i.ytimg.com/vi/new1/max.jpg'])
        ->and($post->durationSeconds)->toBe(3723)
        ->and($post->permalink)->toBe('https://www.youtube.com/watch?v=new1')
        ->and($post->metrics)->toBe(['views' => 1500, 'likes' => 200, 'comments' => 30])
        ->and($posts->last()->durationSeconds)->toBe(45);

    Http::assertSent(fn (Request $request) => ($request['playlistId'] ?? null) === 'UUX6OQ3DkcsbYNE6H8uQQuVA');
    Http::assertSent(fn (Request $request) => ($request['id'] ?? null) === 'new1,soon,new2');
});

it('skips the videos call when nothing is new', function () {
    Http::fake(['www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [
        ['contentDetails' => ['videoId' => 'old', 'videoPublishedAt' => '2026-10-01T10:00:00Z']],
    ]])]);

    $posts = $this->driver->fetchRecentPosts(InfluencerAccount::make(['external_id' => CHANNEL_ID]), now());

    expect($posts)->toBeEmpty();
    Http::assertSentCount(1);
});

it('fetches profiles 50 channels per request', function () {
    Http::fake(['www.googleapis.com/youtube/v3/channels*' => fn (Request $request) => Http::response([
        'items' => collect(explode(',', $request['id']))->map(fn ($id) => ytChannel(['id' => $id]))->all(),
    ])]);

    $accounts = collect(range(1, 120))->map(fn ($i) => InfluencerAccount::make(['external_id' => "UC{$i}"]));
    $profiles = $this->driver->fetchProfiles($accounts);

    expect($profiles)->toHaveCount(120)->and($profiles->get('UC77')->externalId)->toBe('UC77');
    Http::assertSentCount(3);
});

it('throws when a channel is gone', function () {
    Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => []])]);

    $this->driver->fetchProfile(InfluencerAccount::make(['external_id' => CHANNEL_ID]));
})->throws(PlatformException::class, 'was not found');

it('retries server errors', function () {
    Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::sequence()
        ->push(['error' => ['message' => 'Backend Error']], 503)
        ->push(['items' => [ytChannel()]])]);

    expect($this->driver->resolve('@mrbeast')?->externalId)->toBe(CHANNEL_ID);
    Http::assertSentCount(2);
});

it('stops at the daily quota', function () {
    Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(
        ['error' => ['message' => 'Quota exceeded', 'errors' => [['reason' => 'quotaExceeded']]]], 403,
    )]);

    expect(fn () => $this->driver->resolve('@mrbeast'))->toThrow(RateLimitedException::class, 'daily quota used up');

    config(['services.youtube.daily_quota' => 1]);

    expect(fn () => $this->driver->resolve('@another'))->toThrow(RateLimitedException::class);
    Http::assertSentCount(1);
});

it('needs an API key', function () {
    config(['services.youtube.key' => null]);

    $this->driver->resolve('@mrbeast');
})->throws(PlatformException::class, 'YOUTUBE_API_KEY is not set');
