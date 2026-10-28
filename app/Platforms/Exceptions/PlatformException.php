<?php

namespace App\Platforms\Exceptions;

use App\Enums\Platform;
use Illuminate\Http\Client\Response;
use RuntimeException;

class PlatformException extends RuntimeException
{
    public function __construct(public readonly Platform $platform, string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("{$platform->label()}: {$message}", $code, $previous);
    }

    public static function fromResponse(Platform $platform, Response $response): static
    {
        $error = $response->json('error');
        $message = is_array($error) ? ($error['message'] ?? json_encode($error)) : ($error ?: $response->reason());

        return new static($platform, "HTTP {$response->status()}: {$message}", $response->status());
    }
}
