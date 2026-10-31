<?php

namespace App\Platforms\Apify;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Platforms\Data\AccountData;
use App\Platforms\Data\PostData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * TikTok through the clockworks/tiktok-scraper actor. Its items are videos, each carrying
 * the author's profile, so an account with no videos cannot be resolved.
 */
class TikTokDriver extends ApifyDriver
{
    public function platform(): Platform
    {
        return Platform::TikTok;
    }

    protected function handleOf(array $item): ?string
    {
        return $item['authorMeta']['name'] ?? null;
    }

    protected function input(array $handles, int $limit): array
    {
        return [
            'profiles' => $handles,
            'resultsPerPage' => $limit,
            'profileScrapeSections' => ['videos'],
            'shouldDownloadVideos' => false,
            'shouldDownloadCovers' => false,
            'shouldDownloadSubtitles' => false,
            'shouldDownloadSlideshowImages' => false,
        ];
    }

    protected function handleFromUrl(string $url): ?string
    {
        // tiktok.com/@<handle> and tiktok.com/@<handle>/video/<id>; vm.tiktok.com short links carry no handle.
        $first = $this->segments($url)[0] ?? '';

        return str_starts_with($first, '@') ? $this->cleanHandle($first) : null;
    }

    protected function toAccount(array $items): ?AccountData
    {
        $author = collect($items)->pluck('authorMeta')->first(fn ($author) => isset($author['name']));

        if (! $author) {
            return null;
        }

        return new AccountData(
            platform: Platform::TikTok,
            externalId: (string) ($author['id'] ?? $author['name']),
            handle: $author['name'],
            name: ($author['nickName'] ?? null) ?: $author['name'],
            url: $author['profileUrl'] ?? Platform::TikTok->profileUrl($author['name']),
            avatarUrl: $author['originalAvatarUrl'] ?? $author['avatar'] ?? null,
            bio: ($author['signature'] ?? null) ?: null,
            followers: (int) ($author['fans'] ?? 0),
            isVerified: (bool) ($author['verified'] ?? false),
            // bioLink comes without a scheme ("example.com/page").
            links: collect([($author['bioLink'] ?? null) ? $this->absolute($author['bioLink']) : null])
                ->merge($this->linksIn($author['signature'] ?? null))
                ->filter()->unique()->values()->all(),
        );
    }

    protected function toPosts(array $items): Collection
    {
        return collect($items)
            ->filter(fn ($item) => isset($item['id']) && (isset($item['createTimeISO']) || isset($item['createTime'])))
            ->map(fn (array $video) => new PostData(
                externalId: (string) $video['id'],
                type: ($video['isSlideshow'] ?? false) ? PostType::Carousel : PostType::Video,
                publishedAt: isset($video['createTimeISO']) ? CarbonImmutable::parse($video['createTimeISO']) : CarbonImmutable::createFromTimestamp($video['createTime']),
                body: ($video['text'] ?? null) ?: null,
                images: collect($video['slideshowImageLinks'] ?? [])->map(fn ($image) => $image['downloadLink'] ?? $image['tiktokLink'] ?? null)->filter()->values()->all()
                    ?: array_values(array_filter([$video['videoMeta']['coverUrl'] ?? $video['videoMeta']['originalCoverUrl'] ?? null])),
                // Slideshows report a duration of 0.
                durationSeconds: ((int) ($video['videoMeta']['duration'] ?? 0)) ?: null,
                permalink: $video['webVideoUrl'] ?? null,
                metrics: $this->metrics([
                    'views' => $video['playCount'] ?? null,
                    'likes' => $video['diggCount'] ?? null,
                    'comments' => $video['commentCount'] ?? null,
                    'shares' => $video['shareCount'] ?? null,
                ]),
            ))
            ->unique(fn (PostData $post) => $post->externalId)
            ->values();
    }
}
