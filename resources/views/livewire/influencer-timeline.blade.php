<?php

use App\Enums\Platform;
use App\Models\Influencer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/**
 * One influencer's posts across platforms, newest first, grouped by day on a vertical line (the
 * Timeline page and the influencer page tab). Posts in the same SmartLink group (posts.group_id)
 * show once, with the other platforms listed. $platform narrows it to one platform.
 */
new class extends Component {
    public const PER_PAGE = 20;

    public Influencer $influencer;

    public ?string $platform = null;

    public int $limit = self::PER_PAGE;

    #[Computed]
    public function batch()
    {
        return $this->influencer->posts()->visible()->platform(Platform::tryFrom((string) $this->platform))
            ->latest('published_at')->limit($this->limit + 1)->get();
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

    /**
     * "Today, Nov 9, 2026", "Yesterday, Nov 8, 2026" or "Nov 7, 2026".
     */
    public static function dayLabel(Carbon $date): string
    {
        $prefix = $date->isToday() ? 'Today, ' : ($date->isYesterday() ? 'Yesterday, ' : '');

        return $prefix.$date->format('M j, Y');
    }

    /**
     * The stats a post has, in the platform's own words: [icon, number, label].
     */
    public static function stats($post): array
    {
        $x = $post->platform === Platform::X;
        $names = [
            'likes' => ['heart', 'likes'],
            'comments' => ['chat-bubble-left', $x ? 'replies' : 'comments'],
            'views' => ['eye', 'views'],
            'shares' => ['arrow-path-rounded-square', $x ? 'reposts' : 'shares'],
        ];

        return collect($names)
            ->filter(fn ($name, $key) => isset($post->metrics[$key]))
            ->map(fn ($name, $key) => [$name[0], Number::abbreviate($post->metrics[$key], maxPrecision: 1), $name[1]])
            ->values()->all();
    }
}; ?>

@use('Illuminate\Support\Js')

<div>
    @if ($this->days->isNotEmpty())
        {{-- The line runs behind the gold dots, from the first day to the last. --}}
        <div class="relative space-y-6 before:absolute before:top-3 before:bottom-3 before:start-[7px] before:w-0.5 before:bg-zinc-200">
            @foreach ($this->days as $day => $items)
                <section wire:key="day-{{ $day }}" class="relative ps-8">
                    <span class="absolute start-0 top-1.5 size-4 rounded-full border-4 border-zinc-50 bg-accent"></span>
                    <h3 class="inline-block rounded-full bg-sand px-3 py-1 text-xs font-semibold text-zinc-700">{{ $this->dayLabel(\Illuminate\Support\Carbon::parse($day)) }}</h3>

                    <ol class="mt-3 card divide-y divide-zinc-200/70">
                        @foreach ($items as $item)
                            @php
                                $post = $item['post'];
                                $embed = $post->embed;
                                $play = $embed ? "\$dispatch('play-post', ".Js::from($embed).')' : null;
                                $image = $post->images[0] ?? null;
                            @endphp
                            <li wire:key="timeline-{{ $post->id }}" class="flex items-start gap-3 p-3 sm:gap-4 sm:p-4">
                                @if ($image)
                                    <{{ $play ? 'button' : 'div' }} @if ($play) type="button" x-on:click="{{ $play }}" aria-label="{{ $post->duration ? 'Play video' : 'View post' }}" @endif
                                        class="group relative w-24 shrink-0 overflow-hidden rounded-lg bg-zinc-100 sm:w-36 {{ $play ? 'cursor-pointer' : '' }}">
                                        <img src="{{ image_url($image, 288, 162) }}" alt="" loading="lazy" class="aspect-video w-full object-cover transition group-hover:scale-105">
                                        @if ($post->duration)
                                            <span class="absolute bottom-1 end-1 rounded bg-black/75 px-1 py-0.5 text-[10px] font-medium text-white">{{ $post->duration }}</span>
                                        @endif
                                    </{{ $play ? 'button' : 'div' }}>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <p class="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                                        <x-platform-icon :platform="$post->platform" size="size-3.5" />
                                        <span class="font-medium text-zinc-700">{{ $post->platform->label() }}</span>
                                        <span>· {{ $post->published_at->diffForHumans() }}</span>
                                    </p>
                                    <p class="mt-1 line-clamp-2 text-sm font-semibold text-zinc-900">{{ $post->title ?: $post->body }}</p>

                                    @if ($item['also']->isNotEmpty())
                                        <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                                            <flux:icon.link class="size-3.5" /> Also posted on
                                            @foreach ($item['also'] as $platform)
                                                <x-platform-icon :platform="$platform" size="size-3.5" /> {{ $platform->label() }}
                                            @endforeach
                                        </p>
                                    @endif

                                    @if ($stats = $this->stats($post))
                                        <p class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                                            @foreach ($stats as [$icon, $number, $label])
                                                <span class="inline-flex items-center gap-1" title="{{ $number }} {{ $label }}">
                                                    <flux:icon :icon="$icon" class="size-3.5" /> {{ $number }} <span class="sr-only">{{ $label }}</span>
                                                </span>
                                            @endforeach
                                        </p>
                                    @endif
                                </div>

                                <div class="-me-1 flex shrink-0 flex-col items-center sm:flex-row">
                                    @if ($post->permalink)
                                        <flux:button variant="ghost" size="sm" icon="arrow-top-right-on-square" :href="$post->permalink" target="_blank" rel="noopener" :aria-label="'Open on '.$post->platform->label()" />
                                    @endif
                                    <flux:dropdown align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" aria-label="Post options" />
                                        <flux:menu>
                                            @if ($embed)
                                                <flux:menu.item icon="play" x-on:click="{{ $play }}">Watch here</flux:menu.item>
                                            @endif
                                            @if ($post->permalink)
                                                <flux:menu.item icon="link" x-on:click="navigator.clipboard.writeText({{ Js::from($post->permalink) }})">Copy link</flux:menu.item>
                                            @endif
                                            <flux:menu.item icon="user" :href="route('influencers.show', $influencer)" wire:navigate>View profile</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
            <flux:text>No posts yet.</flux:text>
        </div>
    @endif

    @if ($this->hasMore)
        <div wire:intersect="loadMore" class="flex justify-center py-4">
            <flux:button size="sm" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                <span wire:loading.remove wire:target="loadMore">Load more</span>
                <span wire:loading wire:target="loadMore">Loading…</span>
            </flux:button>
        </div>
    @endif
</div>
