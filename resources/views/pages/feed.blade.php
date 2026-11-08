<?php

use App\Enums\Platform;
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

    public string $sort = 'latest';

    public int $limit = self::PER_PAGE;

    public function updated(string $property): void
    {
        if (in_array($property, ['platform', 'sort'])) {
            $this->limit = self::PER_PAGE;
        }
    }

    /**
     * The current page of posts; one extra row is fetched to know whether more exist.
     */
    #[Computed]
    public function batch()
    {
        return Post::feedFor(auth()->user())
            ->platform(Platform::tryFrom($this->platform))
            ->when($this->sort === 'oldest', fn ($query) => $query->reorder('published_at'))
            ->with('influencer')
            ->limit($this->limit + 1)
            ->get();
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
}; ?>

<div class="mx-auto max-w-3xl">
    <flux:heading size="xl" level="1" class="font-bold!">My Feed</flux:heading>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @foreach (['all' => 'All Updates'] + collect(\App\Enums\Platform::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all() as $key => $label)
            <button type="button" wire:click="$set('platform', '{{ $key }}')" @class([
                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                'border-accent bg-accent text-white shadow-sm' => $platform === $key,
                'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' => $platform !== $key,
            ])>
                @if ($key !== 'all')
                    <x-platform-icon :platform="$key" size="size-4" />
                @endif
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mt-4 flex justify-end">
        <flux:select wire:model.live="sort" size="sm" class="max-w-32">
            <flux:select.option value="latest">Latest</flux:select.option>
            <flux:select.option value="oldest">Oldest</flux:select.option>
        </flux:select>
    </div>

    <div wire:loading.delay wire:target="platform, sort" class="mt-3 w-full space-y-4">
        @foreach (range(1, 3) as $i)
            <flux:skeleton.group animate="shimmer" class="rounded-2xl border border-zinc-200 bg-white p-5">
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

    <div wire:loading.remove wire:target="platform, sort" class="mt-3 space-y-8">
        @forelse ($this->posts->groupBy('influencer_id') as $posts)
            @php($author = $posts->first()->influencer)
            {{-- Keyed by filter so changing it starts again from the first post. --}}
            <section wire:key="influencer-{{ $author->id }}-{{ $platform }}-{{ $sort }}"
                x-data="{ slide(direction) { $refs.slider.scrollBy({ left: direction * $refs.slider.clientWidth * (document.dir === 'rtl' ? -1 : 1), behavior: 'smooth' }) } }">
                <div class="mb-3 flex items-center gap-2">
                    <a href="{{ route('influencers.show', $author) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-2">
                        <flux:avatar circle size="sm" :src="image_url($author->avatar, 80, 80)" />
                        <flux:heading class="truncate">{{ $author->name }}</flux:heading>
                    </a>
                    @if ($posts->count() > 1)
                        <flux:button size="sm" icon="chevron-left" class="rtl:rotate-180" x-on:click="slide(-1)" aria-label="Previous post by {{ $author->name }}" />
                        <flux:button size="sm" icon="chevron-right" class="rtl:rotate-180" x-on:click="slide(1)" aria-label="Next post by {{ $author->name }}" />
                    @endif
                </div>

                <div x-ref="slider" class="flex snap-x snap-mandatory items-start gap-4 overflow-x-auto [scrollbar-width:none]">
                    @foreach ($posts as $post)
                        <x-post-card :post="$post" class="w-full shrink-0 snap-start" wire:key="post-{{ $post->id }}" />
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
                <flux:icon.inbox class="mx-auto size-10 text-zinc-300" />
                <flux:heading class="mt-3">No updates yet</flux:heading>
                <flux:text class="mt-1">
                    @if ($platform !== 'all')
                        Nothing from {{ \App\Enums\Platform::tryFrom($platform)?->label() }} yet. Try another platform.
                    @else
                        Follow more creators to fill your feed.
                    @endif
                </flux:text>
                <flux:button variant="primary" size="sm" class="mt-4" :href="route('explore')" wire:navigate>Explore creators</flux:button>
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
