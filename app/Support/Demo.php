<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Static demo data used by the front-end pages until real models exist.
 * Follow state is kept in the session so the UI behaves end-to-end.
 */
class Demo
{
    public const PLATFORMS = [
        'youtube' => ['label' => 'YouTube', 'unit' => 'Subscribers'],
        'instagram' => ['label' => 'Instagram', 'unit' => 'Followers'],
        'tiktok' => ['label' => 'TikTok', 'unit' => 'Followers'],
        'x' => ['label' => 'X', 'unit' => 'Followers'],
    ];

    public const CATEGORIES = [
        ['name' => 'Technology', 'emoji' => '💻', 'bg' => 'bg-sky-50'],
        ['name' => 'Entertainment', 'emoji' => '🎬', 'bg' => 'bg-rose-50'],
        ['name' => 'Sports', 'emoji' => '⚽', 'bg' => 'bg-emerald-50'],
        ['name' => 'Business', 'emoji' => '📊', 'bg' => 'bg-indigo-50'],
        ['name' => 'Travel', 'emoji' => '✈️', 'bg' => 'bg-amber-50'],
        ['name' => 'Gaming', 'emoji' => '🎮', 'bg' => 'bg-violet-50'],
    ];

    private const DEFAULT_FOLLOWED = [
        'marques-brownlee', 'noor-stars', 'cristiano-ronaldo', 'lex-fridman', 'ali-abdaal', 'ishowspeed',
    ];

