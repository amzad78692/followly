<?php

use App\Enums\Platform;
use App\Livewire\Concerns\TogglesFollow;
use App\Models\Influencer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/**
 * Every post from one influencer across platforms, by day. Opens on the last influencer viewed
 * (kept in the session), else the first one the user follows.
 */
new #[Layout('components.layouts.dashboard')] #[Title('Timeline')] class extends Component {
    use TogglesFollow;

    #[Url(as: 'influencer')]
    public ?int $influencerId = null;

    #[Url]
    public ?string $platform = null;

    public function mount(): void
    {
        $this->influencerId ??= session('timeline.influencer');

        if (! $this->current) {
            unset($this->current);
            $this->influencerId = $this->followed->first()?->id;
        }

        session(['timeline.influencer' => $this->influencerId]);
    }

    #[Computed]
    public function followed()
    {
        return auth()->user()->followedInfluencers()->with('media')->orderBy('name')->get();
    }

    #[Computed]
    public function current(): ?Influencer
    {
        return $this->influencerId
            ? Influencer::with(['accounts' => fn ($q) => $q->orderByRaw(Platform::orderSql())])->find($this->influencerId)
            : null;
    }

    #[Computed]
    public function follow()
    {
        return auth()->user()->follows()->where('influencer_id', $this->influencerId)->first();
    }

    public function toggleAlerts(): void
    {
        $this->follow?->update(['notify' => ! $this->follow->notify]);
    }

    protected function followToggled(): void
    {
        unset($this->follow, $this->followed);
    }
}; ?>

@php
    $influencer = $this->current;
    $follow = $this->follow;
    $chip = 'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition';
@endphp

<div class="mx-auto max-w-4xl">
    <x-page-header icon="clock" title="Followly Timeline" subtitle="View all posts from one influencer across all platforms in chronological order.">
        @if ($influencer)
            {{-- Searchable picker of followed influencers; each entry is a plain link. --}}
            <div class="relative" x-data="{ open: false, q: '' }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                <flux:button icon:trailing="chevron-down" class="max-w-60" x-on:click="open = ! open; $nextTick(() => open && $refs.q.focus())" x-bind:aria-expanded="open" aria-haspopup="true">
                    <span class="truncate">{{ $influencer->name }}</span>
                </flux:button>
                <div x-show="open" x-cloak x-transition class="absolute end-0 z-20 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-zinc-200 bg-white p-2 shadow-lg">
                    <flux:input size="sm" icon="magnifying-glass" placeholder="Search influencers" x-model="q" x-ref="q" aria-label="Search influencers" />
                    <ul class="mt-2 max-h-72 overflow-y-auto">
                        @forelse ($this->followed as $item)
                            <li wire:key="pick-{{ $item->id }}" x-show="{{ Js::from(Str::lower($item->name)) }}.includes(q.toLowerCase())">
                                <a href="{{ route('timeline', ['influencer' => $item->id]) }}" wire:navigate @class([
                                    'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-zinc-100',
                                    'bg-sand font-semibold' => $item->id === $influencer->id,
                                ])>
                                    <flux:avatar circle size="xs" :src="image_url($item->avatar, 48, 48)" />
                                    <span class="truncate">{{ $item->name }}</span>
                                </a>
                            </li>
                        @empty
                            <li class="px-2 py-1.5 text-sm text-zinc-500">You don't follow anyone yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
    </x-page-header>

    @if (! $influencer)
        <div class="mt-6 rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
            <flux:icon.clock class="mx-auto size-10 text-zinc-300" />
            <flux:heading size="lg" class="mt-3">Follow an influencer to see their timeline</flux:heading>
            <flux:text class="mt-1">Their posts from every platform appear here, in order.</flux:text>
            <flux:button variant="primary" class="mt-4" :href="route('explore')" wire:navigate>Discover influencers</flux:button>
        </div>
    @else
        {{-- Profile card --}}
        <section class="mt-6 card p-4 sm:p-6">
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('influencers.show', $influencer) }}" wire:navigate class="shrink-0">
                    <img src="{{ image_url($influencer->avatar, 160, 160) }}" alt="" class="size-16 rounded-full object-cover sm:size-20">
                </a>
                <div class="min-w-0 flex-1 basis-40">
                    <h2 class="flex items-center gap-1.5 font-display text-xl font-bold text-zinc-900">
                        <span class="truncate">{{ $influencer->name }}</span>
                        @if ($influencer->is_verified)
                            <x-verified class="size-5" />
                        @endif
                    </h2>
                    @if ($handle = $influencer->accounts->first()?->handle)
                        <p class="text-sm text-zinc-500">{{ '@'.ltrim($handle, '@') }}</p>
                    @endif
                    <p class="mt-0.5 text-sm text-zinc-700"><span class="font-semibold">{{ $influencer->formatted_reach }}</span> followers <span class="text-zinc-500">(all platforms)</span></p>
                </div>

                <div class="ms-auto flex items-center gap-2">
                    @if ($follow)
                        <flux:button icon:trailing="check" wire:click="toggle({{ $influencer->id }})">Following</flux:button>
                        <flux:button wire:click="toggleAlerts" :icon="$follow->notify ? 'bell' : 'bell-slash'"
                            class="rounded-full! border-accent! text-accent!"
                            :aria-label="$follow->notify ? 'Turn off post alerts for '.$influencer->name : 'Turn on post alerts for '.$influencer->name"
                            :aria-pressed="$follow->notify ? 'true' : 'false'" />
                    @else
                        <flux:button variant="primary" wire:click="toggle({{ $influencer->id }})">Follow</flux:button>
                    @endif
                    <flux:dropdown align="end">
                        <flux:button variant="ghost" icon="ellipsis-horizontal" aria-label="More options" />
                        <flux:menu>
                            <flux:menu.item icon="user" :href="route('influencers.show', $influencer)" wire:navigate>View profile</flux:menu.item>
                            @foreach ($influencer->accounts as $account)
                                @if ($account->url)
                                    <flux:menu.item icon="arrow-top-right-on-square" :href="$account->url" target="_blank">Open on {{ $account->platform->label() }}</flux:menu.item>
                                @endif
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>

            {{-- Platform chips double as the filter --}}
            <div class="mt-5 flex flex-wrap gap-2 border-t border-zinc-200/70 pt-4">
                @foreach ($influencer->accounts->unique(fn ($account) => $account->platform->value) as $account)
                    @php($on = $platform === $account->platform->value)
                    <button type="button" wire:click="$set('platform', '{{ $account->platform->value }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" @class([
                        $chip,
                        'border-accent bg-sand text-zinc-900' => $on,
                        'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' => ! $on,
                    ])>
                        <x-platform-icon :platform="$account->platform" size="size-4" />
                        {{ $account->platform->label() }}
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600">{{ $account->formatted_followers }}</span>
                    </button>
                @endforeach
                <button type="button" wire:click="$set('platform', null)" aria-pressed="{{ $platform ? 'false' : 'true' }}" @class([
                    $chip,
                    'border-accent bg-accent text-white' => ! $platform,
                    'border-accent bg-white text-accent hover:bg-sand' => $platform,
                ])>All Posts</button>
            </div>
        </section>

        <div class="mt-6">
            <livewire:influencer-timeline :influencer="$influencer" :platform="$platform" :key="'timeline-'.$influencer->id.'-'.$platform" />
        </div>
    @endif
</div>
