<?php

use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Support\RemoteImage;

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
    } catch (Throwable $th) {
        return $default;
    }
}

/**
 * A remote image resized to $width (and cropped to $height) and served from our server.
 * See App\Support\RemoteImage.
 */
function image_url(?string $url, int $width, ?int $height = null): ?string
{
    return RemoteImage::url($url, $width, $height);
}
