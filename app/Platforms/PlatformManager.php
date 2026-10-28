<?php

namespace App\Platforms;

use App\Enums\Platform;
use App\Platforms\Apify\InstagramDriver;
use App\Platforms\Apify\TikTokDriver;
use App\Platforms\Apify\XDriver;
use App\Platforms\Contracts\PlatformDriver;
use App\Platforms\Data\AccountData;
use App\Platforms\Exceptions\PlatformException;
use App\Platforms\YouTube\YouTubeDriver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Picks the driver for a platform, link or handle. Swap a platform's driver with extend(),
 * e.g. to move it from Apify to an official API.
 */
class PlatformManager
{
    /** @var array<string, class-string<PlatformDriver>|PlatformDriver> */
    protected array $drivers = [
        'youtube' => YouTubeDriver::class,
        'instagram' => InstagramDriver::class,
        'tiktok' => TikTokDriver::class,
        'x' => XDriver::class,
    ];

    public function __construct(protected Container $container) {}

    public function driver(Platform|string $platform): PlatformDriver
    {
        $platform = $platform instanceof Platform ? $platform : Platform::from($platform);
        $driver = $this->drivers[$platform->value];

        return $driver instanceof PlatformDriver ? $driver : $this->drivers[$platform->value] = $this->container->make($driver);
    }

    /**
     * Every driver, YouTube first (it is the only free one).
     *
     * @return Collection<string, PlatformDriver>
     */
    public function drivers(): Collection
    {
        return collect(Platform::cases())->mapWithKeys(fn (Platform $platform) => [$platform->value => $this->driver($platform)]);
    }

    public function forUrl(string $url): ?PlatformDriver
    {
        $platform = Platform::fromUrl($url);

        return $platform ? $this->driver($platform) : null;
    }

    public function extend(Platform $platform, PlatformDriver|string $driver): static
    {
        $this->drivers[$platform->value] = $driver;

        return $this;
    }

    /**
     * Resolve a link with its platform's driver, or a bare handle with the first platform that has it.
     *
     * @throws PlatformException when a link's platform cannot be reached
     */
    public function resolve(string $urlOrHandle): ?AccountData
    {
        $input = trim($urlOrHandle);

        if (str_contains($input, '/') || Platform::fromUrl($input)) {
            return $this->forUrl($input)?->resolve($input)?->withSource('search');
        }

        foreach ($this->drivers() as $driver) {
            try {
                if ($account = $driver->resolve($input)) {
                    return $account->withSource('search');
                }
            } catch (PlatformException $e) {
                // One platform being down should not stop the others from being tried.
                report($e);
            }
        }

        return null;
    }
}
