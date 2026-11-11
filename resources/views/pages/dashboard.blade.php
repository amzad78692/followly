<?php

use App\Enums\Platform;
use App\Feed\SmartFeed;
use App\Models\Post;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

    #[Url]
    public bool $saved = false;

    /** "smart" keeps the SmartFeed ranking; "latest" is newest first. */
    #[Url]
    public string $sort = 'smart';

    public int $limit = self::PER_PAGE;

    public function updated(): void
    {
        $this->limit = self::PER_PAGE;
    }

    /**
     * Watchlist, a collection and Saved are alternative scopes; the platform filter combines with any.
     */
    public function scope(string $scope, ?int $collection = null): void
    {
        $this->watchlist = $scope === 'watchlist';
        $this->collection = $scope === 'collection' ? $collection : null;
        $this->saved = $scope === 'saved';
        $this->limit = self::PER_PAGE;
    }

    /** A post card's bookmark changed: only the Saved list needs a fresh render. */
    #[On('post-saved')]
    public function savedChanged(): void
    {
        if (! $this->saved) {
            $this->skipRender();
        }
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
        $platform = Platform::tryFrom($this->platform);

        if ($this->saved) {
            $query = auth()->user()->savedPosts()->visible()->platform($platform)
                ->orderByPivot('created_at', 'desc')->orderByDesc('posts.id');
        } else {
            $query = app(SmartFeed::class)->query(auth()->user(), $platform, $this->watchlist, $this->activeCollection);
            if ($this->sort === 'latest') {
                $query->reorder()->orderByDesc('posts.published_at')->orderByDesc('posts.id');
            }
        }

        $posts = $query->with('influencer.media')->limit($this->limit + 1)->get();

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

    /**
     * The user's newest keyword alerts with their unread matches: count and the latest match's platform.
     */
    #[Computed]
    public function alerts()
    {
        $user = auth()->user();
        $unread = $user->unreadNotifications()->where('type', 'keyword-alert')->latest()->limit(200)->get();

        return $user->keywordAlerts()->latest()->limit(4)->get()->map(function ($alert) use ($unread) {
            $hits = $unread->filter(fn ($n) => in_array(mb_strtolower($alert->keyword), array_map('mb_strtolower', $n->data['keywords'] ?? [])));

            return ['keyword' => $alert->keyword, 'count' => $hits->count(), 'platform' => $hits->first()?->data['platform'] ?? null];
        });
    }

    #[Computed]
    public function followsAnyone(): bool
    {
        return auth()->user()->follows()->exists();
    }
}; ?>

@php
    // shortcut: mirrors the layout's $links targets; Route::has switches to each real page once it exists.
    $alertsUrl = Route::has('alerts') ? route('alerts') : route('settings').'#alerts';
    $timelineUrl = Route::has('timeline') ? route('timeline') : route('influencers.index');
    $watchlistUrl = Route::has('watchlist') ? route('watchlist') : route('dashboard', ['watchlist' => 1]);
    $sharedUrl = route('collections.index', ['tab' => 'shared']);

    $everything = ! $watchlist && ! $saved && ! $this->activeCollection;
    $tab = fn (bool $on) => [
        'inline-flex items-center gap-1.5 border-b-2 px-1 pb-3 text-sm font-medium whitespace-nowrap transition',
        'border-accent text-zinc-900' => $on,
        'border-transparent text-zinc-500 hover:text-zinc-800' => ! $on,
    ];
@endphp

