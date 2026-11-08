<?php

use App\Enums\Platform;
use App\Events\PostsSynced;
use App\Jobs\SendPush;
use App\Models\DeviceToken;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Watch\PushSender;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->influencer = Influencer::factory()->create(['name' => 'Poster']);
    $this->account = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::YouTube]);

    $this->travelTo(now()->subDay());
    $this->user->follow($this->influencer);
    $this->travelBack();
});

function addDevice(User $user, string $platform, string $token = 'device-token', ?string $timezone = null): DeviceToken
{
    return DeviceToken::create([
        'user_id' => $user->id,
        'personal_access_token_id' => $user->createToken('Device', ['watch'])->accessToken->id,
        'platform' => $platform,
        'token' => $token,
        'timezone' => $timezone,
        'last_seen_at' => now(),
    ]);
}

function syncFresh(InfluencerAccount $account, int $posts = 1): void
{
    PostsSynced::dispatch($account, new Collection(Post::factory()->count($posts)->for($account, 'account')->create([
        'title' => 'A video',
        'published_at' => now()->subHour(),
    ])->all()));
}

/**
 * @return array{0: string, 1: string} private and public key in PEM form
 */
function makeKey(array $options): array
{
    // PHP on Windows may have no openssl.cnf, and key generation needs one.
    $config = tempnam(sys_get_temp_dir(), 'ssl');
    file_put_contents($config, "[req]\ndefault_bits=2048\ndistinguished_name=dn\n[dn]\n");
    $options['config'] = $config;

    $key = openssl_pkey_new($options);
    openssl_pkey_export($key, $pem, null, $options);

    return [$pem, openssl_pkey_get_details($key)['key']];
}

function makeRsaKey(): string
{
    return makeKey(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA])[0];
}

function useFcm(): void
{
    $pem = makeRsaKey();

    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode([
        'project_id' => 'followly',
        'client_email' => 'push@followly.iam.gserviceaccount.com',
        'private_key' => $pem,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));

    config(['services.push.fcm_credentials' => $path]);
}

function fakeFcm(int $status = 200): void
{
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
        'fcm.googleapis.com/*' => Http::response([], $status),
    ]);
}

it('queues an alert with a link to the stored update', function () {
    Queue::fake([SendPush::class]);
    addDevice($this->user, 'wear_os');

    syncFresh($this->account);

    $id = $this->user->notifications()->first()->id;

    Queue::assertPushed(SendPush::class, fn (SendPush $job) => $job->userId === $this->user->id
        && $job->title === 'Poster'
        && $job->body === 'A video'
        && $job->data === ['type' => 'new-post', 'notification_id' => $id, 'creator_slug' => $this->influencer->slug]);
});

it('summarises several posts from one sync', function () {
    Queue::fake([SendPush::class]);
    addDevice($this->user, 'wear_os');

    syncFresh($this->account, 3);

    Queue::assertPushed(SendPush::class, fn (SendPush $job) => $job->body === '3 new posts');
});

it('does not queue anything for users without devices', function () {
    Queue::fake([SendPush::class]);

    syncFresh($this->account);

    Queue::assertNotPushed(SendPush::class);
});

it('keeps the feed row and sends a silent refresh when a creator alert is off', function () {
    Queue::fake([SendPush::class]);
    addDevice($this->user, 'wear_os');
    $this->user->follows()->update(['notify' => false]);

    syncFresh($this->account);

    expect($this->user->notifications()->count())->toBe(1);
    Queue::assertPushed(SendPush::class, fn (SendPush $job) => $job->title === null && $job->body === null);
});

it('sends a silent refresh when instant updates are off', function () {
    Queue::fake([SendPush::class]);
    addDevice($this->user, 'wear_os');
    $this->user->update(['notify_push' => false]);

    syncFresh($this->account);

    Queue::assertPushed(SendPush::class, fn (SendPush $job) => $job->title === null);
});

it('does not push posts from a muted platform', function () {
    Queue::fake([SendPush::class]);
    addDevice($this->user, 'wear_os');
    $this->user->update(['muted_platforms' => [Platform::YouTube]]);

    syncFresh($this->account);

    Queue::assertNotPushed(SendPush::class);
});

