<?php

use App\Livewire\Concerns\TogglesFollow;
use App\Models\Influencer;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    use TogglesFollow;

    public const TABS = [
        'trending' => 'Trending',
        'category' => 'By Category',
        'country' => 'By Country',
        'language' => 'By Language',
        'recommended' => 'Recommended',
    ];

    public const FEATURES = [
        ['icon' => 'squares-2x2', 'title' => 'All Platforms, One Feed'],
        ['icon' => 'user-group', 'title' => 'Unlimited Influencers'],
        ['icon' => 'tag', 'title' => 'Automatic Categories'],
        ['icon' => 'sparkles', 'title' => 'Discover New Creators'],
    ];

    public const STATS = [
        ['value' => '1,000+', 'label' => 'Influencers'],
        ['value' => 'Multiple', 'label' => 'Social Platforms'],
        ['value' => 'Global', 'label' => 'Creators'],
        ['value' => '100%', 'label' => 'Free to Use'],
    ];

    public const STEPS = [
        ['icon' => 'user-plus', 'title' => 'Add Influencers', 'text' => 'Search and pick your favorite creators.'],
        ['icon' => 'bell-alert', 'title' => 'We Track Updates', 'text' => 'New posts are detected automatically.'],
        ['icon' => 'squares-2x2', 'title' => 'Get One Feed', 'text' => 'See everything neatly personalized.'],
        ['icon' => 'signal', 'title' => 'Stay Informed', 'text' => 'Never miss your favorite content again.'],
    ];

    // The sections below are sample content that shows off the product, not database records.
    public const FEED_NAV = [
        ['icon' => 'home', 'label' => 'My Feed'],
        ['icon' => 'magnifying-glass', 'label' => 'Explore'],
        ['icon' => 'users', 'label' => 'My Influencers'],
        ['icon' => 'bell', 'label' => 'Notifications'],
        ['icon' => 'cog-6-tooth', 'label' => 'Settings'],
    ];

    public const FEED_POSTS = [
        ['name' => 'Marques Brownlee', 'platform' => 'youtube', 'time' => '2 hours ago', 'title' => 'This Smartphone Changed Everything!', 'body' => 'After weeks of testing, here\'s why this device made me question every other phone on the market.', 'stats' => '12K likes · 342 comments · 210 shares', 'thumb' => 'from-terracotta to-gold'],
        ['name' => 'Leo Fridman', 'platform' => 'x', 'time' => '6 hours ago', 'title' => 'AI is Healing', 'body' => 'This AI is healing the sense of touch, and the science behind it is astonishing.', 'stats' => '18K likes · 903 comments · 210 shares', 'thumb' => 'from-khaki to-gold'],
        ['name' => 'Noor Stars', 'platform' => 'instagram', 'time' => '1 day ago', 'title' => 'New vlog is live', 'body' => 'Behind the scenes from this week — thank you for all the love.', 'stats' => '6.7K likes · 1.1K comments · 540 shares', 'thumb' => 'from-gold to-terracotta'],
    ];

    public const FOUND_ACCOUNTS = [
        ['platform' => 'youtube', 'label' => 'YouTube', 'handle' => '@MrBeast', 'count' => '420M subscribers'],
        ['platform' => 'instagram', 'label' => 'Instagram', 'handle' => '@mrbeast', 'count' => '64M followers'],
        ['platform' => 'tiktok', 'label' => 'TikTok', 'handle' => '@mrbeast', 'count' => '115M followers'],
        ['platform' => 'x', 'label' => 'X', 'handle' => '@MrBeast', 'count' => '34M followers'],
    ];

    public const PROFILE_STATS = [
        ['value' => '420M', 'label' => 'YouTube'],
        ['value' => '64M', 'label' => 'Followers'],
        ['value' => '115M', 'label' => 'TikTok'],
        ['value' => '34M', 'label' => 'X'],
        ['value' => '569M', 'label' => 'Followed by'],
        ['value' => '125K', 'label' => 'Tracking'],
    ];

    public const PROFILE_PLATFORMS = [
        ['platform' => 'youtube', 'label' => 'YouTube', 'time' => '2 hours ago'],
        ['platform' => 'instagram', 'label' => 'Instagram', 'time' => '1 day ago'],
        ['platform' => 'tiktok', 'label' => 'TikTok', 'time' => '3 days ago'],
        ['platform' => 'x', 'label' => 'X', 'time' => '1 week ago'],
    ];

    public const MY_INFLUENCERS = [
        ['initials' => 'MKB', 'name' => 'Marques Brownlee', 'meta' => 'Tech · @mkbhd'],
        ['initials' => 'NS', 'name' => 'Noor Stars', 'meta' => 'Entertainment · @noorstars'],
        ['initials' => 'LF', 'name' => 'Leo Fridman', 'meta' => 'Science · @lexfridman'],
        ['initials' => 'AA', 'name' => 'Ali Abdaal', 'meta' => 'Productivity · @aliabdaal'],
        ['initials' => 'IS', 'name' => 'iShowSpeed', 'meta' => 'Gaming · @ishowspeed'],
    ];

    public const FAQS = [
        ['q' => 'What is Followly?', 'a' => 'A single feed for the creators you care about. Follow someone once and see their new posts from every platform in one place.'],
        ['q' => 'Which platforms are supported?', 'a' => 'YouTube, Instagram, TikTok and X.'],
        ['q' => 'Is it free?', 'a' => 'Yes, it is free to use.'],
        ['q' => 'What if a creator is missing?', 'a' => 'Paste a link to any of their profiles or their username, and we find their other accounts so you can start tracking them.'],
        ['q' => 'Can I control notifications?', 'a' => 'Yes. In Settings you can mute platforms, turn in-app notifications off and choose a daily or weekly email digest.'],
    ];

    public string $tab = 'trending';

    /**
     * Guests have no follows to base recommendations on, so that tab shows the most tracked creators.
     * The "By" tabs show the top creator of each category, country or language.
     */
    #[Computed]
    public function influencers()
    {
        $query = Influencer::query()->with(['category', 'country', 'accounts']);

        return match ($this->tab) {
            'category' => $query->whereNotNull('category_id')->mostTracked()->limit(100)->get()->unique('category_id')->take(10),
            'country' => $query->whereNotNull('country_id')->mostTracked()->limit(100)->get()->unique('country_id')->take(10),
            'language' => $query->whereNotNull('language')->mostTracked()->limit(100)->get()->unique('language')->take(10),
            'recommended' => $query->mostTracked()->limit(10)->get(),
            default => $query->trending()->limit(10)->get(),
        };
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'trending';
    }
}; ?>

