<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ContactSettings extends Settings
{
    public ?string $email;

    public ?string $mobile;

    public ?string $whatsapp;

    public ?string $address;

    public ?string $youtube;

    public ?string $instagram;

    public ?string $tiktok;

    public ?string $x;

    public static function group(): string
    {
        return 'contact';
    }
}
