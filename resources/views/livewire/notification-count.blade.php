<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Unread notification count for the sidebar item ("sidebar") or the top bar bell ("bell").
 * Polls every minute and refreshes when the notifications page marks something read.
 * Shows nothing when the user turned off in-app notifications in Settings.
 */
new class extends Component {
    public string $variant = 'bell';

    /** Whether the notifications page is open (sidebar highlight). Passed in, since polls run on another route. */
    public bool $current = false;

    #[Computed]
    public function unread(): int
    {
        $user = auth()->user();

        return $user->notify_in_app ? $user->unreadNotifications()->count() : 0;
    }

    #[On('notifications-read')]
    public function refresh(): void
    {
        unset($this->unread);
    }

    public function label(): ?string
    {
        return $this->unread ? ($this->unread > 99 ? '99+' : (string) $this->unread) : null;
    }
}; ?>

<div wire:poll.60s.visible>
    @if ($variant === 'sidebar')
        <flux:sidebar.item icon="bell" :href="route('notifications')" :current="$current" :badge="$this->label()" badge:color="red" wire:navigate>Notifications</flux:sidebar.item>
    @else
        <a href="{{ route('notifications') }}" wire:navigate aria-label="Notifications{{ $this->unread ? ', '.$this->unread.' unread' : '' }}"
            class="relative flex size-8 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800">
            <flux:icon.bell class="size-5" />
            @if ($this->label())
                <span class="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white">{{ $this->label() }}</span>
            @endif
        </a>
    @endif
</div>
