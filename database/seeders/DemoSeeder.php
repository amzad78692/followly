<?php

namespace Database\Seeders;

use App\Enums\Platform;
use App\Enums\PostType;
use App\Enums\SyncStatus;
use App\Models\Country;
use App\Models\Influencer;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\User;
use App\Notifications\NewPostNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample creators, accounts and posts for local development.
 * Safe to run more than once.
 */
class DemoSeeder extends Seeder
{
    private const CATEGORIES = [
        'Technology' => '💻',
        'Entertainment' => '🎬',
        'Sports' => '⚽',
        'Business' => '📊',
        'Travel' => '✈️',
        'Gaming' => '🎮',
    ];

    private const INFLUENCERS = [
        'mrbeast' => [
            'name' => 'MrBeast', 'real_name' => 'Jimmy Donaldson', 'avatar' => 12, 'category' => 'Entertainment',
            'country' => 'United States', 'language' => 'English',
            'bio' => 'I want to make the world a better place before I die. Creator of the most-subscribed YouTube channel.',
            'accounts' => ['youtube' => ['@MrBeast', 420_000_000], 'instagram' => ['@mrbeast', 64_000_000], 'tiktok' => ['@mrbeast', 115_000_000], 'x' => ['@MrBeast', 34_000_000]],
        ],
        'cristiano-ronaldo' => [
            'name' => 'Cristiano Ronaldo', 'real_name' => 'Cristiano Ronaldo dos Santos Aveiro', 'avatar' => 13, 'category' => 'Sports',
            'country' => 'Portugal', 'language' => 'Portuguese',
            'bio' => 'Professional footballer. Hard work, dedication and family.',
            'accounts' => ['youtube' => ['@cristiano', 75_000_000], 'instagram' => ['@cristiano', 650_000_000], 'tiktok' => ['@cristiano', 170_000_000], 'x' => ['@Cristiano', 115_000_000]],
        ],
        'noor-stars' => [
            'name' => 'Noor Stars', 'real_name' => 'Noor Stars', 'avatar' => 47, 'category' => 'Entertainment',
            'country' => 'United Arab Emirates', 'language' => 'Arabic',
            'bio' => 'Lifestyle, vlogs and everyday adventures.',
            'accounts' => ['youtube' => ['@noorstars', 45_000_000], 'instagram' => ['@noorstars', 36_000_000], 'tiktok' => ['@noorstars', 11_000_000], 'x' => ['@noorstars', 10_000_000]],
        ],
        'marques-brownlee' => [
            'name' => 'Marques Brownlee', 'real_name' => 'Marques Brownlee', 'avatar' => 33, 'category' => 'Technology',
            'country' => 'United States', 'language' => 'English',
            'bio' => 'Quality tech videos. MKBHD.',
            'accounts' => ['youtube' => ['@mkbhd', 20_000_000], 'instagram' => ['@mkbhd', 10_000_000], 'tiktok' => ['@mkbhd', 12_000_000], 'x' => ['@MKBHD', 6_000_000]],
        ],
        'lex-fridman' => [
            'name' => 'Lex Fridman', 'real_name' => 'Lex Fridman', 'avatar' => 59, 'category' => 'Technology',
            'country' => 'United States', 'language' => 'English',
            'bio' => 'Host of the Lex Fridman Podcast. Conversations about science, technology, history and power.',
            'accounts' => ['youtube' => ['@lexfridman', 4_500_000], 'instagram' => ['@lexfridman', 1_200_000], 'tiktok' => ['@lexfridman', 600_000], 'x' => ['@lexfridman', 4_000_000]],
        ],
        'ali-abdaal' => [
            'name' => 'Ali Abdaal', 'real_name' => 'Ali Abdaal', 'avatar' => 52, 'category' => 'Business',
            'country' => 'United Kingdom', 'language' => 'English',
            'bio' => 'Doctor turned entrepreneur. Productivity, business and living a happier life.',
            'accounts' => ['youtube' => ['@aliabdaal', 6_000_000], 'instagram' => ['@aliabdaal', 1_400_000], 'tiktok' => ['@aliabdaal', 800_000]],
        ],
        'ishowspeed' => [
            'name' => 'IShowSpeed', 'real_name' => 'Darren Watkins Jr.', 'avatar' => 11, 'category' => 'Entertainment',
            'country' => 'United States', 'language' => 'English',
            'bio' => 'Streamer, gamer and entertainer.',
            'accounts' => ['youtube' => ['@IShowSpeed', 38_000_000], 'instagram' => ['@ishowspeed', 30_000_000], 'tiktok' => ['@ishowspeed', 35_000_000], 'x' => ['@ishowspeedsui', 5_000_000]],
        ],
    ];