    public static function influencers(): Collection
    {
        return collect([
            [
                'slug' => 'mrbeast', 'name' => 'MrBeast', 'real_name' => 'Jimmy Donaldson', 'avatar' => 12,
                'category' => 'Entertainment', 'country' => 'United States', 'language' => 'English',
                'tracking' => '125K', 'combined' => '569M',
                'bio' => 'I want to make the world a better place before I die. Creator of the most-subscribed YouTube channel.',
                'platforms' => [
                    'youtube' => ['handle' => '@MrBeast', 'count' => '420M'],
                    'instagram' => ['handle' => '@mrbeast', 'count' => '64M'],
                    'tiktok' => ['handle' => '@mrbeast', 'count' => '115M'],
                    'x' => ['handle' => '@MrBeast', 'count' => '34M'],
                ],
            ],
            [
                'slug' => 'cristiano-ronaldo', 'name' => 'Cristiano Ronaldo', 'real_name' => 'Cristiano Ronaldo dos Santos Aveiro', 'avatar' => 13,
                'category' => 'Sports', 'country' => 'Portugal', 'language' => 'Portuguese',
                'tracking' => '72K', 'combined' => '1B',
                'bio' => 'Professional footballer. Hard work, dedication and family.',
                'platforms' => [
                    'youtube' => ['handle' => '@cristiano', 'count' => '75M'],
                    'instagram' => ['handle' => '@cristiano', 'count' => '650M'],
                    'tiktok' => ['handle' => '@cristiano', 'count' => '170M'],
                    'x' => ['handle' => '@Cristiano', 'count' => '115M'],
                ],
            ],
            [
                'slug' => 'noor-stars', 'name' => 'Noor Stars', 'real_name' => 'Noor Stars', 'avatar' => 47,
                'category' => 'Entertainment', 'country' => 'United Arab Emirates', 'language' => 'Arabic',
                'tracking' => '72K', 'combined' => '102M',
                'bio' => 'Lifestyle, vlogs and everyday adventures.',
                'platforms' => [
                    'youtube' => ['handle' => '@noorstars', 'count' => '45M'],
                    'instagram' => ['handle' => '@noorstars', 'count' => '36M'],
                    'tiktok' => ['handle' => '@noorstars', 'count' => '11M'],
                    'x' => ['handle' => '@noorstars', 'count' => '10M'],
                ],
            ],
            [
                'slug' => 'marques-brownlee', 'name' => 'Marques Brownlee', 'real_name' => 'Marques Brownlee', 'avatar' => 33,
                'category' => 'Technology', 'country' => 'United States', 'language' => 'English',
                'tracking' => '72K', 'combined' => '48M',
                'bio' => 'Quality tech videos. MKBHD.',
                'platforms' => [
                    'youtube' => ['handle' => '@mkbhd', 'count' => '20M'],
                    'instagram' => ['handle' => '@mkbhd', 'count' => '10M'],
                    'tiktok' => ['handle' => '@mkbhd', 'count' => '12M'],
                    'x' => ['handle' => '@MKBHD', 'count' => '6M'],
                ],
            ],
            [
                'slug' => 'lex-fridman', 'name' => 'Lex Fridman', 'real_name' => 'Lex Fridman', 'avatar' => 59,
                'category' => 'Technology', 'country' => 'United States', 'language' => 'English',
                'tracking' => '41K', 'combined' => '10M',
                'bio' => 'Host of the Lex Fridman Podcast. Conversations about science, technology, history and power.',
                'platforms' => [
                    'youtube' => ['handle' => '@lexfridman', 'count' => '4.5M'],
                    'instagram' => ['handle' => '@lexfridman', 'count' => '1.2M'],
                    'tiktok' => ['handle' => '@lexfridman', 'count' => '600K'],
                    'x' => ['handle' => '@lexfridman', 'count' => '4M'],
                ],
            ],
            [
                'slug' => 'ali-abdaal', 'name' => 'Ali Abdaal', 'real_name' => 'Ali Abdaal', 'avatar' => 52,
                'category' => 'Business', 'country' => 'United Kingdom', 'language' => 'English',
                'tracking' => '28K', 'combined' => '8M',
                'bio' => 'Doctor turned entrepreneur. Productivity, business and living a happier life.',
                'platforms' => [
                    'youtube' => ['handle' => '@aliabdaal', 'count' => '6M'],
                    'instagram' => ['handle' => '@aliabdaal', 'count' => '1.4M'],
                    'tiktok' => ['handle' => '@aliabdaal', 'count' => '800K'],
                ],
            ],
            [
                'slug' => 'ishowspeed', 'name' => 'IShowSpeed', 'real_name' => 'Darren Watkins Jr.', 'avatar' => 11,
                'category' => 'Entertainment', 'country' => 'United States', 'language' => 'English',
                'tracking' => '64K', 'combined' => '108M',
                'bio' => 'Streamer, gamer and entertainer.',
                'platforms' => [
                    'youtube' => ['handle' => '@IShowSpeed', 'count' => '38M'],
                    'instagram' => ['handle' => '@ishowspeed', 'count' => '30M'],
                    'tiktok' => ['handle' => '@ishowspeed', 'count' => '35M'],
                    'x' => ['handle' => '@ishowspeedsui', 'count' => '5M'],
                ],
            ],
        ])->map(fn (array $i) => $i + [
            'avatar_url' => "https://i.pravatar.cc/300?img={$i['avatar']}",
            'followed' => in_array($i['slug'], self::followed()),
        ]);
    }

    public static function find(string $slug): ?array
    {
        return self::influencers()->firstWhere('slug', $slug);
    }

    /**
     * Find an influencer from a pasted profile link or a plain username.
     */
    public static function lookup(string $query): ?array
    {
        $needle = str($query)->lower()->replaceMatches('#^https?://(www\.)?[^/]+/#', '')->trim('/@ ')->before('/')->before('?')->toString();

        if ($needle === '') {
            return null;
        }

        return self::influencers()->first(function (array $i) use ($needle) {
            if (str_contains(str($i['name'])->lower()->replace(' ', ''), str_replace(' ', '', $needle))) {
                return true;
            }

            return collect($i['platforms'])->contains(fn ($p) => ltrim(strtolower($p['handle']), '@') === $needle);
        });
    }

