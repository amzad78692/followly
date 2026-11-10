<?php

use App\Enums\Platform;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('Notifications')] class extends Component {
    public const PER_PAGE = 20;

    /** Filter key => notification types it shows; null shows everything else (system). */
    public const TYPES = ['posts' => ['new-post'], 'alerts' => ['keyword-alert'], 'system' => null];

    #[Url]
    public string $filter = 'all';

    public int $limit = self::PER_PAGE;

    public function updatedFilter(): void
    {
        $this->limit = self::PER_PAGE;
    }

    /**
     * The current page of notifications; one extra row is fetched to know whether more exist.
     */
    #[Computed]
    public function batch()
    {
        $types = array_merge(...array_values(array_filter(self::TYPES)));

        return ($this->filter === 'unread' ? auth()->user()->unreadNotifications() : auth()->user()->notifications())
            ->when(self::TYPES[$this->filter] ?? null, fn ($q, $only) => $q->whereIn('type', $only))
            ->when($this->filter === 'system', fn ($q) => $q->whereNotIn('type', $types))
            ->limit($this->limit + 1)
            ->get();
    }

    #[Computed]
    public function items()
    {
        return $this->batch->take($this->limit);
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->batch->count() > $this->limit;
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->limit += self::PER_PAGE;
            unset($this->batch, $this->items, $this->hasMore);
        }
    }

    /**
     * Mark one notification read and open the creator's profile.
     */
    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if (! isset($notification->data['influencer_slug'])) {
            $this->refreshList();

            return;
        }

        $this->redirectRoute('influencers.show', $notification->data['influencer_slug'], navigate: true);
    }

    public function markRead(string $id): void
    {
        auth()->user()->unreadNotifications()->whereKey($id)->update(['read_at' => now()]);

        $this->refreshList();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        $this->refreshList();
    }

    protected function refreshList(): void
    {
        unset($this->batch, $this->items, $this->hasMore, $this->unreadCount);
        $this->dispatch('notifications-read');
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1" class="font-bold!">Notifications</flux:heading>
            <flux:text class="mt-1">New posts from the creators you track, and your keyword alerts.</flux:text>
        </div>

        @if ($this->unreadCount)
            <flux:button size="sm" icon="check" wire:click="markAllRead">Mark all as read</flux:button>
        @endif
    </div>

    @unless (auth()->user()->notify_in_app)
        <flux:callout icon="bell-slash" class="mt-4">
            <flux:callout.text>
                In-app notifications are off, so the bell won't show new posts.
                <flux:callout.link :href="route('settings').'#notifications'">Change in Settings</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endunless

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach (['all' => 'All', 'unread' => 'Unread', 'posts' => 'New posts', 'alerts' => 'Alerts', 'system' => 'System'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')" @class([
                'rounded-full border px-3 py-2.5 text-sm font-medium transition',
                'border-accent bg-accent text-white shadow-sm' => $filter === $key,
                'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' => $filter !== $key,
            ])>
                {{ $label }}
                @if ($key === 'unread' && $this->unreadCount)
                    <span class="ms-1 opacity-75">{{ $this->unreadCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="mt-5 space-y-2" wire:loading.class="opacity-50" wire:target="filter, markAllRead">
        @forelse ($this->items as $notification)
            @php
                $data = $notification->data;
                $platform = Platform::tryFrom($data['platform'] ?? '');
            @endphp

            <div wire:key="notification-{{ $notification->id }}" @class([
                'group flex items-start gap-3 rounded-2xl border p-4 transition',
                'border-khaki/50 bg-sand' => $notification->unread(),
                'border-zinc-200 bg-white' => $notification->read(),
            ])>
                <button type="button" wire:click="open('{{ $notification->id }}')" class="flex min-w-0 flex-1 items-start gap-3 text-start">
                    <span class="relative shrink-0">
                        @if ($data['avatar'] ?? null)
                            <img src="{{ image_url($data['avatar'], 80, 80) }}" alt="" class="size-10 rounded-full object-cover">
                        @else
                            <span class="flex size-10 items-center justify-center rounded-full bg-zinc-100"><flux:icon.bell class="size-5 text-zinc-400" /></span>
                        @endif
                        @if ($platform)
                            <span class="absolute -bottom-1 -end-1 rounded-full bg-white p-0.5 shadow-sm">
                                <x-platform-icon :platform="$platform" size="size-3.5" />
                            </span>
                        @endif
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-sm text-zinc-700">
                            @if ($notification->type === 'keyword-alert')
                                <flux:badge size="sm" color="amber" icon="bolt" class="me-1">{{ implode(', ', $data['keywords']) }}</flux:badge>
                                <span class="font-semibold text-zinc-900">{{ $data['influencer_name'] }}</span>
                                mentioned it on {{ $platform?->label() }}
                            @elseif (! isset($data['influencer_name']))
                                <span class="font-semibold text-zinc-900">{{ $data['subject'] ?? config('app.name') }}</span>
                            @elseif (($data['count'] ?? 1) > 1)
                                <span class="font-semibold text-zinc-900">{{ $data['influencer_name'] }}</span>
                                posted {{ $data['count'] }} times on {{ $platform?->label() }}
                            @else
                                <span class="font-semibold text-zinc-900">{{ $data['influencer_name'] }}</span>
                                posted on {{ $platform?->label() }}
                            @endif
                        </span>
                        <span class="mt-0.5 line-clamp-2 block text-sm text-zinc-500">{{ ($data['title'] ?? $data['message'] ?? null) ?: 'New post' }}</span>
                        <span class="mt-1 block text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>

                    @if ($data['image'] ?? null)
                        <img src="{{ image_url($data['image'], 192, 112) }}" alt="" class="hidden h-14 w-24 shrink-0 rounded-lg object-cover sm:block">
                    @endif
                </button>

                <div class="flex shrink-0 items-center gap-1">
                    @if ($data['permalink'] ?? null)
                        <flux:button variant="ghost" size="sm" icon="arrow-top-right-on-square" :href="$data['permalink']" target="_blank" aria-label="Open on {{ $platform?->label() }}" />
                    @endif
                    @if ($notification->unread())
                        <flux:button variant="ghost" size="sm" icon="check" wire:click="markRead('{{ $notification->id }}')" aria-label="Mark as read" />
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-14 text-center">
                <flux:icon.bell class="mx-auto size-10 text-zinc-300" />
                <flux:heading class="mt-3">{{ $filter === 'unread' ? "You're all caught up" : 'No notifications yet' }}</flux:heading>
                <flux:text class="mt-1">We'll let you know when the creators you track post something new.</flux:text>
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
</div>