    /** [influencer, platform, hours ago, type, title, body, image seeds, duration seconds] */
    private const POSTS = [
        ['marques-brownlee', 'youtube', 2, 'video', 'This Smartphone Changed Everything!', 'A closer look at the new features and why they matter.', ['phone'], 754],
        ['mrbeast', 'youtube', 3, 'video', '$1 vs $1,000,000 Hotel!', 'We stayed in the cheapest and the most expensive hotels in the world.', ['hotel'], 1122],
        ['lex-fridman', 'youtube', 5, 'video', 'AI and the Future of Humanity', 'A fascinating conversation about where AI is heading.', ['podcast'], 8285],
        ['mrbeast', 'instagram', 5, 'image', null, 'Behind the scenes of our biggest video yet 🏝️', ['island'], null],
        ['noor-stars', 'instagram', 6, 'carousel', null, 'New vlog is live! ❤️', ['vlog1', 'vlog2', 'vlog3'], null],
        ['cristiano-ronaldo', 'instagram', 8, 'image', null, 'Hard work never stops 💪', ['training'], null],
        ['ishowspeed', 'tiktok', 12, 'video', null, 'Bro really thought he could beat me 😂', ['speed'], 45],
        ['mrbeast', 'tiktok', 24, 'video', null, 'Would you take the deal?', ['deal'], 58],
        ['mrbeast', 'x', 25, 'text', null, 'Next video is the craziest thing we have ever done. Trust me.', [], null],
        ['ali-abdaal', 'youtube', 27, 'video', 'How I Manage My Time', 'The simple system I use to get more done without burning out.', ['desk'], 980],
        ['lex-fridman', 'x', 48, 'text', null, 'Recorded a 5 hour conversation today. One of the most important ones I have done.', [], null],
    ];

    private const DEMO_FOLLOWS = ['marques-brownlee', 'noor-stars', 'cristiano-ronaldo', 'lex-fridman', 'ali-abdaal', 'ishowspeed'];

    public function run(): void
    {
        $categories = collect(self::CATEGORIES)->map(fn (string $emoji, string $name) => Taxonomy::firstOrCreate(
            ['type' => 'category', 'slug' => Str::slug($name)],
            ['name' => $name, 'meta' => ['emoji' => $emoji]],
        ));

        $countries = Country::whereIn('name', array_column(self::INFLUENCERS, 'country'))->pluck('id', 'name');

        $influencers = collect(self::INFLUENCERS)->map(function (array $data, string $slug) use ($categories, $countries) {
            $influencer = Influencer::updateOrCreate(['slug' => $slug], [
                'name' => $data['name'],
                'real_name' => $data['real_name'],
                'bio' => $data['bio'],
                'avatar_url' => "https://i.pravatar.cc/300?img={$data['avatar']}",
                'cover_url' => "https://picsum.photos/seed/cover-{$slug}/1400/400",
                'category_id' => $categories[$data['category']]->id,
                'country_id' => $countries[$data['country']] ?? null,
                'language' => $data['language'],
                'is_verified' => true,
            ]);

            foreach ($data['accounts'] as $platform => [$handle, $followers]) {
                $account = $influencer->accounts()->updateOrCreate(['platform' => $platform], [
                    'handle' => $handle,
                    'external_id' => "demo-{$platform}-".ltrim(strtolower($handle), '@'),
                    'followers' => $followers,
                    'is_verified' => true,
                    'sync_status' => SyncStatus::Synced,
                    'last_synced_at' => now(),
                ]);

                $account->snapshot();
            }

            return $influencer;
        });

        foreach (self::POSTS as $i => [$slug, $platform, $hoursAgo, $type, $title, $body, $images, $duration]) {
            $account = $influencers[$slug]->accounts()->where('platform', $platform)->first();

            $account->posts()->updateOrCreate(['platform' => Platform::from($platform), 'external_id' => "demo-post-{$i}"], [
                'influencer_id' => $account->influencer_id,
                'type' => PostType::from($type),
                'title' => $title,
                'body' => $body,
                'media' => $images ? ['images' => array_map(fn ($seed) => "https://picsum.photos/seed/flw-{$seed}/800/450", $images)] : null,
                'duration_seconds' => $duration,
                'published_at' => now()->subHours($hoursAgo),
            ]);
        }

        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password'],
        );

        foreach (self::DEMO_FOLLOWS as $slug) {
            $user->follow($influencers[$slug]);
        }

        // A notification per tracked account with posts, the newest two unread.
        if (! $user->notifications()->exists()) {
            Post::feedFor($user)->with('account.influencer')->get()
                ->groupBy('influencer_account_id')
                ->each(fn ($posts) => $user->notifyNow(new NewPostNotification($posts->first()->account, $posts)));

            $user->notifications()->skip(2)->take(PHP_INT_MAX)->get()->markAsRead();
        }
    }
}
