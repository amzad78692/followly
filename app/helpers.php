<?php

use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;

function setting($key, $default = null)
{
    try {
        $keys = explode('.', $key);

        switch ($keys[0]) {
            case 'general':
                $class = GeneralSettings::class;
                break;
            case 'contact':
                $class = ContactSettings::class;
                break;
            default:
                return $default;
        }

        return app($class)->{$keys[1]} ?? $default;
    } catch (\Throwable $th) {
        return $default;
    }
}
