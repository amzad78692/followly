<?php

namespace App\Platforms\Exceptions;

use App\Enums\Platform;

class AccountNotFoundException extends PlatformException
{
    public static function for(Platform $platform, string $idOrHandle): static
    {
        return new static($platform, "account {$idOrHandle} was not found or is private");
    }
}
