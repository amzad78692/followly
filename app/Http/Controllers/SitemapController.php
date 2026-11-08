<?php

namespace App\Http\Controllers;

use App\Models\Influencer;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * The landing and static pages and every public profile, cached for an hour. A sitemap holds at most
     * 50,000 URLs, so past that it would need splitting into an index.
     */
    public function __invoke()
    {
        $xml = Cache::remember('sitemap', now()->addHour(), function () {
            $urls = collect(['home', 'privacy', 'terms', 'data-deletion', 'contact'])->map(fn ($name) => ['loc' => route($name), 'lastmod' => null])->all();

            foreach (Influencer::query()->select(['slug', 'updated_at'])->orderBy('id')->limit(49_999)->cursor() as $influencer) {
                $urls[] = ['loc' => route('influencers.show', $influencer), 'lastmod' => $influencer->updated_at?->toAtomString()];
            }

            return view('sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
