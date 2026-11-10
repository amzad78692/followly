<?php

use App\Livewire\Concerns\TogglesFollow;
use App\Models\Influencer;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    use TogglesFollow;

    public const PER_PAGE = 10;

    public Influencer $influencer;

    #[Url]
    public string $tab = 'posts';

    public int $limit = self::PER_PAGE;

    public function mount(Influencer $influencer): void
    {
        $this->influencer = $influencer->load(['category', 'country']);
    }

    /**
     * Accounts with the time of their latest post, for the stats row and "Across Platforms".
     */
    #[Computed]
    public function accounts()
    {
        return $this->influencer->accounts()->withMax('posts', 'published_at')->get();
    }

    #[Computed]
    public function batch()
    {
        return $this->influencer->posts()->visible()->with('influencer')->latest('published_at')->limit($this->limit + 1)->get();
    }

    #[Computed]
    public function posts()
    {
        return $this->batch->take($this->limit);
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->batch->count() > $this->limit;
    }

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->limit += self::PER_PAGE;
            unset($this->batch, $this->posts, $this->hasMore);
        }
    }

    #[Computed]
    public function related()
    {
        return Influencer::query()
            ->with(['category', 'country', 'accounts'])
            ->whereKeyNot($this->influencer->id)
            ->when($this->influencer->category_id, fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->mostTracked()
            ->limit(4)
            ->get();
    }

    protected function followToggled(): void
    {
        $this->influencer->refresh();
        unset($this->related);
    }

    public function rendering($view): void
    {
        $view->layout(auth()->check() ? 'components.layouts.dashboard' : 'components.layouts.public')
            ->title($this->influencer->name)
            ->layoutData($this->seo());
    }

    /**
     * Description, share image and structured data for the profile (see partials/seo).
     */
    protected function seo(): array
    {
        $influencer = $this->influencer;
        $url = route('influencers.show', $influencer);
        $image = url(image_url($influencer->avatar, 400, 400));
        $platforms = $this->accounts->map(fn ($account) => $account->platform->label())->join(', ', ' and ');

        return [
            'description' => Str::limit(Str::squish($influencer->bio ?? ''), 160)
                ?: "Latest posts from {$influencer->name}".($platforms ? " on {$platforms}" : '').', in one feed on '.config('app.name').'.',
            'image' => $image,
            'canonical' => $url,
            'ogType' => 'profile',
            'jsonLd' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $influencer->name,
                'alternateName' => $influencer->real_name !== $influencer->name ? $influencer->real_name : null,
                'description' => $influencer->bio,
                'image' => $image,
                'url' => $url,
                'sameAs' => $this->accounts->pluck('url')->filter()->values()->all(),
            ]),
        ];
    }
}; ?>

@php
    $influencer = $this->influencer;
    $accounts = $this->accounts;
    $followed = in_array($influencer->id, $this->followedIds);
@endphp

