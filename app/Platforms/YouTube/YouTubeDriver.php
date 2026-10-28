<?php

namespace App\Platforms\YouTube;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Models\InfluencerAccount;
use App\Platforms\Data\AccountData;
use App\Platforms\Data\PostData;
use App\Platforms\Driver;
use App\Platforms\Exceptions\AccountNotFoundException;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\Exceptions\RateLimitedException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * YouTube Data API v3. Every call used here costs 1 quota unit (search, at 100, is avoided).
 */
class YouTubeDriver extends Driver
{
    protected const BASE_URL = 'https://www.googleapis.com/youtube/v3/';

    /** The API returns at most 50 ids per channels/videos call. */
    public const BATCH_SIZE = 50;

    public function platform(): Platform
    {
        return Platform::YouTube;
    }

    protected function lookup(string $urlOrHandle): ?AccountData
    {
        if (! $this->isUrl($urlOrHandle)) {
            $handle = $this->cleanHandle($urlOrHandle);

            return match (true) {
                $handle === null => null,
                $this->isChannelId($handle) => $this->channel(['id' => $handle]),
                default => $this->channel(['forHandle' => '@'.$handle]),
            };
        }

        $segments = $this->segments($urlOrHandle);
        $first = $segments[0] ?? null;

        if ($videoId = $this->videoId($urlOrHandle, $segments)) {
            $channelId = $this->get('videos', ['part' => 'snippet', 'id' => $videoId])['items'][0]['snippet']['channelId'] ?? null;

            return $channelId ? $this->channel(['id' => $channelId]) : null;
        }

        return match (true) {
            $first === null => null,
            $first === 'channel' && isset($segments[1]) => $this->channel(['id' => $segments[1]]),
            $first === 'user' && isset($segments[1]) => $this->channel(['forUsername' => $segments[1]]),
            // Old /c/ custom URLs have no API lookup; they usually match the channel's handle.
            $first === 'c' && isset($segments[1]) => $this->channel(['forHandle' => '@'.$segments[1]]),
            ($handle = $this->cleanHandle($first)) !== null => $this->channel(['forHandle' => '@'.$handle]),
            default => null,
        };
    }

    public function fetchProfile(InfluencerAccount $account): AccountData
    {
        return $this->channel(['id' => $account->external_id])
            ?? throw AccountNotFoundException::for($this->platform(), $account->external_id);
    }

    /**
     * Profiles for many accounts, 50 channels per call. Channels that no longer exist are left out.
     *
     * @param  iterable<InfluencerAccount>  $accounts
     * @return Collection<string, AccountData> keyed by channel id
     */
    public function fetchProfiles(iterable $accounts): Collection
    {
        return collect($accounts)
            ->pluck('external_id')
            ->filter()
            ->unique()
            ->chunk(self::BATCH_SIZE)
            ->flatMap(fn (Collection $ids) => $this->get('channels', ['part' => 'snippet,statistics', 'id' => $ids->join(','), 'maxResults' => self::BATCH_SIZE])['items'] ?? [])
            ->mapWithKeys(fn (array $channel) => [$channel['id'] => $this->toAccount($channel)]);
    }

    /**
     * Reads the channel's uploads playlist rather than searching, so a sync costs 2 units.
     */
    public function fetchRecentPosts(InfluencerAccount $account, ?CarbonInterface $since = null): Collection
    {
        $playlist = $this->get('playlistItems', [
            'part' => 'contentDetails',
            // Every channel's uploads playlist is its id with "UC" swapped for "UU".
            'playlistId' => 'UU'.substr($account->external_id, 2),
            'maxResults' => min(self::BATCH_SIZE, config('platforms.posts_per_fetch')),
        ], notFoundIsEmpty: true);

        $ids = collect($playlist['items'] ?? [])
            ->filter(fn (array $item) => ! $since || CarbonImmutable::parse($item['contentDetails']['videoPublishedAt'] ?? 'now')->gt($since))
            ->pluck('contentDetails.videoId')
            ->filter();

        if ($ids->isEmpty()) {
            return collect();
        }

        return collect($this->get('videos', ['part' => 'snippet,contentDetails,statistics', 'id' => $ids->join(',')])['items'] ?? [])
            // Scheduled premieres and streams have not been published yet.
            ->reject(fn (array $video) => ($video['snippet']['liveBroadcastContent'] ?? 'none') === 'upcoming')
            ->map(fn (array $video) => $this->toPost($video))
            ->sortByDesc(fn (PostData $post) => $post->publishedAt)
            ->values();
    }

    /**
     * Quota units used today (Google resets the quota at midnight Pacific time).
     */
    public function quotaUsedToday(): int
    {
        return (int) Cache::get($this->quotaKey(), 0);
    }

