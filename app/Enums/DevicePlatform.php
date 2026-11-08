<?php

namespace App\Enums;

enum DevicePlatform: string
{
    case WearOs = 'wear_os';
    case AppleWatch = 'apple_watch';
    case Android = 'android';
    case AndroidHms = 'android_hms'; // Huawei phones without Google services
    case Ios = 'ios';

    public function isWatch(): bool
    {
        return in_array($this, [self::WearOs, self::AppleWatch], true);
    }

    public function provider(): string
    {
        return match ($this) {
            self::WearOs, self::Android => 'fcm',
            self::AppleWatch, self::Ios => 'apns',
            self::AndroidHms => 'hms',
        };
    }
}