<div>
    <x-site-header />

    {{-- Hero --}}
    <section class="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-10 pb-20 sm:px-6 lg:grid-cols-2 lg:pt-16">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sand px-3 py-1 text-[10px] font-semibold uppercase tracking-widest text-accent">
                <flux:icon.squares-2x2 variant="micro" class="size-3" /> One feed for every creator
            </span>

            <h1 class="mt-5 text-5xl font-bold leading-[1.05] tracking-tight text-zinc-900 sm:text-6xl">
                Follow Once.<br>
                <span class="text-gold">Track Everywhere.</span>
            </h1>

            <p class="mt-6 max-w-md text-lg leading-relaxed text-zinc-500">
                Get updates from your favorite influencers in one place. Follow once and track their content across YouTube, Instagram, TikTok, X and more.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <flux:button variant="primary" icon:trailing="arrow-right" :href="route('register')" wire:navigate class="px-6!">Get Started Free</flux:button>
                <flux:button href="#features" class="px-6!">Learn More</flux:button>
            </div>

            <div class="mt-10 grid max-w-lg grid-cols-2 gap-6 sm:grid-cols-4">
                @foreach ($this::FEATURES as $feature)
                    <div>
                        <span class="flex size-10 items-center justify-center rounded-xl bg-sand text-accent">
                            <flux:icon :icon="$feature['icon']" class="size-5" />
                        </span>
                        <p class="mt-3 text-xs font-medium leading-snug text-zinc-700">{{ $feature['title'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mx-auto w-full max-w-lg">
            <div class="relative rounded-3xl border border-zinc-200 bg-white p-4 pb-7 shadow-xl shadow-zinc-900/10 rotate-1">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-full bg-sand font-display text-xs font-bold text-accent">MB</span>
                    <div>
                        <p class="flex items-center gap-1 font-display text-sm font-semibold text-zinc-900">MrBeast <x-verified class="size-3.5" /></p>
                        <p class="text-xs text-zinc-500">@Jimmy Donaldson · 2h</p>
                    </div>
                </div>

                {{-- shortcut: gradient stands in for the hero photo, swap in an <img> when the file exists. --}}
                <div class="mt-3 flex aspect-video items-center justify-center rounded-2xl bg-linear-to-br from-accent via-gold to-terracotta p-6 text-center">
                    <p class="font-display text-xl font-bold text-white sm:text-3xl">“$1 vs $1,000,000 Hotel!”</p>
                </div>

                <div class="mt-3 flex items-center justify-between text-xs text-zinc-500">
                    <span class="flex items-center gap-1"><flux:icon.heart variant="micro" class="size-3.5 text-terracotta" /> 24M</span>
                    <span class="flex items-center gap-1"><flux:icon.chat-bubble-oval-left variant="micro" class="size-3.5" /> 1.8M</span>
                    <span class="flex items-center gap-1"><flux:icon.paper-airplane variant="micro" class="size-3.5" /> Send</span>
                </div>

                @foreach ([['youtube', 'YouTube', '-top-3 -end-3'], ['instagram', 'Instagram', 'top-1/4 -start-5'], ['tiktok', 'TikTok', 'bottom-14 -end-4'], ['x', 'X (Twitter)', '-bottom-3 start-8']] as [$platform, $label, $position])
                    <span class="absolute {{ $position }} hidden items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-2.5 py-1 text-[11px] font-medium text-zinc-700 shadow-md sm:inline-flex">
                        <x-platform-icon :platform="$platform" size="size-4" /> {{ $label }}
                    </span>
                @endforeach
            </div>

            <dl class="mt-10 grid grid-cols-2 gap-6">
                @foreach ($this::STATS as $stat)
                    <div>
                        <dd class="font-display text-2xl font-bold text-zinc-900">{{ $stat['value'] }}</dd>
                        <dt class="mt-0.5 text-xs text-zinc-500">{{ $stat['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- How it works --}}
    <section id="features" class="mx-auto max-w-7xl scroll-mt-6 px-4 pb-20 text-center sm:px-6">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-terracotta">How it works</p>
        <h2 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">Four small steps, zero missed uploads</h2>
        <p class="mt-3 text-base text-zinc-500">Start tracking your favorite influencers in just a few steps.</p>

        <ol class="mt-10 grid gap-4 text-start sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($this::STEPS as $step)
                <li class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-sand text-accent">
                        <flux:icon :icon="$step['icon']" class="size-5" />
                    </span>
                    <p class="mt-6 font-display text-base font-semibold text-zinc-900">{{ $loop->iteration }}. {{ $step['title'] }}</p>
                    <p class="mt-2 text-sm text-zinc-500">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Feed showcase --}}
    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6">
        <div class="grid gap-8 rounded-3xl border border-zinc-200 bg-sand p-6 sm:p-8 lg:grid-cols-[14rem_1fr]">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">My Feed</p>
                <ul class="mt-4 space-y-1 text-sm font-medium text-zinc-600">
                    @foreach ($this::FEED_NAV as $item)
                        <li @class(['flex items-center gap-2.5 rounded-xl px-3 py-2', 'bg-white font-semibold text-zinc-900 shadow-sm' => $loop->first])>
                            <flux:icon :icon="$item['icon']" class="size-4" />
                            <span class="flex-1">{{ $item['label'] }}</span>
                            @if ($item['label'] === 'Notifications')
                                <span class="flex size-5 items-center justify-center rounded-full bg-terracotta text-[10px] font-semibold text-white">3</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-6 text-xs leading-relaxed text-zinc-500">Your Personalized Feed brings together new updates from YouTube, Instagram, TikTok, X and more — organized by influencer.</p>
            </div>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-xl font-bold text-zinc-900">Your Personalized Feed</h2>
                    <div class="flex flex-wrap gap-1 text-xs font-medium text-zinc-600">
                        <span class="rounded-full bg-accent px-3 py-1 text-white">All Updates</span>
                        @foreach (['YouTube', 'Instagram', 'TikTok'] as $label)
                            <span class="rounded-full px-3 py-1">{{ $label }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($this::FEED_POSTS as $post)
                        <article class="flex gap-4 rounded-2xl border border-zinc-200 bg-white p-4">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sand font-display text-xs font-bold text-accent">{{ str($post['name'])->explode(' ')->map(fn ($word) => $word[0])->join('') }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                                    <span class="font-display text-sm font-semibold text-zinc-900">{{ $post['name'] }}</span>
                                    <x-verified class="size-3.5" />
                                    <x-platform-icon :platform="$post['platform']" size="size-3.5" />
                                    · {{ $post['time'] }}
                                </p>
                                <h3 class="mt-1 text-sm font-semibold text-zinc-900">{{ $post['title'] }}</h3>
                                <p class="mt-1 text-xs text-zinc-500">{{ $post['body'] }}</p>
                                <p class="mt-2 text-[11px] text-zinc-500">{{ $post['stats'] }}</p>
                            </div>
                            <div class="hidden h-16 w-24 shrink-0 rounded-xl bg-linear-to-br sm:block {{ $post['thumb'] }}"></div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Trending (real data) --}}
    <section id="explore" class="mx-auto max-w-7xl scroll-mt-6 px-4 pb-20 sm:px-6"
        x-data="{ scroll(direction) { $refs.row.scrollBy({ left: direction * $refs.row.clientWidth * 0.8, behavior: 'smooth' }) } }">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-terracotta">Explore</p>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">Discover Trending Influencers</h2>
                <p class="mt-3 text-base text-zinc-500">Discover trending creators around the world, curated across every platform.</p>
            </div>
            <a href="{{ route('explore') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-accent hover:underline">See All <flux:icon.arrow-right variant="micro" class="size-4" /></a>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            @foreach ($this::TABS as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" @class([
                    'rounded-full border px-4 py-1.5 text-sm font-medium transition',
                    'border-accent bg-accent text-white shadow-sm' => $tab === $key,
                    'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' => $tab !== $key,
                ])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="relative mt-6" wire:loading.class="opacity-50" wire:target="setTab">
            @if ($this->influencers->isEmpty())
                <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-14 text-center">
                    <flux:text>No creators here yet.</flux:text>
                </div>
            @else
                <div x-ref="row" class="flex snap-x gap-4 overflow-x-auto pb-3 [scrollbar-width:none]">
                    @foreach ($this->influencers as $influencer)
                        <x-influencer-card :influencer="$influencer" :followed="in_array($influencer->id, $this->followedIds)" class="w-56 shrink-0 snap-start" wire:key="trending-{{ $tab }}-{{ $influencer->id }}" />
                    @endforeach
                </div>

                <button type="button" x-on:click="scroll(-1)" aria-label="Previous creators" class="absolute top-1/2 -start-3 hidden size-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-700 shadow-md sm:flex">
                    <flux:icon.chevron-left variant="micro" class="size-4 rtl:rotate-180" />
                </button>
                <button type="button" x-on:click="scroll(1)" aria-label="More creators" class="absolute top-1/2 -end-3 hidden size-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-700 shadow-md sm:flex">
                    <flux:icon.chevron-right variant="micro" class="size-4 rtl:rotate-180" />
                </button>
            @endif
        </div>
    </section>

    {{-- Add an influencer --}}
    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6">
        <div class="grid items-center gap-10 rounded-3xl border border-zinc-200 bg-sand p-6 sm:p-10 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-zinc-900">Add an Influencer</h2>
                <p class="mt-3 max-w-md text-base text-zinc-500">Paste a link or username and we'll find their social media accounts — then track everything in your feed.</p>

                {{-- Guests sign in first, then land on the add page with this query filled in. --}}
                <form method="GET" action="{{ route('influencers.create') }}" class="mt-6 flex max-w-md items-center gap-2">
                    <div class="relative flex-1">
                        <flux:icon.magnifying-glass variant="micro" class="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                        <input type="text" name="q" required maxlength="255" placeholder="https://www.youtube.com/@MrBeast" aria-label="Link or username" class="h-10 w-full rounded-full border border-zinc-200 bg-white ps-9 pe-4 text-sm text-zinc-900 placeholder:text-zinc-400 focus:outline-2 focus:outline-accent">
                    </div>
                    <flux:button type="submit" variant="primary">Search</flux:button>
                </form>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold text-zinc-700">We found these accounts:</p>
                <div class="mt-3 flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-full bg-sand font-display text-xs font-bold text-accent">MB</span>
                    <div>
                        <p class="flex items-center gap-1 font-display text-sm font-semibold text-zinc-900">MrBeast <x-verified class="size-3.5" /></p>
                        <p class="text-xs text-zinc-500">@Jimmy Donaldson</p>
                    </div>
                </div>

                <ul class="mt-4 space-y-2">
                    @foreach ($this::FOUND_ACCOUNTS as $account)
                        <li class="flex items-center gap-2 rounded-xl bg-zinc-100 px-3 py-2 text-xs text-zinc-700">
                            <flux:icon.check-circle variant="micro" class="size-4 text-accent" />
                            <x-platform-icon :platform="$account['platform']" size="size-4" />
                            <span class="font-medium">{{ $account['label'] }}</span>
                            <span class="flex-1 truncate text-zinc-500">{{ $account['handle'] }}</span>
                            <span class="text-zinc-500">{{ $account['count'] }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-4 rounded-full bg-accent py-2 text-center text-sm font-medium text-white">Start Tracking Selected (3)</p>
            </div>
        </div>
    </section>

    {{-- Profile showcase --}}
    <section class="mx-auto grid max-w-7xl gap-6 px-4 pb-20 sm:px-6 lg:grid-cols-[1fr_22rem]">
        <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
            <div class="h-32 bg-linear-to-r from-gold via-khaki to-terracotta sm:h-40"></div>

            <div class="p-5 sm:p-6">
                <div class="-mt-14 flex flex-wrap items-end gap-3">
                    <span class="relative flex size-20 items-center justify-center rounded-2xl bg-sand font-display text-xl font-bold text-accent ring-4 ring-white">MB</span>
                    <div class="flex-1 pt-14">
                        <p class="flex items-center gap-1 font-display text-xl font-bold text-zinc-900">MrBeast <x-verified /></p>
                        <p class="text-xs text-zinc-500">@Entertainment · Jimmy Donaldson</p>
                    </div>
                    <span class="rounded-full bg-accent px-5 py-1.5 text-sm font-medium text-white">Follow</span>
                </div>

                <dl class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-6">
                    @foreach ($this::PROFILE_STATS as $stat)
                        <div class="rounded-xl bg-zinc-100 p-3">
                            <dd class="font-display text-base font-bold text-zinc-900">{{ $stat['value'] }}</dd>
                            <dt class="text-[11px] text-zinc-500">{{ $stat['label'] }}</dt>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <div>
                        <div class="flex gap-4 border-b border-zinc-200 text-xs font-medium text-zinc-500">
                            <span class="-mb-px border-b-2 border-accent pb-2 text-accent">Latest Posts</span>
                            <span class="pb-2">About</span>
                            <span class="pb-2">Related Influencers</span>
                        </div>
                        <div class="mt-4 flex gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-sand font-display text-[11px] font-bold text-accent">MB</span>
                            <div class="flex-1">
                                <p class="font-display text-sm font-semibold text-zinc-900">MrBeast <span class="font-sans text-xs font-normal text-zinc-500">· 2h</span></p>
                                <p class="mt-1 text-sm text-zinc-700">$1 vs $1,000,000 Hotel!</p>
                                <p class="mt-2 flex gap-3 text-xs text-zinc-500">
                                    <span class="flex items-center gap-1"><flux:icon.heart variant="micro" class="size-3.5 text-terracotta" /> 24M</span>
                                    <span class="flex items-center gap-1"><flux:icon.chat-bubble-oval-left variant="micro" class="size-3.5" /> 1.8M</span>
                                </p>
                            </div>
                            <div class="h-16 w-20 shrink-0 rounded-xl bg-linear-to-br from-accent to-terracotta"></div>
                        </div>
                    </div>

                    <div>
                        <p class="font-display text-sm font-semibold text-zinc-900">Across Platforms</p>
                        <ul class="mt-3 space-y-2">
                            @foreach ($this::PROFILE_PLATFORMS as $item)
                                <li class="flex items-center gap-2 rounded-xl bg-zinc-100 px-3 py-2 text-xs text-zinc-700">
                                    <x-platform-icon :platform="$item['platform']" size="size-4" />
                                    <span class="flex-1 font-medium">{{ $item['label'] }}</span>
                                    <span class="text-zinc-500">{{ $item['time'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <aside class="rounded-3xl border border-zinc-200 bg-sand p-5 sm:p-6">
            <h2 class="text-xl font-bold text-zinc-900">My Influencers</h2>
            <div class="relative mt-4 flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-400">
                <flux:icon.magnifying-glass variant="micro" class="size-4" /> Search my influencers...
            </div>
            <ul class="mt-4 space-y-3">
                @foreach ($this::MY_INFLUENCERS as $item)
                    <li class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sand font-display text-[11px] font-bold text-accent">{{ $item['initials'] }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-display text-sm font-semibold text-zinc-900">{{ $item['name'] }}</p>
                            <p class="truncate text-[11px] text-zinc-500">{{ $item['meta'] }}</p>
                        </div>
                        <span class="flex shrink-0 gap-1">
                            @foreach (['youtube', 'instagram', 'tiktok'] as $platform)
                                <x-platform-icon :platform="$platform" size="size-3.5" />
                            @endforeach
                        </span>
                    </li>
                @endforeach
            </ul>
        </aside>
    </section>

    {{-- About --}}
    <section id="about" class="mx-auto max-w-3xl scroll-mt-6 px-4 pb-20 text-center sm:px-6">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-terracotta">About</p>
        <h2 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">Your creators, without the tab-hopping</h2>
        <p class="mt-4 text-base leading-relaxed text-zinc-500">{{ config('app.name') }} exists because the people you follow post everywhere and no single app shows it all. We watch their public accounts and bring every new post into one calm feed, so you can keep up without keeping five apps open.</p>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl scroll-mt-6 px-4 pb-20 sm:px-6">
        <h2 class="text-center text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">Frequently asked questions</h2>
        <div class="mt-8 space-y-3">
            @foreach ($this::FAQS as $faq)
                <details class="group rounded-2xl border border-zinc-200 bg-white p-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-display text-base font-semibold text-zinc-900">
                        {{ $faq['q'] }}
                        <flux:icon.chevron-down variant="micro" class="size-4 shrink-0 transition group-open:rotate-180" />
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-500">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Call to action --}}
    <section class="mx-auto max-w-7xl px-4 pb-20 sm:px-6">
        <div class="rounded-3xl bg-accent px-6 py-16 text-center text-white">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Ready to Track Your Favorite Influencers?</h2>
            <p class="mx-auto mt-4 max-w-xl text-base text-white/85">Join thousands of users who never miss an update. It's free, and always will be.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" wire:navigate class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-2.5 text-sm font-medium text-zinc-900 hover:bg-sand">Get Started Free <flux:icon.arrow-right variant="micro" class="size-4" /></a>
                <a href="#features" class="rounded-full border border-white/40 px-6 py-2.5 text-sm font-medium text-white hover:bg-white/10">Learn More</a>
            </div>
        </div>
    </section>

    <x-site-footer />
</div>
