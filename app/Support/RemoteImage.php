<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Spatie\Glide\GlideImage;
use Throwable;

/**
 * Serves platform images (avatars, covers, thumbnails) from our own server: each one is
 * downloaded once, resized with Glide to the size the page shows and kept on disk, so a
 * slow platform CDN only costs the first visitor.
 */
class RemoteImage
{
    /**
     * A signed image.remote URL for a remote image, or the URL unchanged when it is local,
     * empty or the proxy is off. With a height the image is cropped to fill the box.
     */
    public static function url(?string $url, int $width, ?int $height = null): ?string
    {
        if (! $url || ! config('images.proxy') || ! static::isRemote($url)) {
            return $url;
        }

        return URL::signedRoute('image.remote', array_filter(['w' => $width, 'h' => $height, 'url' => $url]));
    }

    public static function isRemote(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && $host
            && $host !== parse_url(config('app.url'), PHP_URL_HOST);
    }

    /**
     * Path of the resized image on disk, downloading the original first if needed.
     * Null when the image can't be fetched or decoded.
     */
    public static function resized(string $url, int $width, ?int $height = null): ?string
    {
        $folder = static::folder($url);
        $file = $folder.'/'.$width.'x'.($height ?? 'auto').'.webp';

        if (is_file($file)) {
            touch($folder);

            return $file;
        }

        // Glide's temp files collide when one image is resized twice at once (a page shows
        // the same avatar in several places), so one request at a time per image.
        try {
            return Cache::lock('remote-image:'.sha1($url), 30)->block(15, fn () => static::generate($url, $file, $width, $height));
        } catch (LockTimeoutException) {
            return null;
        }
    }

    protected static function generate(string $url, string $file, int $width, ?int $height): ?string
    {
        if (is_file($file)) {
            return $file;
        }

        $original = static::download($url);

        if (! $original) {
            return null;
        }

        try {
            GlideImage::create($original)
                ->modify(array_filter([
                    'w' => $width,
                    'h' => $height,
                    'fit' => $height ? 'crop' : 'max',
                    'fm' => 'webp',
                    'q' => 80,
                ]))
                ->save($file);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $file;
    }

    /**
     * Remove images that nobody has requested for config('images.keep_days') days.
     */
    public static function prune(): int
    {
        $root = config('images.path');
        $cutoff = now()->subDays(config('images.keep_days'))->getTimestamp();
        $removed = 0;

        foreach (File::isDirectory($root) ? File::directories($root) : [] as $folder) {
            if (filemtime($folder) < $cutoff) {
                File::deleteDirectory($folder);
                $removed++;
            }
        }

        return $removed;
    }

    protected static function folder(string $url): string
    {
        return config('images.path').'/'.sha1($url);
    }

    protected static function download(string $url): ?string
    {
        // Glide names its temp file after the source's basename and the size, so each
        // original needs a unique name or two images resized to one size could swap.
        $folder = static::folder($url);
        $original = $folder.'/'.sha1($url);

        if (is_file($original)) {
            return $original;
        }

        $failedKey = 'remote-image-failed:'.sha1($url);

        if (Cache::has($failedKey) || ! static::isPublicHost(parse_url($url, PHP_URL_HOST))) {
            return null;
        }

        try {
            $response = Http::timeout(config('images.timeout'))
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url);
        } catch (ConnectionException) {
            $response = null;
        }

        if (! $response?->successful()
            || ! str_starts_with((string) $response->header('Content-Type'), 'image/')
            || strlen($response->body()) > config('images.max_bytes')) {
            Cache::put($failedKey, true, now()->addMinutes(config('images.failure_minutes')));

            return null;
        }

        File::ensureDirectoryExists($folder);
        File::put($original, $response->body());

        return $original;
    }

    /**
     * Refuse hosts that resolve to private or reserved addresses. A host that doesn't
     * resolve is let through: the download fails on its own.
     */
    protected static function isPublicHost(string $host): bool
    {
        $ip = gethostbyname($host);

        return ! filter_var($ip, FILTER_VALIDATE_IP)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
