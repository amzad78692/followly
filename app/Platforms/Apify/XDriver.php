<?php

namespace App\Platforms\Apify;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Platforms\Data\AccountData;
use App\Platforms\Data\PostData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * X through the apidojo/tweet-scraper actor. Its items are tweets, each carrying the
 * author's profile. Retweets and replies are left out of the feed.
 */
class XDriver extends ApifyDriver
{
    /** First path segments that are not usernames. */
    protected const RESERVED = ['home', 'i', 'search', 'explore', 'hashtag', 'intent', 'share', 'settings', 'messages', 'notifications'];

    public function platform(): Platform
    {
        return Platform::X;
    }

    protected function input(array $handles, int $limit): array
    {
        // Searching instead of twitterHandles keeps retweets and replies out of the run (each tweet is charged).
        // maxItems caps the whole run, not each handle.
        return [
            'searchTerms' => array_map(fn (string $handle) => "from:{$handle} -filter:retweets -filter:replies", $handles),
            'maxItems' => $limit * count($handles),
            'sort' => 'Latest',
        ];
    }

    protected function handleOf(array $item): ?string
    {
        return $item['author']['userName'] ?? null;
    }

    protected function handleFromUrl(string $url): ?string
    {
        $first = $this->segments($url)[0] ?? null;

        return in_array($first, self::RESERVED, true) ? null : $this->cleanHandle($first);
    }

    protected function toAccount(array $items): ?AccountData
    {
        $author = collect($this->tweets($items))->pluck('author')->first(fn ($author) => isset($author['userName']));

        if (! $author) {
            return null;
        }

        return new AccountData(
            platform: Platform::X,
            externalId: (string) ($author['id'] ?? $author['userName']),
            handle: $author['userName'],
            name: ($author['name'] ?? null) ?: $author['userName'],
            url: Platform::X->profileUrl($author['userName']),
            avatarUrl: isset($author['profilePicture']) ? str_replace('_normal.', '_400x400.', $author['profilePicture']) : null,
            bio: ($author['description'] ?? null) ?: null,
            followers: (int) ($author['followers'] ?? 0),
            isVerified: (bool) (($author['isVerified'] ?? false) || ($author['isBlueVerified'] ?? false)),
            links: collect($author['entities']['url']['urls'] ?? [])
                ->merge($author['entities']['description']['urls'] ?? [])
                ->pluck('expanded_url')
                ->merge($this->linksIn($author['description'] ?? null))
                ->filter()->unique()->values()->all(),
        );
    }

    protected function toPosts(array $items): Collection
    {
        return collect($this->tweets($items))
            ->reject(fn (array $tweet) => ($tweet['isRetweet'] ?? false) || ($tweet['isReply'] ?? false))
            ->map(function (array $tweet) {
                $media = collect($tweet['extendedEntities']['media'] ?? []);
                $video = $media->first(fn ($item) => in_array($item['type'] ?? null, ['video', 'animated_gif'], true));

                return new PostData(
                    externalId: (string) $tweet['id'],
                    type: match (true) {
                        $video !== null => PostType::Video,
                        $media->count() > 1 => PostType::Carousel,
                        $media->isNotEmpty() => PostType::Image,
                        default => PostType::Text,
                    },
                    publishedAt: CarbonImmutable::parse($tweet['createdAt']),
                    body: ($tweet['fullText'] ?? $tweet['text'] ?? null) ?: null,
                    images: $media->pluck('media_url_https')->filter()->values()->all(),
                    durationSeconds: isset($video['video_info']['duration_millis']) ? intdiv((int) $video['video_info']['duration_millis'], 1000) : null,
                    permalink: $tweet['url'] ?? $tweet['twitterUrl'] ?? null,
                    metrics: $this->metrics([
                        'views' => $tweet['viewCount'] ?? null,
                        'likes' => $tweet['likeCount'] ?? null,
                        'comments' => $tweet['replyCount'] ?? null,
                        'shares' => isset($tweet['retweetCount']) ? ($tweet['retweetCount'] + ($tweet['quoteCount'] ?? 0)) : null,
                    ]),
                );
            })
            ->unique(fn (PostData $post) => $post->externalId)
            ->values();
    }

    /**
     * The actor pads empty results with {"noResults": true} items.
     */
    protected function tweets(array $items): array
    {
        return array_values(array_filter($items, fn ($item) => isset($item['id'], $item['createdAt']) && ($item['type'] ?? 'tweet') === 'tweet'));
    }
}
