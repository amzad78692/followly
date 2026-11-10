<?php

use App\Discovery\GlobalSearch;
use App\Livewire\Concerns\TogglesFollow;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('Search')] class extends Component {
    use TogglesFollow;

    public const PER_PAGE = 24;

    public const TABS = ['all' => 'All', 'influencers' => 'Influencers', 'posts' => 'Posts', 'topics' => 'Categories & Countries'];

    #[Url]
    public string $q = '';

    #[Url]
    public string $tab = 'all';

    public int $limit = self::PER_PAGE;

    #[Computed]
    public function searchable(): bool
    {
        return GlobalSearch::searchable($this->q);
    }

    /**
     * Matching influencers; one extra row is fetched to know whether more exist.
     */
    #[Computed]
    public function batch()
    {
        return $this->searchable ? app(GlobalSearch::class)->influencers($this->q, $this->limit + 1) : collect();
    }

    #[Computed]
    public function influencers()
    {
        return $this->batch->take($this->limit);
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->batch->count() > $this->limit;
    }

    #[Computed]
    public function categories()
    {
        return $this->searchable ? app(GlobalSearch::class)->categories($this->q, 12) : collect();
    }

    #[Computed]
    public function countries()
    {
        return $this->searchable ? app(GlobalSearch::class)->countries($this->q, 12) : collect();
    }

    #[Computed]
    public function posts()
    {
        return $this->searchable ? app(GlobalSearch::class)->posts($this->q, self::PER_PAGE) : collect();
    }

    /**
     * Whether a section shows on the active tab.
     */
    public function shows(string $section): bool
    {
        return in_array($this->tab, ['all', $section]);
    }

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->limit += self::PER_PAGE;
            unset($this->batch, $this->influencers, $this->hasMore);
        }
    }

    protected function followToggled(): void
    {
        unset($this->batch, $this->influencers);
    }
}; ?>

<div class="mx-auto max-w-6xl">
    <flux:heading size="xl" level="1" class="font-bold!">
        @if ($this->searchable)
            Results for “{{ trim($q) }}”
        @else
            Search
        @endif
    </flux:heading>

    @if (! $this->searchable)
        <flux:text class="mt-1">Type at least {{ config('discovery.search.min_length') }} characters in the search box to find influencers, categories and countries.</flux:text>
    @elseif ($this->influencers->isEmpty() && $this->posts->isEmpty() && $this->categories->isEmpty() && $this->countries->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-14 text-center">
            <flux:icon.magnifying-glass class="mx-auto size-10 text-zinc-300" />
            <flux:heading class="mt-3">Nothing matches “{{ trim($q) }}”</flux:heading>
            <flux:text class="mt-1">Try another name or handle, or add the creator yourself.</flux:text>
            <flux:button variant="primary" size="sm" class="mt-4" :href="route('influencers.create')" wire:navigate>Add Influencer</flux:button>
        </div>
    @else
        <nav class="mt-5 flex flex-wrap gap-2">
            @foreach ($this::TABS as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                    'rounded-full border px-4 py-1.5 text-sm font-medium transition',
                    'border-accent bg-accent text-white shadow-sm' => $tab === $key,
                    'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' => $tab !== $key,
                ])>{{ $label }}</button>
            @endforeach
        </nav>

        @if ($this->shows('topics') && ($this->categories->isNotEmpty() || $this->countries->isNotEmpty()))
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($this->categories as $category)
                    <a href="{{ route('explore', ['tab' => 'category', 'category' => $category->slug]) }}" wire:navigate wire:key="category-{{ $category->id }}"
                        class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                        {{ $category->meta['emoji'] ?? '🏷️' }} {{ $category->name }}
                        <span class="text-xs text-zinc-400">{{ $category->influencers_count }}</span>
                    </a>
                @endforeach
                @foreach ($this->countries as $country)
                    <a href="{{ route('explore', ['tab' => 'country', 'country' => $country->iso2]) }}" wire:navigate wire:key="country-{{ $country->id }}"
                        class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                        <flux:icon.globe-alt class="size-4 text-zinc-400" /> {{ $country->name }}
                        <span class="text-xs text-zinc-400">{{ $country->influencers_count }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($tab === 'topics' &&$this->categories->isEmpty() && $this->countries->isEmpty())
            <flux:text class="mt-6">No categories or countries match “{{ trim($q) }}”.</flux:text>
        @endif

        @if ($this->shows('posts') && ($tab === 'posts' || $this->posts->isNotEmpty()))
            <section class="mt-8">
                <flux:heading size="lg" class="font-bold!">Posts</flux:heading>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @forelse ($tab === 'posts' ? $this->posts : $this->posts->take(4) as $post)
                        <x-post-card :post="$post" compact wire:key="post-{{ $post->id }}" />
                    @empty
                        <flux:text class="col-span-full">No posts mention “{{ trim($q) }}”.</flux:text>
                    @endforelse
                </div>
                @if ($tab === 'all' && $this->posts->count() > 4)
                    <button type="button" wire:click="$set('tab', 'posts')" class="mt-3 text-sm font-medium text-accent hover:underline">See all posts</button>
                @endif
            </section>
        @endif

        @if ($this->shows('influencers'))
        <section class="mt-8">
            <flux:heading size="lg" class="font-bold!">Influencers</flux:heading>

            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                @forelse ($this->influencers as $influencer)
                    <x-influencer-card :influencer="$influencer" :followed="in_array($influencer->id, $this->followedIds)" wire:key="card-{{ $influencer->id }}" />
                @empty
                    <flux:text class="col-span-full">No influencers match “{{ trim($q) }}”.</flux:text>
                @endforelse
            </div>

            @if ($this->hasMore)
                <div wire:intersect="loadMore" class="flex justify-center py-6">
                    <flux:button size="sm" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                        <span wire:loading.remove wire:target="loadMore">Load more</span>
                        <span wire:loading wire:target="loadMore">Loading…</span>
                    </flux:button>
                </div>
            @endif
        </section>
        @endif
    @endif
</div>
