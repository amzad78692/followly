<?php

use App\Enums\Platform;
use App\Feed\SmartFeed;
use App\Models\Influencer;
use App\Models\Post;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('My Feed')] class extends Component {
    public const PER_PAGE = 15;

    #[Url]
    public string $platform = 'all';

    #[Url]
    public bool $watchlist = false;

    #[Url]
    public ?int $collection = null;

    public int $limit = self::PER_PAGE;

    public function updated(): void
    {
        $this->limit = self::PER_PAGE;
    }

    /**
     * Watchlist and a collection are alternative scopes; the platform filter combines with either.
     */
    public function scope(string $scope, ?int $collection = null): void
    {
        $this->watchlist = $scope === 'watchlist';
        $this->collection = $scope === 'collection' ? $collection : null;
        $this->limit = self::PER_PAGE;
    }

    public function showEverything(): void
    {
        $this->scope('all');
        $this->platform = 'all';
    }

    /**
     * Only the user's own collections; any other id is ignored.
     */
    #[Computed]
    public function activeCollection()
    {
        return $this->collection ? auth()->user()->collections()->find($this->collection) : null;
    }

    /**
     * The current page of posts; one extra row is fetched to know whether more exist.
     */
    #[Computed]
    public function batch()
    {
        $posts = app(SmartFeed::class)
            ->query(auth()->user(), Platform::tryFrom($this->platform), $this->watchlist, $this->activeCollection)
            ->with('influencer.media')
            ->limit($this->limit + 1)
            ->get();

        // Every visible version of each SmartLink group, for the card's platform icons.
        $versions = Post::visible()->whereIn('group_id', $posts->pluck('group_id')->filter())->get()->groupBy('group_id');
        $posts->each(fn (Post $post) => $post->setRelation('versions', $versions[$post->group_id] ?? collect()));

        return $posts;
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
    public function watchlistInfluencers()
    {
        return auth()->user()->watchlist()->with('media')->orderByPivot('watchlisted_at')->get();
    }

    #[Computed]
    public function collections()
    {
        return auth()->user()->collections()->withCount('influencers')->orderBy('name')->get();
    }

    #[Computed]
    public function trending()
    {
        return Influencer::trending()->with('media')->limit(5)->get();
    }

    #[Computed]
    public function followsAnyone(): bool
    {
        return auth()->user()->follows()->exists();
    }
}; ?>

@php
    $chip = fn (bool $on) => [
        'inline-flex items-center gap-1.5 rounded-full border px-3 py-2.5 text-sm font-medium transition',
        'border-accent bg-accent text-white shadow-sm' => $on,
        'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' => ! $on,
    ];
    $everything = ! $watchlist && ! $this->activeCollection;
@endphp

<div class="mx-auto max-w-6xl">
    <x-page-header icon="home" title="My Feed" subtitle="Latest posts from the creators you follow, across every platform." />

    {{-- Watchlist strip --}}
    <section class="mt-4 card p-4" aria-labelledby="watchlist-heading">
        <div class="flex items-center gap-2">
            <flux:icon.star variant="solid" class="size-4 text-amber-400" />
            <flux:heading id="watchlist-heading" size="sm">Watchlist</flux:heading>
        </div>
        @if ($this->watchlistInfluencers->isEmpty())
            <flux:text class="mt-2 text-sm">Star up to 10 creators you follow to keep them here and see their posts first.</flux:text>
        @else
            <div class="mt-3 flex gap-4 overflow-x-auto pb-1 [scrollbar-width:none]">
                @foreach ($this->watchlistInfluencers as $influencer)
                    <a href="{{ route('influencers.show', $influencer) }}" wire:navigate wire:key="watch-{{ $influencer->id }}" class="flex w-16 shrink-0 flex-col items-center gap-1 text-center">
                        <flux:avatar circle size="lg" :src="image_url($influencer->avatar, 112, 112)" class="ring-2 ring-amber-300" />
                        <span class="w-full truncate text-xs text-zinc-700">{{ $influencer->name }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        {{-- Smart Feed --}}
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="scope('all')" @class($chip($everything)) aria-pressed="{{ $everything ? 'true' : 'false' }}">All</button>
                <button type="button" wire:click="scope('watchlist')" @class($chip($watchlist)) aria-pressed="{{ $watchlist ? 'true' : 'false' }}">
                    <flux:icon.star class="size-4" /> Watchlist
                </button>
                @if ($this->activeCollection)
                    <button type="button" wire:click="scope('all')" @class($chip(true)) aria-label="Stop filtering by {{ $this->activeCollection->name }}">
                        <flux:icon.folder class="size-4" /> {{ $this->activeCollection->name }} <flux:icon.x-mark class="size-3.5" />
                    </button>
                @endif
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                @foreach (['all' => 'All platforms'] + collect(Platform::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all() as $key => $label)
                    <button type="button" wire:click="$set('platform', '{{ $key }}')" @class($chip($platform === $key)) aria-pressed="{{ $platform === $key ? 'true' : 'false' }}">
                        @if ($key !== 'all')
                            <x-platform-icon :platform="$key" size="size-4" />
                        @endif
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div wire:loading.delay wire:target="platform, scope, showEverything" class="mt-4 w-full space-y-4">
                @foreach (range(1, 3) as $i)
                    <flux:skeleton.group animate="shimmer" class="card p-5">
                        <div class="flex items-center gap-3">
                            <flux:skeleton class="size-10 rounded-full" />
                            <div class="flex-1 space-y-2">
                                <flux:skeleton.line class="w-1/3" />
                                <flux:skeleton.line class="w-1/4" />
                            </div>
                        </div>
                        <flux:skeleton class="mt-4 aspect-video w-full max-w-md rounded-xl" />
                    </flux:skeleton.group>
                @endforeach
            </div>

            <div wire:loading.remove wire:target="platform, scope, showEverything" class="mt-4 space-y-4">
                @forelse ($this->posts as $post)
                    <x-smartlink-card :post="$post" wire:key="post-{{ $post->id }}" />
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
                        <flux:icon.inbox class="mx-auto size-10 text-zinc-300" />
                        @if (! $this->followsAnyone && ! $this->activeCollection)
                            <flux:heading class="mt-3">You don't follow anyone yet</flux:heading>
                            <flux:text class="mt-1">Follow creators and their posts from every platform show up here.</flux:text>
                            <flux:button variant="primary" size="sm" class="mt-4" :href="route('explore')" wire:navigate>Explore creators</flux:button>
                        @elseif (! $everything || $platform !== 'all')
                            <flux:heading class="mt-3">Nothing here yet</flux:heading>
                            <flux:text class="mt-1">No posts match this filter. Try another one.</flux:text>
                            <flux:button size="sm" class="mt-4" wire:click="showEverything">Show everything</flux:button>
                        @else
                            <flux:heading class="mt-3">No posts yet</flux:heading>
                            <flux:text class="mt-1">New posts from the creators you follow appear here after the next sync.</flux:text>
                        @endif
                    </div>
                @endforelse

                @if ($this->hasMore)
                    <div wire:intersect="loadMore" wire:key="load-more" class="flex justify-center py-4">
                        <flux:button size="sm" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                            <span wire:loading.remove wire:target="loadMore">Load more</span>
                            <span wire:loading wire:target="loadMore">Loading…</span>
                        </flux:button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Side panel: below the feed on small screens --}}
        <aside class="space-y-6">
            <section class="card p-4" aria-labelledby="collections-heading">
                <flux:heading id="collections-heading" size="sm">Collections</flux:heading>
                @forelse ($this->collections as $item)
                    <button type="button" wire:click="scope('collection', {{ $item->id }})" wire:key="collection-{{ $item->id }}" @class([
                        'mt-2 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-start text-sm hover:bg-zinc-50',
                        'bg-zinc-100 font-medium' => $this->activeCollection?->is($item),
                    ])>
                        <flux:icon.folder class="size-4 text-zinc-400" />
                        <span class="min-w-0 flex-1 truncate">{{ $item->name }}</span>
                        <span class="text-xs text-zinc-400">{{ $item->influencers_count }}</span>
                    </button>
                @empty
                    <flux:text class="mt-2 text-sm">Group creators into collections to filter your feed by topic.</flux:text>
                @endforelse
            </section>

            <section class="card p-4" aria-labelledby="trending-heading">
                <flux:heading id="trending-heading" size="sm">Trending</flux:heading>
                @foreach ($this->trending as $influencer)
                    <a href="{{ route('influencers.show', $influencer) }}" wire:navigate wire:key="trending-{{ $influencer->id }}" class="mt-2 flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-zinc-50">
                        <flux:avatar circle size="sm" :src="image_url($influencer->avatar, 80, 80)" />
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $influencer->name }}</span>
                    </a>
                @endforeach
                <flux:link :href="route('explore')" wire:navigate class="mt-3 inline-block text-sm">See more</flux:link>
            </section>
        </aside>
    </div>
</div>
