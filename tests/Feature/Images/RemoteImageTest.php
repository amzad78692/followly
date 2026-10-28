<?php

use App\Support\RemoteImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

const REMOTE_AVATAR = 'https://cdn.followly-test.invalid/avatar.jpg';

function pngBytes(int $width = 40, int $height = 20): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

beforeEach(function () {
    config(['images.path' => storage_path('framework/testing/image-cache')]);
    File::deleteDirectory(config('images.path'));
});

afterEach(function () {
    File::deleteDirectory(config('images.path'));
});

it('signs remote URLs and leaves local and empty ones alone', function () {
    expect(image_url(REMOTE_AVATAR, 80, 80))->toStartWith(route('image.remote').'?')->toContain('signature=')
        ->and(image_url(url('storage/1/avatar.jpg'), 80))->toBe(url('storage/1/avatar.jpg'))
        ->and(image_url('/storage/1/avatar.jpg', 80))->toBe('/storage/1/avatar.jpg')
        ->and(image_url(null, 80))->toBeNull();

    config(['images.proxy' => false]);

    expect(image_url(REMOTE_AVATAR, 80))->toBe(REMOTE_AVATAR);
});

it('downloads once, resizes and serves from disk', function () {
    Http::fake([REMOTE_AVATAR => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $url = image_url(REMOTE_AVATAR, 30, 30);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get($url)->assertOk();
    $this->get(image_url(REMOTE_AVATAR, 10))->assertOk();

    Http::assertSentCount(1);
    [$width, $height] = getimagesize(RemoteImage::resized(REMOTE_AVATAR, 30, 30));
    expect([$width, $height])->toBe([30, 30]);
});

it('sends the browser to the original when the download fails, and waits before retrying', function () {
    Http::fake([REMOTE_AVATAR => Http::response('gone', 404)]);

    $this->get(image_url(REMOTE_AVATAR, 80))->assertRedirect(REMOTE_AVATAR);
    $this->get(image_url(REMOTE_AVATAR, 80))->assertRedirect(REMOTE_AVATAR);

    Http::assertSentCount(1);
});

it('refuses anything that is not an image', function () {
    Http::fake([REMOTE_AVATAR => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

    $this->get(image_url(REMOTE_AVATAR, 80))->assertRedirect(REMOTE_AVATAR);
});

it('rejects unsigned requests', function () {
    $this->get(route('image.remote', ['w' => 80, 'url' => REMOTE_AVATAR]))->assertForbidden();
});

it('never fetches private addresses', function () {
    Http::fake();

    $this->get(image_url('http://127.0.0.1/secret.png', 80))->assertRedirect('http://127.0.0.1/secret.png');

    Http::assertNothingSent();
});

it('prunes images nobody has requested lately', function () {
    Http::fake([REMOTE_AVATAR => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);
    RemoteImage::resized(REMOTE_AVATAR, 20);

    expect(RemoteImage::prune())->toBe(0);

    $folder = File::directories(config('images.path'))[0];
    touch($folder, now()->subDays(config('images.keep_days') + 1)->getTimestamp());

    $this->artisan('images:prune')->assertSuccessful();
    expect(File::directories(config('images.path')))->toBeEmpty();
});