    public static function posts(): Collection
    {
        $now = Carbon::now();

        return collect([
            ['influencer' => 'marques-brownlee', 'platform' => 'youtube', 'posted_at' => $now->copy()->subHours(2), 'title' => 'This Smartphone Changed Everything!', 'body' => 'A closer look at the new features and why they matter.', 'media' => ['phone'], 'duration' => '12:34'],
            ['influencer' => 'mrbeast', 'platform' => 'youtube', 'posted_at' => $now->copy()->subHours(3), 'title' => '$1 vs $1,000,000 Hotel!', 'body' => 'We stayed in the cheapest and the most expensive hotels in the world.', 'media' => ['hotel'], 'duration' => '18:42'],
            ['influencer' => 'lex-fridman', 'platform' => 'youtube', 'label' => 'Podcast (YouTube)', 'posted_at' => $now->copy()->subHours(5), 'title' => 'AI and the Future of Humanity', 'body' => 'A fascinating conversation about where AI is heading.', 'media' => ['podcast'], 'duration' => '2:18:05'],
            ['influencer' => 'mrbeast', 'platform' => 'instagram', 'posted_at' => $now->copy()->subHours(5), 'title' => null, 'body' => 'Behind the scenes of our biggest video yet 🏝️', 'media' => ['island']],
            ['influencer' => 'noor-stars', 'platform' => 'instagram', 'posted_at' => $now->copy()->subHours(6), 'title' => null, 'body' => 'New vlog is live! ❤️', 'media' => ['vlog1', 'vlog2', 'vlog3']],
            ['influencer' => 'cristiano-ronaldo', 'platform' => 'instagram', 'posted_at' => $now->copy()->subHours(8), 'title' => null, 'body' => 'Hard work never stops 💪', 'media' => ['training']],
            ['influencer' => 'ishowspeed', 'platform' => 'tiktok', 'posted_at' => $now->copy()->subHours(12), 'title' => null, 'body' => 'Bro really thought he could beat me 😂', 'media' => ['speed'], 'duration' => '0:45'],
            ['influencer' => 'mrbeast', 'platform' => 'tiktok', 'posted_at' => $now->copy()->subDay(), 'title' => null, 'body' => 'Would you take the deal?', 'media' => ['deal'], 'duration' => '0:58'],
            ['influencer' => 'mrbeast', 'platform' => 'x', 'posted_at' => $now->copy()->subDay()->subHour(), 'title' => null, 'body' => 'Next video is the craziest thing we have ever done. Trust me.', 'media' => []],
            ['influencer' => 'ali-abdaal', 'platform' => 'youtube', 'posted_at' => $now->copy()->subDay()->subHours(3), 'title' => 'How I Manage My Time', 'body' => 'The simple system I use to get more done without burning out.', 'media' => ['desk'], 'duration' => '16:20'],
            ['influencer' => 'lex-fridman', 'platform' => 'x', 'posted_at' => $now->copy()->subDays(2), 'title' => null, 'body' => 'Recorded a 5 hour conversation today. One of the most important ones I have done.', 'media' => []],
        ])->map(function (array $post, int $index) {
            $post['id'] = $index + 1;
            $post['author'] = self::find($post['influencer']);
            $post['label'] ??= self::PLATFORMS[$post['platform']]['label'];
            $post['images'] = array_map(fn ($seed) => "https://picsum.photos/seed/flw-{$seed}/800/450", $post['media']);

            return $post;
        });
    }

    public static function followed(): array
    {
        return session('demo.followed', self::DEFAULT_FOLLOWED);
    }

    public static function follow(string $slug): void
    {
        session(['demo.followed' => array_values(array_unique([...self::followed(), $slug]))]);
    }

    public static function unfollow(string $slug): void
    {
        session(['demo.followed' => array_values(array_diff(self::followed(), [$slug]))]);
    }

    public static function toggle(string $slug): void
    {
        in_array($slug, self::followed()) ? self::unfollow($slug) : self::follow($slug);
    }
}
