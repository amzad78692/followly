<?php

namespace App\Traits;

trait EnumToArray
{
    public static function toArray(): array
    {
        return array_column(self::cases(), 'name', 'value');
    }

    /**
     * Get the enum cases as an associative array (value => name).
     *
     * @return array<string|int, string>
     */
    public static function toFlippedArray(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }
}
