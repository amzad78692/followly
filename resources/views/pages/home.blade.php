<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $collage = [
        ['img' => 12, 'platform' => 'youtube', 'class' => 'left-[30%] top-0 w-52 rotate-[-4deg]'],
        ['img' => 47, 'platform' => 'instagram', 'class' => 'left-0 top-[34%] w-40 rotate-[3deg]'],
        ['img' => 33, 'platform' => 'tiktok', 'class' => 'right-0 top-[30%] w-40 rotate-[4deg]'],
        ['img' => 59, 'platform' => 'x', 'class' => 'left-[38%] bottom-0 w-44 rotate-[-3deg]'],
    ];

    public array $features = [
        ['icon' => 'squares-2x2', 'title' => 'All Platforms', 'text' => 'One Feed', 'color' => 'bg-indigo-50 text-indigo-600'],
        ['icon' => 'user-group', 'title' => 'Unlimited', 'text' => 'Influencers', 'color' => 'bg-emerald-50 text-emerald-600'],
        ['icon' => 'tag', 'title' => 'Automatic', 'text' => 'Categories', 'color' => 'bg-orange-50 text-orange-500'],
        ['icon' => 'sparkles', 'title' => 'Discover', 'text' => 'New Creators', 'color' => 'bg-violet-50 text-violet-600'],
    ];

    public array $stats = [
        ['value' => '1,000+', 'label' => 'Influencers'],
        ['value' => 'Multiple', 'label' => 'Social Platforms'],
        ['value' => 'Global', 'label' => 'Creators'],
        ['value' => '100%', 'label' => 'Free to Use'],
    ];
}; ?>

<div>
    {{-- Navbar --}}
    <header class="mx-auto flex max-w-7xl items-center gap-8 px-6 py-5">
        <x-brand />

        <nav class="hidden items-center gap-7 text-sm font-medium text-zinc-600 md:flex">
            <a href="#features" class="hover:text-zinc-900">Features</a>
            <a href="{{ route('explore') }}" wire:navigate class="hover:text-zinc-900">Explore</a>
            <a href="#about" class="hover:text-zinc-900">About</a>
            <a href="#faq" class="hover:text-zinc-900">FAQ</a>
        </nav>

        <flux:spacer />

        <div class="flex items-center gap-2">
            <flux:button size="sm" :href="route('login')" wire:navigate>Sign in</flux:button>
            <flux:button size="sm" variant="primary" :href="route('register')" wire:navigate>Get Started</flux:button>
        </div>
    </header>

    {{-- Hero --}}
    <section class="mx-auto grid max-w-7xl items-center gap-12 px-6 pt-10 pb-16 lg:grid-cols-2 lg:pt-16">
        <div>
            <h1 class="text-5xl font-extrabold leading-[1.05] tracking-tight text-zinc-900 sm:text-6xl">
                Follow Once.<br>
                <span class="bg-linear-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">Track Everywhere.</span>
            </h1>

            <p class="mt-6 max-w-md text-lg leading-relaxed text-zinc-600">
                Get all updates from your favorite influencers in one place. Follow once and track their content across YouTube, Instagram, TikTok, X and more.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <flux:button variant="primary" :href="route('register')" wire:navigate class="px-6!">Get Started Free</flux:button>
                <flux:button href="#features" class="px-6!">Learn More</flux:button>
            </div>
        </div>

        <div class="relative mx-auto aspect-square w-full max-w-md">
            <div class="absolute inset-6 rounded-[40%_60%_55%_45%/50%_45%_55%_50%] bg-linear-to-br from-indigo-100 via-violet-100 to-white"></div>

            @foreach ($collage as $card)
                <div class="absolute {{ $card['class'] }} aspect-square overflow-hidden rounded-2xl bg-white p-1 shadow-xl shadow-indigo-900/10 ring-1 ring-zinc-100">
                    <img src="https://i.pravatar.cc/400?img={{ $card['img'] }}" alt="" class="size-full rounded-xl object-cover">
                    <x-platform-icon :platform="$card['platform']" size="size-8" class="absolute top-3 end-3 shadow-md ring-2 ring-white" />
                </div>
            @endforeach
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="mx-auto max-w-7xl px-6 pb-14">
        <div class="grid grid-cols-2 gap-8 sm:grid-cols-4 lg:max-w-2xl">
            @foreach ($features as $feature)
                <div>
                    <div class="flex size-11 items-center justify-center rounded-xl {{ $feature['color'] }}">
                        <flux:icon :icon="$feature['icon']" variant="solid" class="size-5" />
                    </div>
                    <p class="mt-3 text-sm font-medium leading-snug text-zinc-800">{{ $feature['title'] }}<br>{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Stats --}}
    <section id="about" class="border-t border-zinc-100 bg-zinc-50/60">
        <div class="mx-auto grid max-w-7xl grid-cols-2 divide-zinc-200 px-6 py-12 sm:grid-cols-4 sm:divide-x">
            @foreach ($stats as $stat)
                <div class="py-4 text-center">
                    <p class="text-3xl font-extrabold tracking-tight text-indigo-600">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-sm text-zinc-500">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <footer id="faq" class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-zinc-500">
        <x-brand class="text-base" />
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </footer>
</div>
