<?php

namespace App\Platforms;

use App\Enums\Platform;
use App\Platforms\Contracts\PlatformDriver;
use App\Platforms\Data\AccountData;
use App\Platforms\Exceptions\RateLimitedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Shared plumbing for the drivers: per-platform rate limit, HTTP retries and URL parsing.
 */
abstract class Driver implements PlatformDriver
{
    public function matchesUrl(string $url): bool
    {
        return Platform::fromUrl($url) === $this->platform();
    }

    public function resolve(string $urlOrHandle): ?AccountData
    {
        $input = trim($urlOrHandle);

        if ($input === '' || (str_contains($input, '/') && ! $this->matchesUrl($input))) {
            return null;
        }

        $key = 'platforms:resolve:'.$this->platform()->value.':'.md5(Str::lower($input));

        return Cache::remember($key, now()->addMinutes(config('platforms.resolve_cache_minutes')), fn () => $this->lookup($input));
    }

    /**
     * Resolve without the cache.
     */
    abstract protected function lookup(string $urlOrHandle): ?AccountData;

    /**
     * Count one request against the driver's per-minute limit.
     *
     * @throws RateLimitedException
     */
    protected function throttle(): void
    {
        $key = 'platforms:'.$this->platform()->value;

        if (RateLimiter::tooManyAttempts($key, config('platforms.rate_limits.'.$this->platform()->value, 60))) {
            throw new RateLimitedException($this->platform(), RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * An HTTP client that retries with backoff on 429, 5xx and dropped connections, and never throws on status.
     */
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->retry(config('platforms.retry_backoff'), 0, fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())), throw: false);
    }

    /**
     * Path segments of a link, e.g. ["@mkbhd", "videos"].
     *
     * @return array<int, string>
     */
    protected function segments(string $url): array
    {
        $path = parse_url($this->absolute($url), PHP_URL_PATH) ?? '';

        return array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    }

    protected function query(string $url, string $key): ?string
    {
        parse_str(parse_url($this->absolute($url), PHP_URL_QUERY) ?? '', $query);

        return is_string($query[$key] ?? null) ? $query[$key] : null;
    }

    protected function isUrl(string $input): bool
    {
        return str_contains($input, '/') || Platform::fromUrl($input) !== null;
    }

    /**
     * A handle without "@", or null when it cannot be one.
     */
    protected function cleanHandle(?string $handle): ?string
    {
        $handle = ltrim(trim((string) $handle), '@');

        return preg_match('/^[\w.\-]{1,100}$/u', $handle) ? $handle : null;
    }

    /**
     * Every http(s) link in a block of text.
     *
     * @return array<int, string>
     */
    protected function linksIn(?string $text): array
    {
        preg_match_all('~https?://[^\s<>"\')\]]+~i', (string) $text, $matches);

        return array_values(array_unique(array_map(fn ($link) => rtrim($link, '.,;:!?'), $matches[0])));
    }

    private function absolute(string $url): string
    {
        return str_contains($url, '://') ? $url : "https://{$url}";
    }
}