it('still collects updates for a push-only user with a device', function () {
    Queue::fake([SendPush::class]);
    $this->user->update(['notify_in_app' => false]);
    addDevice($this->user, 'wear_os');

    syncFresh($this->account);

    expect($this->user->notifications()->count())->toBe(1);
});

it('sends through FCM with string data', function () {
    useFcm();
    fakeFcm();
    addDevice($this->user, 'wear_os');
    syncFresh($this->account);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/followly/messages:send'
        && $request->hasHeader('Authorization', 'Bearer google-token')
        && $request['message']['token'] === 'device-token'
        && $request['message']['notification'] === ['title' => 'Poster', 'body' => 'A video']
        && $request['message']['data']['unread_count'] === '1');
});

it('signs the Google token request with the service account key', function () {
    useFcm();
    fakeFcm();
    addDevice($this->user, 'wear_os');
    syncFresh($this->account);

    $account = json_decode(file_get_contents(config('services.push.fcm_credentials')), true);
    $public = openssl_pkey_get_details(openssl_pkey_get_private($account['private_key']))['key'];

    Http::assertSent(function (Request $request) use ($public) {
        if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
            return false;
        }

        [$header, $claims, $signature] = explode('.', $request['assertion']);

        return openssl_verify("{$header}.{$claims}", base64_decode(strtr($signature, '-_', '+/')), $public, OPENSSL_ALGO_SHA256) === 1;
    });
});

it('sends a silent push without a notification block', function () {
    useFcm();
    fakeFcm();
    addDevice($this->user, 'wear_os');
    $this->user->update(['notify_push' => false]);

    syncFresh($this->account);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'messages:send')
        && ! isset($request['message']['notification']));
});

it('removes a token FCM no longer recognises', function () {
    useFcm();
    fakeFcm(404);
    addDevice($this->user, 'wear_os');

    syncFresh($this->account);

    expect(DeviceToken::count())->toBe(0);
});

it('keeps the token and carries on when a provider fails', function () {
    useFcm();
    fakeFcm(500);
    addDevice($this->user, 'wear_os', 'one');
    addDevice($this->user, 'wear_os', 'two');

    syncFresh($this->account);

    expect(DeviceToken::count())->toBe(2);
    Http::assertSentCount(3); // one Google token request, one send per device
});

it('skips a provider without credentials', function () {
    Http::fake();
    addDevice($this->user, 'wear_os');
    addDevice($this->user, 'apple_watch', 'apple');

    syncFresh($this->account);

    Http::assertNothingSent();
    expect(DeviceToken::count())->toBe(2);
});

it('sends to APNs with a valid ES256 token and the right topic', function () {
    [$pem, $public] = makeKey(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

    $path = tempnam(sys_get_temp_dir(), 'apns');
    file_put_contents($path, $pem);

    config(['services.push.apns' => [
        'key_path' => $path,
        'key_id' => 'KEY123',
        'team_id' => 'TEAM123',
        'sandbox' => true,
        'watch_topic' => 'com.followly.watch',
        'phone_topic' => 'com.followly.companion',
    ]]);

    Http::fake(['api.sandbox.push.apple.com/*' => Http::response()]);
    addDevice($this->user, 'apple_watch', 'apple-token');

    syncFresh($this->account);

    Http::assertSent(function (Request $request) use ($public) {
        [$header, $claims, $signature] = explode('.', str_replace('Bearer ', '', $request->header('Authorization')[0]));

        $raw = base64_decode(strtr($signature, '-_', '+/'));
        $integer = function (string $bytes) {
            $bytes = ltrim($bytes, "\0");
            $bytes = ord($bytes[0]) > 127 ? "\0".$bytes : $bytes;

            return "\x02".chr(strlen($bytes)).$bytes;
        };
        $body = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32));

        return $request->url() === 'https://api.sandbox.push.apple.com/3/device/apple-token'
            && strlen($raw) === 64
            && openssl_verify("{$header}.{$claims}", "\x30".chr(strlen($body)).$body, $public, OPENSSL_ALGO_SHA256) === 1
            && $request->hasHeader('apns-topic', 'com.followly.watch')
            && $request->hasHeader('apns-push-type', 'alert')
            && $request['aps']['alert'] === ['title' => 'Poster', 'body' => 'A video'];
    });
});