<div class="mx-auto max-w-5xl">
    {{-- Cover + identity --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white">
        <div class="relative h-40 bg-linear-to-r from-gold via-khaki to-terracotta sm:h-52">
            @if ($influencer->cover)
                <img src="{{ image_url($influencer->cover, 1600) }}" alt="" class="size-full object-cover opacity-60 mix-blend-overlay">
            @endif
        </div>

        <div class="px-5 pb-5 sm:px-8">
            <div class="-mt-14 flex flex-wrap items-end gap-4 sm:-mt-16">
                <img src="{{ image_url($influencer->avatar, 256, 256) }}" alt="" class="relative size-28 rounded-full bg-white object-cover ring-4 ring-white sm:size-32">

                <div class="min-w-0 flex-1 pt-16 pb-1">
                    <h1 class="flex items-center gap-2 text-2xl font-bold text-zinc-900">
                        {{ $influencer->name }}
                        @if ($influencer->is_verified)
                            <x-verified class="size-5" />
                        @endif
                    </h1>
                    <p class="text-sm text-zinc-500">
                        {{ collect([$accounts->first()?->handle, $influencer->real_name !== $influencer->name ? $influencer->real_name : null])->filter()->join(' · ') }}
                    </p>
                </div>

                <div class="flex items-center gap-2 pb-1">
                    @if ($followed)
                        <flux:button icon="check" wire:click="toggle({{ $influencer->id }})">Following</flux:button>
                    @else
                        <flux:button variant="primary" class="px-6!" wire:click="toggle({{ $influencer->id }})">Follow</flux:button>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach (array_filter([$influencer->category?->name, $influencer->country?->name, $influencer->language]) as $badge)
                    <flux:badge size="sm">{{ $badge }}</flux:badge>
                @endforeach
            </div>

            {{-- Stats --}}
            <div class="mt-6 grid grid-cols-3 gap-y-5 sm:grid-cols-6">
                @foreach ($accounts as $account)
                    <div>
                        <p class="flex items-center gap-1.5 font-display text-2xl font-bold text-zinc-900"><x-platform-icon :platform="$account->platform" size="size-4" /> {{ $account->formatted_followers }}</p>
                        <p class="text-xs text-zinc-500">{{ $account->platform->unit() }}</p>
                    </div>
                @endforeach
                <div class="sm:border-s sm:border-zinc-200 sm:ps-4">
                    <p class="font-display text-2xl font-bold text-zinc-900">{{ $influencer->formatted_reach }}</p>
                    <p class="text-xs text-zinc-500">Combined Followers<br><span class="text-zinc-400">(Not unique people)</span></p>
                </div>
                <div>
                    <p class="font-display text-2xl font-bold text-zinc-900">{{ $influencer->formatted_tracking }}</p>
                    <p class="text-xs text-zinc-500">Tracking on {{ config('app.name') }}</p>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="flex gap-6 overflow-x-auto whitespace-nowrap border-t border-zinc-200 px-5 sm:px-8">
            @foreach (['posts' => 'Latest Posts', 'about' => 'About', 'related' => 'Related Influencers', 'analytics' => 'Analytics'] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                    '-mb-px border-b-2 py-3 text-sm font-medium transition',
                    'border-accent text-accent' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800' => $tab !== $key,
                ])>{{ $label }}</button>
            @endforeach
        </nav>
    </div>

    {{-- Tab content --}}
    <div class="mt-6">
        @if ($tab === 'about')
            <div class="rounded-2xl border border-zinc-200 bg-white p-6">
                <flux:heading size="lg" class="font-semibold!">About {{ $influencer->name }}</flux:heading>
                <flux:text class="mt-2 whitespace-pre-line">{{ $influencer->bio ?: 'No bio yet.' }}</flux:text>

                <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                    @foreach (['Category' => $influencer->category?->name, 'Country' => $influencer->country?->name, 'Language' => $influencer->language] as $label => $value)
                        <div class="rounded-xl bg-zinc-50 p-4">
                            <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                            <dd class="mt-1 font-semibold text-zinc-900">{{ $value ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <flux:heading class="mt-6 font-semibold!">Accounts</flux:heading>
                <ul class="mt-2 divide-y divide-zinc-100">
                    @foreach ($accounts as $account)
                        <li class="flex items-center gap-3 py-2.5 text-sm">
                            <x-platform-icon :platform="$account->platform" size="size-5" />
                            <span class="w-24 font-medium text-zinc-800">{{ $account->platform->label() }}</span>
                            <a href="{{ $account->url }}" target="_blank" rel="noopener" class="flex-1 text-zinc-500 hover:underline">{{ $account->handle }}</a>
                            <span class="text-zinc-500">{{ $account->formatted_followers }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($tab === 'analytics')
            <livewire:influencer-analytics :influencer="$influencer" :key="'analytics-'.$influencer->id" />
        @elseif ($tab === 'related')
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                @forelse ($this->related as $item)
                    <x-influencer-card :influencer="$item" :followed="in_array($item->id, $this->followedIds)" wire:key="rel-{{ $item->id }}" />
                @empty
                    <flux:text class="col-span-full">No related creators yet.</flux:text>
                @endforelse
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-[1fr_16rem]">
                <div class="space-y-4">
                    @forelse ($this->posts as $post)
                        <x-post-card :post="$post" compact wire:key="post-{{ $post->id }}" />
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
                            <flux:text>No recent posts.</flux:text>
                        </div>
                    @endforelse

                    @if ($this->hasMore)
                        <div wire:intersect="loadMore" class="flex justify-center py-4">
                            <flux:button size="sm" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                                <span wire:loading.remove wire:target="loadMore">Load more</span>
                                <span wire:loading wire:target="loadMore">Loading…</span>
                            </flux:button>
                        </div>
                    @endif
                </div>

                <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-4 lg:sticky lg:top-24">
                    <flux:heading size="sm" class="font-semibold!">Across Platforms</flux:heading>
                    <ul class="mt-3 space-y-3">
                        @foreach ($accounts as $account)
                            <li class="flex items-center gap-2 text-sm">
                                <x-platform-icon :platform="$account->platform" size="size-4" />
                                <span class="flex-1 text-zinc-700">{{ $account->platform->label() }}</span>
                                <span class="text-xs text-zinc-400">{{ $account->posts_max_published_at ? \Illuminate\Support\Carbon::parse($account->posts_max_published_at)->diffForHumans(short: true) : '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </aside>
            </div>
        @endif
    </div>
</div>
