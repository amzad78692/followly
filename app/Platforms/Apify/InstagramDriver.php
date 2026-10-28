<?php

namespace App\Platforms\Apify;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Platforms\Data\AccountData;
use App\Platforms\Data\PostData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Instagram through the apify/instagram-profile-scraper actor (profile plus its latest posts).
 */
class InstagramDriver extends ApifyDriver
{
    /** First path segments that are not usernames. */
    protected const RESERVED = ['p', 'reel', 'reels', 'tv', 'explore', 'accounts', 'direct', 'stories'];

    public function platform(): Platform
    {
        return Platform::Instagram;
    }

    protected function input(string $handle, int $limit): array
    {
        return ['usernames' => [$handle], 'resultsLimit' => $limit];
    }

    protected function handleFromUrl(string $url): ?string
    {
        $segments = $this->segments($url);

        // instagram.com/stories/<handle>/...
        if (($segments[0] ?? null) === 'stories') {
            return $this->cleanHandle($segments[1] ?? null);
        }

        return in_array($segments[0] ?? null, self::RESERVED, true) ? null : $this->cleanHandle($segments[0] ?? null);
    }

    protected function toAccount(array $items): ?AccountData
    {
        $profile = collect($items)->first(fn ($item) => isset($item['username']) && ! isset($item['error']));

        if (! $profile) {
            return null;
        }

        return new AccountData(
            platform: Platform::Instagram,
            externalId: (string) ($profile['id'] ?? $profile['username']),
            handle: $profile['username'],
            name: ($profile['fullName'] ?? null) ?: $profile['username'],
            url: Platform::Instagram->profileUrl($profile['username']),
            avatarUrl: $profile['profilePicUrlHD'] ?? $profile['profilePicUrl'] ?? null,
            bio: ($profile['biography'] ?? null) ?: null,
            followers: (int) ($profile['followersCount'] ?? 0),
            isVerified: (bool) ($profile['verified'] ?? false),
            links: collect($profile['externalUrls'] ?? [])
                ->map(fn ($link) => is_array($link) ? ($link['url'] ?? null) : $link)
                ->push($profile['externalUrl'] ?? null)
                ->merge($this->linksIn($profile['biography'] ?? null))
                ->filter()->unique()->values()->all(),
        );
    }

    protected function toPosts(array $items): Collection
    {
        return collect($items)
            ->flatMap(fn ($item) => $item['latestPosts'] ?? [])
            ->filter(fn ($post) => isset($post['id'], $post['timestamp']))
            ->map(fn (array $post) => new PostData(
                externalId: (string) $post['id'],
                type: match ($post['type'] ?? null) {
                    'Video' => PostType::Video,
                    'Sidecar' => PostType::Carousel,
                    default => PostType::Image,
                },
                publishedAt: CarbonImmutable::parse($post['timestamp']),
                body: ($post['caption'] ?? null) ?: null,
                images: array_values(array_filter(($post['type'] ?? null) === 'Sidecar' && ! empty($post['images']) ? $post['images'] : [$post['displayUrl'] ?? null])),
                durationSeconds: isset($post['videoDuration']) ? (int) round($post['videoDuration']) : null,
                permalink: $post['url'] ?? (isset($post['shortCode']) ? "https://www.instagram.com/p/{$post['shortCode']}/" : null),
                metrics: $this->metrics([
                    'views' => $post['videoViewCount'] ?? $post['videoPlayCount'] ?? null,
                    'likes' => ($post['likesCount'] ?? -1) >= 0 ? $post['likesCount'] : null,
                    'comments' => $post['commentsCount'] ?? null,
                ]),
            ))
            ->unique(fn (PostData $post) => $post->externalId)
            ->values();
    }
}