<div class="mx-auto max-w-6xl">
    <x-page-header icon="calendar" title="My Feed" subtitle="The latest from all influencers you follow, across all platforms.">
        @foreach ([['Alerts', 'bell-alert', $alertsUrl], ['Timeline', 'clock', $timelineUrl], ['Shared', 'share', $sharedUrl]] as [$label, $icon, $url])
            <a href="{{ $url }}" wire:navigate class="card flex w-16 flex-col items-center gap-1 px-2 py-2 text-xs text-zinc-600 transition hover:text-accent">
                <flux:icon :icon="$icon" class="size-5 text-accent" />
                {{ $label }}
            </a>
        @endforeach
    </x-page-header>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="min-w-0">
            {{-- Tabs --}}
            <nav class="flex gap-5 overflow-x-auto border-b border-zinc-200 [scrollbar-width:none]" aria-label="Feed">
                <button type="button" wire:click="scope('all')" @class($tab($everything)) aria-pressed="{{ $everything ? 'true' : 'false' }}">My Feed</button>
                <button type="button" wire:click="scope('watchlist')" @class($tab($watchlist)) aria-pressed="{{ $watchlist ? 'true' : 'false' }}">Watchlist</button>
                <flux:dropdown>
                    <button type="button" @class($tab((bool) $this->activeCollection))>
                        {{ $this->activeCollection?->name ?? 'Collections' }} <flux:icon.chevron-down class="size-3.5" />
                    </button>
                    <flux:menu>
                        @forelse ($this->collections as $item)
                            <flux:menu.item icon="folder" wire:click="scope('collection', {{ $item->id }})" wire:key="collection-{{ $item->id }}">
                                {{ $item->name }} <span class="ms-auto ps-3 text-xs text-zinc-400">{{ $item->influencers_count }}</span>
                            </flux:menu.item>
                        @empty
                            <flux:menu.item icon="plus" :href="route('collections.index')" wire:navigate>Create a collection</flux:menu.item>
                        @endforelse
                    </flux:menu>
                </flux:dropdown>
                <button type="button" wire:click="scope('saved')" @class($tab($saved)) aria-pressed="{{ $saved ? 'true' : 'false' }}">Saved</button>
            </nav>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @unless ($saved)
                    <flux:select wire:model.live="sort" size="sm" class="w-36!" aria-label="Sort">
                        <flux:select.option value="latest">Latest First</flux:select.option>
                        <flux:select.option value="smart">Smart</flux:select.option>
                    </flux:select>
                @endunless
                <flux:select wire:model.live="platform" size="sm" class="w-40!" aria-label="Platform">
                    <flux:select.option value="all">All Platforms</flux:select.option>
                    @foreach (Platform::cases() as $p)
                        <flux:select.option :value="$p->value">{{ $p->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div wire:loading.delay wire:target="platform, sort, scope, showEverything" class="mt-4 w-full space-y-4">
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

            <div wire:loading.remove wire:target="platform, sort, scope, showEverything" class="mt-4 space-y-4">
                @forelse ($this->posts as $post)
                    <x-smartlink-card :post="$post" wire:key="post-{{ $post->id }}" />
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
                        @if ($saved)
                            <flux:icon.bookmark class="mx-auto size-10 text-zinc-300" />
                            <flux:heading class="mt-3">No saved posts</flux:heading>
                            <flux:text class="mt-1">Tap the bookmark on any post to keep it here.</flux:text>
                        @elseif (! $this->followsAnyone && ! $this->activeCollection)
                            <flux:icon.inbox class="mx-auto size-10 text-zinc-300" />
                            <flux:heading class="mt-3">You don't follow anyone yet</flux:heading>
                            <flux:text class="mt-1">Follow creators and their posts from every platform show up here.</flux:text>
                            <flux:button variant="primary" size="sm" class="mt-4" :href="route('explore')" wire:navigate>Explore creators</flux:button>
                        @elseif (! $everything || $platform !== 'all')
                            <flux:icon.inbox class="mx-auto size-10 text-zinc-300" />
                            <flux:heading class="mt-3">Nothing here yet</flux:heading>
                            <flux:text class="mt-1">No posts match this filter. Try another one.</flux:text>
                            <flux:button size="sm" class="mt-4" wire:click="showEverything">Show everything</flux:button>
                        @else
                            <flux:icon.inbox class="mx-auto size-10 text-zinc-300" />
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

        {{-- Right column: below the feed on small screens --}}
        <aside class="space-y-6">
            <section class="card p-4" aria-labelledby="actions-heading">
                <flux:heading id="actions-heading" size="sm">Quick Actions</flux:heading>
                <div class="mt-3 grid grid-cols-2 gap-2 xl:grid-cols-1">
                    <flux:button size="sm" icon="user-plus" :href="route('influencers.create')" wire:navigate>Follow Influencer</flux:button>
                    <flux:button size="sm" icon="magnifying-glass" :href="route('search')" wire:navigate>Search Content</flux:button>
                </div>
            </section>

            <section class="card p-4" aria-labelledby="alerts-heading">
                <div class="flex items-center justify-between">
                    <flux:heading id="alerts-heading" size="sm">My Alerts</flux:heading>
                    <flux:link :href="$alertsUrl" wire:navigate class="text-xs">View All</flux:link>
                </div>
                @forelse ($this->alerts as $alert)
                    <a href="{{ $alertsUrl }}" wire:navigate wire:key="alert-{{ $loop->index }}" class="mt-2 flex items-center gap-3 rounded-lg px-1 py-1.5 hover:bg-zinc-50">
                        @if ($alert['platform'])
                            <x-platform-icon :platform="$alert['platform']" size="size-7" />
                        @else
                            <span class="flex size-7 items-center justify-center rounded-lg bg-accent/10"><flux:icon.bell class="size-4 text-accent" /></span>
                        @endif
                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $alert['keyword'] }}</span>
                        <span @class(['text-xs', 'font-semibold text-accent' => $alert['count'], 'text-zinc-400' => ! $alert['count']])>{{ $alert['count'] }} new {{ Str::plural('post', $alert['count']) }}</span>
                    </a>
                @empty
                    <flux:text class="mt-2 text-sm">Add keywords to get alerted when a creator you follow mentions them.</flux:text>
                @endforelse
            </section>

            <section class="card p-4" aria-labelledby="watchlist-heading">
                <div class="flex items-center justify-between">
                    <flux:heading id="watchlist-heading" size="sm">My Watchlist</flux:heading>
                    <flux:link :href="$watchlistUrl" wire:navigate class="text-xs">View All</flux:link>
                </div>
                @if ($this->watchlistInfluencers->isEmpty())
                    <flux:text class="mt-2 text-sm">Star up to 10 creators you follow to keep them here and see their posts first.</flux:text>
                @else
                    <div class="mt-3 flex -space-x-2">
                        @foreach ($this->watchlistInfluencers->take(5) as $influencer)
                            <a href="{{ route('influencers.show', $influencer) }}" wire:navigate wire:key="watch-{{ $influencer->id }}" title="{{ $influencer->name }}" class="rounded-full ring-2 ring-ivory">
                                <flux:avatar circle :src="image_url($influencer->avatar, 80, 80)" :alt="$influencer->name" />
                            </a>
                        @endforeach
                        @if ($this->watchlistInfluencers->count() > 5)
                            <a href="{{ $watchlistUrl }}" wire:navigate class="flex size-10 items-center justify-center rounded-full bg-accent/15 text-xs font-semibold text-accent ring-2 ring-ivory">+{{ $this->watchlistInfluencers->count() - 5 }}</a>
                        @endif
                    </div>
                @endif
            </section>
        </aside>
    </div>
</div>
