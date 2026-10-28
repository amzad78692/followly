<?php

namespace App\Platforms\Data;

use App\Enums\PostType;
use Carbon\CarbonImmutable;

/**
 * One post (video, image, carousel or text) as a driver returns it.
 */
final readonly class PostData
{
    /**
     * @param  array<int, string>  $images  Thumbnail for videos, every image for carousels.
     * @param  array<string, int>  $metrics  views, likes, comments, shares where the platform has them.
     */
    public function __construct(
        public string $externalId,
        public PostType $type,
        public CarbonImmutable $publishedAt,
        public ?string $title = null,
        public ?string $body = null,
        public array $images = [],
        public ?int $durationSeconds = null,
        public ?string $permalink = null,
        public array $metrics = [],
    ) {}

    /**
     * Columns for a Post row (the account fills in influencer and platform).
     */
    public function toPostAttributes(): array
    {
        return [
            'external_id' => $this->externalId,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'media' => $this->images ? ['images' => array_values($this->images)] : null,
            'duration_seconds' => $this->durationSeconds,
            'permalink' => $this->permalink,
            'metrics' => array_filter($this->metrics, fn ($value) => $value !== null) ?: null,
            'published_at' => $this->publishedAt,
        ];
    }
}
