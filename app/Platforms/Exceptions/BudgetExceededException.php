<?php

namespace App\Platforms\Exceptions;

use App\Enums\Platform;

/**
 * The monthly Apify budget is used up; no runs until next month or until the budget is raised.
 */
class BudgetExceededException extends PlatformException
{
    public static function for(Platform $platform, float $limit): static
    {
        return new static($platform, 'monthly Apify budget of $'.number_format($limit, 2).' reached');
    }
}