it('removes a token APNs reports as unregistered', function () {
    $path = tempnam(sys_get_temp_dir(), 'apns');
    file_put_contents($path, makeRsaKey());

    config(['services.push.apns.key_path' => $path, 'services.push.apns.key_id' => 'K', 'services.push.apns.team_id' => 'T']);
    Http::fake(['api.push.apple.com/*' => Http::response(['reason' => 'Unregistered'], 410)]);
    $device = addDevice($this->user, 'ios');

    app(PushSender::class)->send($device, 'Title', 'Body');

    expect(DeviceToken::count())->toBe(0);
});

it('sends through Huawei Push and removes rejected tokens', function () {
    config(['services.push.hms' => ['app_id' => 'app', 'secret' => 'secret']]);

    Http::fake([
        'oauth-login.cloud.huawei.com/*' => Http::response(['access_token' => 'huawei-token']),
        'push-api.cloud.huawei.com/*' => Http::sequence()
            ->push(['code' => '80000000'])
            ->push(['code' => '80300007']),
    ]);

    $device = addDevice($this->user, 'android_hms');

    app(PushSender::class)->send($device, 'Title', 'Body', ['type' => 'new-post']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://push-api.cloud.huawei.com/v1/app/messages:send'
        && $request['message']['token'] === ['device-token']
        && $request['message']['android']['notification']['click_action'] === ['type' => 3]
        && json_decode($request['message']['data'], true) === ['type' => 'new-post']);

    expect(DeviceToken::count())->toBe(1);

    app(PushSender::class)->send($device->fresh(), 'Title', 'Body');

    expect(DeviceToken::count())->toBe(0);
});

it('sends alerts to the watch only when a user has a watch and a phone', function () {
    useFcm();
    fakeFcm();
    addDevice($this->user, 'wear_os', 'watch');
    addDevice($this->user, 'android', 'phone');

    syncFresh($this->account);

    Http::assertSent(fn (Request $request) => isset($request['message']) && $request['message']['token'] === 'watch');
    Http::assertNotSent(fn (Request $request) => isset($request['message']) && $request['message']['token'] === 'phone');
});

it('sends alerts to the phone companion when there is no watch app', function () {
    useFcm();
    fakeFcm();
    addDevice($this->user, 'android', 'phone');

    syncFresh($this->account);

    Http::assertSent(fn (Request $request) => isset($request['message']) && $request['message']['token'] === 'phone');
});

it('sends no silent refresh to a phone', function () {
    useFcm();
    fakeFcm();
    addDevice($this->user, 'android', 'phone');
    $this->user->update(['notify_push' => false]);

    syncFresh($this->account);

    Http::assertNotSent(fn (Request $request) => isset($request['message']));
});

it('pushes the daily summary only in the local evening and only with news', function () {
    Queue::fake([SendPush::class]);
    $this->user->update(['push_daily_summary' => true]);
    addDevice($this->user, 'wear_os', 'kuwait', 'Asia/Kuwait');

    $this->user->notifyNow(new NewPostNotification($this->account, new Collection([
        Post::factory()->for($this->account, 'account')->create(['title' => 'A video']),
    ])));

    $quiet = User::factory()->create(['push_daily_summary' => true]);
    addDevice($quiet, 'wear_os', 'quiet', 'Asia/Kuwait');

    $off = User::factory()->create();
    addDevice($off, 'wear_os', 'off', 'Asia/Kuwait');

    $this->travelTo(now('Asia/Kuwait')->setTime(19, 0));
    $this->artisan('notifications:daily-summary')->assertSuccessful();
    Queue::assertNothingPushed();

    $this->user->notifications()->update(['created_at' => now('Asia/Kuwait')->setTime(10, 0)->setTimezone(config('app.timezone'))]);

    $this->travelTo(now('Asia/Kuwait')->setTime(20, 0));
    $this->artisan('notifications:daily-summary')->assertSuccessful();

    Queue::assertPushed(SendPush::class, 1);
    Queue::assertPushed(SendPush::class, fn (SendPush $job) => $job->userId === $this->user->id
        && $job->title === "Today's update"
        && $job->body === '1 new post from 1 creator'
        && $job->data === ['type' => 'daily-summary']);
});
