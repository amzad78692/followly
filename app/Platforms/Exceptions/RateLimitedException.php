<?php

namespace App\Platforms\Exceptions;

use App\Enums\Platform;

/**
 * The driver's own per-minute limit (or the platform's quota) was hit. Retry after $retryAfter seconds.
 */
class RateLimitedException extends PlatformException
{
    public function __construct(Platform $platform, public readonly int $retryAfter, string $message = 'rate limit reached')
    {
        parent::__construct($platform, "{$message}, retry in {$retryAfter}s", 429);
    }
}
