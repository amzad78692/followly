<?php

namespace App\Watch;

use App\Enums\DevicePlatform;
use App\Models\DeviceToken;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sends one push to one device through FCM, APNs or Huawei Push. A provider without credentials
 * is skipped. Without a title and body the push is silent and only refreshes the app.
 */
class PushSender
{
    protected const OAUTH_SECONDS = 3000; // Provider tokens live for an hour.

    protected const HMS_SUCCESS = '80000000';

    protected const HMS_TOKENS_INVALID = '80300007';

    public function send(DeviceToken $device, ?string $title, ?string $body, array $data = []): void
    {
        $data = array_map('strval', $data);

        try {
            $accepted = match ($device->platform->provider()) {
                'fcm' => $this->fcm($device, $title, $body, $data),
                'apns' => $this->apns($device, $title, $body, $data),
                'hms' => $this->hms($device, $title, $body, $data),
            };

            if (! $accepted) {
                $device->delete();
            }
        } catch (Throwable $e) {
            // One failed device must not stop the rest. The watch catches up on its next refresh.
            report($e);
        }
    }

    /**
     * @return bool false when the provider says the token is no longer valid
     */
    protected function fcm(DeviceToken $device, ?string $title, ?string $body, array $data): bool
    {
        $path = config('services.push.fcm_credentials');

        if (! $path) {
            return true;
        }

        $account = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        $response = Http::timeout(10)
            ->withToken($this->fcmAccessToken($account))
            ->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", [
                'message' => array_filter([
                    'token' => $device->token,
                    'notification' => $title === null ? null : ['title' => $title, 'body' => $body],
                    'data' => $data,
                    'android' => ['priority' => 'high'],
                ]),
            ]);

        return $this->acceptedUnless($response, $response->status() === 404);
    }

    protected function fcmAccessToken(array $account): string
    {
        return Cache::remember('push:fcm-access-token', self::OAUTH_SECONDS, function () use ($account) {
            $assertion = $this->jwt(['alg' => 'RS256', 'typ' => 'JWT'], [
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $account['token_uri'],
                'iat' => now()->getTimestamp(),
                'exp' => now()->addHour()->getTimestamp(),
            ], $account['private_key']);

            return Http::timeout(10)->asForm()->post($account['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw()->json('access_token');
        });
    }

    protected function apns(DeviceToken $device, ?string $title, ?string $body, array $data): bool
    {
        $config = config('services.push.apns');

        if (! $config['key_path'] || ! $config['key_id'] || ! $config['team_id']) {
            return true;
        }

        $host = $config['sandbox'] ? 'api.sandbox.push.apple.com' : 'api.push.apple.com';
        $topic = $device->platform === DevicePlatform::AppleWatch ? $config['watch_topic'] : $config['phone_topic'];
        $alert = $title !== null;

        $response = Http::timeout(10)
            ->withOptions(['version' => 2.0]) // APNs only speaks HTTP/2.
            ->withToken($this->apnsProviderToken($config))
            ->withHeaders([
                'apns-topic' => $topic,
                'apns-push-type' => $alert ? 'alert' : 'background',
                'apns-priority' => $alert ? '10' : '5',
            ])
            ->post("https://{$host}/3/device/{$device->token}", [
                ...$data,
                'aps' => $alert
                    ? ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default']
                    : ['content-available' => 1],
            ]);

        return $this->acceptedUnless($response, $response->status() === 410 || $response->json('reason') === 'BadDeviceToken');
    }

    protected function apnsProviderToken(array $config): string
    {
        return Cache::remember('push:apns-provider-token', self::OAUTH_SECONDS, fn () => $this->jwt(
            ['alg' => 'ES256', 'kid' => $config['key_id']],
            ['iss' => $config['team_id'], 'iat' => now()->getTimestamp()],
            File::get($config['key_path']),
            rawSignature: true,
        ));
    }

    protected function hms(DeviceToken $device, ?string $title, ?string $body, array $data): bool
    {
        $appId = config('services.push.hms.app_id');
        $secret = config('services.push.hms.secret');

        if (! $appId || ! $secret) {
            return true;
        }

        $message = ['token' => [$device->token], 'data' => json_encode($data, JSON_THROW_ON_ERROR)];

        if ($title !== null) {
            $message['notification'] = ['title' => $title, 'body' => $body];
            $message['android'] = ['notification' => ['title' => $title, 'body' => $body, 'click_action' => ['type' => 3]]];
        }

        $accessToken = Cache::remember('push:hms-access-token', self::OAUTH_SECONDS, fn () => Http::timeout(10)
            ->asForm()
            ->post('https://oauth-login.cloud.huawei.com/oauth2/v3/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $appId,
                'client_secret' => $secret,
            ])->throw()->json('access_token'));

        $response = Http::timeout(10)
            ->withToken($accessToken)
            ->post("https://push-api.cloud.huawei.com/v1/{$appId}/messages:send", ['validate_only' => false, 'message' => $message])
            ->throw();

        // Huawei answers 200 and puts the outcome in the body.
        $code = $response->json('code');

        if ($code !== self::HMS_SUCCESS && $code !== self::HMS_TOKENS_INVALID) {
            throw new RuntimeException("Huawei Push refused the message with code {$code}.");
        }

        return $code === self::HMS_SUCCESS;
    }

    protected function acceptedUnless(Response $response, bool $tokenInvalid): bool
    {
        if ($tokenInvalid) {
            return false;
        }

        $response->throw();

        return true;
    }

    protected function jwt(array $header, array $claims, string $privateKey, bool $rawSignature = false): string
    {
        $input = $this->base64Url(json_encode($header, JSON_THROW_ON_ERROR)).'.'.$this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR));

        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the push provider token.');
        }

        return $input.'.'.$this->base64Url($rawSignature ? $this->derToRaw($signature) : $signature);
    }

    /**
     * OpenSSL gives ES256 signatures in DER form, but a JWT needs R and S as 32 raw bytes each.
     */
    protected function derToRaw(string $der): string
    {
        $rLength = ord($der[3]);
        $r = substr($der, 4, $rLength);
        $s = substr($der, 6 + $rLength, ord($der[5 + $rLength]));

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT).str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
