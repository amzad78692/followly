<?php

namespace App\Watch;

use App\Enums\DevicePlatform;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Signs in a watch without typing a password on it: the watch shows a short code, the user
 * enters it on the website, and the watch collects its access token. Only hashes of the code
 * and the watch's secret are stored, and each attempt lasts a few minutes.
 */
class Pairing
{
    public const SECONDS = 300;

    // No 0/O, 1/I/L: the code is read off a small screen.
    protected const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * @return array{code: string, device_secret: string, expires_in: int}
     */
    public function start(string $deviceName, DevicePlatform $platform): array
    {
        $secret = Str::random(64);

        Cache::put($this->deviceKey($secret), [
            'name' => $deviceName,
            'platform' => $platform->value,
            'user_id' => null,
        ], self::SECONDS);

        return ['code' => $this->claimCode($secret), 'device_secret' => $secret, 'expires_in' => self::SECONDS];
    }

    /**
     * The device waiting on this code, for the user to check before approving it.
     *
     * @return array{name: string, platform: string}|null
     */
    public function find(string $code): ?array
    {
        $deviceKey = Cache::get($this->codeKey($code));

        return $deviceKey ? Cache::get($deviceKey) : null;
    }

    public function approve(string $code, User $user): bool
    {
        // pull(): a code works once.
        $deviceKey = Cache::pull($this->codeKey($code));
        $device = $deviceKey ? Cache::get($deviceKey) : null;

        if (! $device) {
            return false;
        }

        Cache::put($deviceKey, [...$device, 'user_id' => $user->id], self::SECONDS);

        return true;
    }

    /**
     * Null when the attempt expired, false while waiting for approval, and the access token once.
     */
    public function poll(string $secret): NewAccessToken|false|null
    {
        $key = $this->deviceKey($secret);
        $device = Cache::get($key);

        if (! $device) {
            return null;
        }

        if (! $device['user_id']) {
            return false;
        }

        Cache::forget($key);

        return User::find($device['user_id'])?->createToken($device['name'], ['watch']);
    }

    protected function claimCode(string $secret): string
    {
        do {
            $code = implode('', array_map(
                fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)],
                range(1, 6),
            ));
        } while (! Cache::add($this->codeKey($code), $this->deviceKey($secret), self::SECONDS));

        return $code;
    }

    protected function codeKey(string $code): string
    {
        return 'watch-pairing:code:'.hash('sha256', Str::upper(trim($code)));
    }

    protected function deviceKey(string $secret): string
    {
        return 'watch-pairing:device:'.hash('sha256', $secret);
    }
}