    protected function channel(array $filter): ?AccountData
    {
        $channel = $this->get('channels', ['part' => 'snippet,statistics', ...$filter])['items'][0] ?? null;

        return $channel ? $this->toAccount($channel) : null;
    }

    protected function toAccount(array $channel): AccountData
    {
        $snippet = $channel['snippet'] ?? [];
        $stats = $channel['statistics'] ?? [];
        $handle = ltrim($snippet['customUrl'] ?? '', '@') ?: $channel['id'];

        return new AccountData(
            platform: Platform::YouTube,
            externalId: $channel['id'],
            handle: $handle,
            name: $snippet['title'] ?? $handle,
            url: $handle === $channel['id'] ? "https://www.youtube.com/channel/{$channel['id']}" : Platform::YouTube->profileUrl($handle),
            avatarUrl: $this->thumbnail($snippet['thumbnails'] ?? []),
            bio: ($snippet['description'] ?? null) ?: null,
            followers: ($stats['hiddenSubscriberCount'] ?? false) ? 0 : (int) ($stats['subscriberCount'] ?? 0),
            // The API does not expose the verified badge.
            isVerified: false,
            links: $this->linksIn($snippet['description'] ?? null),
        );
    }

    protected function toPost(array $video): PostData
    {
        $snippet = $video['snippet'];
        $stats = $video['statistics'] ?? [];
        $duration = $video['contentDetails']['duration'] ?? null;

        return new PostData(
            externalId: $video['id'],
            type: PostType::Video,
            publishedAt: CarbonImmutable::parse($snippet['publishedAt']),
            title: $snippet['title'] ?? null,
            body: Str::limit($snippet['description'] ?? '', 1000) ?: null,
            images: array_filter([$this->thumbnail($snippet['thumbnails'] ?? [])]),
            durationSeconds: $duration ? (int) CarbonInterval::make($duration)?->totalSeconds : null,
            permalink: "https://www.youtube.com/watch?v={$video['id']}",
            metrics: [
                'views' => isset($stats['viewCount']) ? (int) $stats['viewCount'] : null,
                'likes' => isset($stats['likeCount']) ? (int) $stats['likeCount'] : null,
                'comments' => isset($stats['commentCount']) ? (int) $stats['commentCount'] : null,
            ],
        );
    }

    protected function thumbnail(array $thumbnails): ?string
    {
        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $size) {
            if (isset($thumbnails[$size]['url'])) {
                return $thumbnails[$size]['url'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $segments
     */
    protected function videoId(string $url, array $segments): ?string
    {
        if (Str::endsWith(Str::lower(parse_url(str_contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST) ?? ''), 'youtu.be')) {
            return $segments[0] ?? null;
        }

        if (($segments[0] ?? null) === 'watch') {
            return $this->query($url, 'v');
        }

        return in_array($segments[0] ?? null, ['shorts', 'live', 'embed'], true) ? ($segments[1] ?? null) : null;
    }

    protected function isChannelId(string $value): bool
    {
        return preg_match('/^UC[\w-]{22}$/', $value) === 1;
    }

    /**
     * @throws PlatformException
     */
    protected function get(string $endpoint, array $query, bool $notFoundIsEmpty = false): array
    {
        $key = config('services.youtube.key') ?: throw new PlatformException($this->platform(), 'YOUTUBE_API_KEY is not set');

        if ($this->quotaUsedToday() >= config('services.youtube.daily_quota')) {
            throw new RateLimitedException($this->platform(), $this->secondsUntilQuotaReset(), 'daily quota used up');
        }

        $this->throttle();

        try {
            $response = $this->http()->get(self::BASE_URL.$endpoint, [...$query, 'key' => $key]);
        } catch (ConnectionException $e) {
            throw new PlatformException($this->platform(), 'API is unreachable: '.$e->getMessage(), previous: $e);
        }

        Cache::add($this->quotaKey(), 0, now()->addDays(2));
        Cache::increment($this->quotaKey());

        if ($notFoundIsEmpty && $response->notFound()) {
            return [];
        }

        if ($response->forbidden() && in_array($response->json('error.errors.0.reason'), ['quotaExceeded', 'dailyLimitExceeded'], true)) {
            throw new RateLimitedException($this->platform(), $this->secondsUntilQuotaReset(), 'daily quota used up');
        }

        if ($response->failed()) {
            throw PlatformException::fromResponse($this->platform(), $response);
        }

        return $response->json() ?? [];
    }

    protected function quotaKey(): string
    {
        return 'platforms:youtube:quota:'.now('America/Los_Angeles')->toDateString();
    }

    protected function secondsUntilQuotaReset(): int
    {
        $now = now('America/Los_Angeles');

        return (int) $now->diffInSeconds($now->copy()->addDay()->startOfDay());
    }
}
