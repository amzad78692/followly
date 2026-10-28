<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How often a user gets the email digest of new posts.
 */
enum DigestFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
        };
    }

    /**
     * Start of the period a digest covers when the user has never had one.
     */
    public function since(): CarbonInterface
    {
        return match ($this) {
            self::Daily => now()->subDay(),
            self::Weekly => now()->subWeek(),
        };
    }
}
