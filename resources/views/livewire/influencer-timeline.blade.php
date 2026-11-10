<?php

use App\Models\Influencer;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/**
 * "Timeline" tab on the influencer page: every post across platforms, newest first, grouped by
 * day. Posts in the same SmartLink group (posts.group_id) show once, with the other platforms listed.
 */
new class extends Component {
    public const PER_PAGE = 20;

    public Influencer $influencer;

    public int $limit = self::PER_PAGE;

    #[Computed]
    public function batch()
    {
        return $this->influencer->posts()->visible()->with('influencer')->latest('published_at')->limit($this->limit + 1)->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->batch->count() > $this->limit;
    }

    /**
     * Day (Y-m-d) => list of ['post' => newest post of the story, 'also' => other platforms].
     */
    #[Computed]
    public function days()
    {
        return $this->batch->take($this->limit)
            ->groupBy(fn ($post) => $post->group_id ? 'g'.$post->group_id : 'p'.$post->id)
            ->map(fn ($story) => [
                'post' => $story->first(),
                'also' => $story->skip(1)->pluck('platform')->unique(fn ($platform) => $platform->value)->reject($story->first()->platform),
            ])
            ->groupBy(fn ($item) => $item['post']->published_at->toDateString());
    }

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->limit += self::PER_PAGE;
            unset($this->batch, $this->hasMore, $this->days);
        }
    }
}; ?>

<div class="max-w-3xl space-y-8">
    @forelse ($this->days as $day => $items)
        @php($date = \Illuminate\Support\Carbon::parse($day))
        <section wire:key="day-{{ $day }}">
            <h3 class="sticky top-16 z-10 mb-3 inline-block rounded-full bg-zinc-100 px-3 py-1 text-xs font-semibold text-zinc-600">
                {{ $date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format($date->isCurrentYear() ? 'l, j F' : 'j F Y')) }}
            </h3>

            <ol class="space-y-4 border-s-2 border-zinc-200 ps-4">
                @foreach ($items as $item)
                    <li wire:key="timeline-{{ $item['post']->id }}">
                        @if ($item['also']->isNotEmpty())
                            <p class="mb-1.5 flex items-center gap-1.5 text-xs text-zinc-500">
                                <flux:icon.link class="size-3.5" /> Also posted on
                                @foreach ($item['also'] as $platform)
                                    <x-platform-icon :platform="$platform" size="size-3.5" /> {{ $platform->label() }}
                                @endforeach
                            </p>
                        @endif
                        <x-post-card :post="$item['post']" compact />
                    </li>
                @endforeach
            </ol>
        </section>
    @empty
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
            <flux:text>No posts yet.</flux:text>
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
