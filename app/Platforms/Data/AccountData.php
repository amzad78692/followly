<?php

namespace App\Platforms\Data;

use App\Enums\Platform;

/**
 * A creator's profile on one platform, as a driver returns it.
 */
final readonly class AccountData
{
    /**
     * @param  array<int, string>  $links  External links from the profile (website, other socials).
     * @param  string|null  $source  How the lookup found it: "search", "link", "handle" or null.
     */
    public function __construct(
        public Platform $platform,
        public string $externalId,
        public string $handle,
        public string $name,
        public ?string $url = null,
        public ?string $avatarUrl = null,
        public ?string $bio = null,
        public int $followers = 0,
        public bool $isVerified = false,
        public array $links = [],
        public ?string $source = null,
    ) {}

    public function withSource(string $source): self
    {
        return new self(...[...get_object_vars($this), 'source' => $source]);
    }

    /**
     * The handle as shown in the UI: "@name" for every platform.
     */
    public function displayHandle(): string
    {
        return '@'.ltrim($this->handle, '@');
    }

    /**
     * Columns for an InfluencerAccount row.
     */
    public function toAccountAttributes(): array
    {
        return [
            'platform' => $this->platform,
            'external_id' => $this->externalId,
            'handle' => $this->displayHandle(),
            'url' => $this->url ?? $this->platform->profileUrl($this->handle),
            'followers' => $this->followers,
            'is_verified' => $this->isVerified,
        ];
    }

    public function toArray(): array
    {
        return [...get_object_vars($this), 'platform' => $this->platform->value];
    }

    public static function fromArray(array $data): self
    {
        return new self(...[...$data, 'platform' => Platform::from($data['platform'])]);
    }
}
