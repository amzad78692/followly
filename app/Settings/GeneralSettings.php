<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public ?string $website_name;

    public ?string $logo;

    public ?string $favicon;

    public static function group(): string
    {
        return 'general';
    }
}
