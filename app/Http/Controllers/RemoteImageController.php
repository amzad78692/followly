<?php

namespace App\Http\Controllers;

use App\Support\RemoteImage;
use Illuminate\Http\Request;

class RemoteImageController extends Controller
{
    /**
     * A platform image resized and served from disk (see App\Support\RemoteImage). When it
     * can't be fetched the browser is sent to the platform's own URL.
     */
    public function __invoke(Request $request)
    {
        $url = $request->query('url');
        $width = $request->integer('w');
        $height = $request->integer('h') ?: null;

        abort_unless(is_string($url) && RemoteImage::isRemote($url) && $width > 0 && $width <= 2000 && ($height ?? 1) <= 2000, 404);

        $path = RemoteImage::resized($url, $width, $height);

        if (! $path) {
            return redirect()->away($url);
        }

        return response()->file($path, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=2592000, immutable',
        ]);
    }
}
