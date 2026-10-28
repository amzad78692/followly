<?php

use App\Support\Demo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('My Influencers')] class extends Component {
    public string $search = '';

    public string $category = '';

    public string $platform = '';

    #[Computed]
    public function influencers()
    {
        return Demo::influencers()
            ->where('followed', true)
            ->when($this->search, fn ($items) => $items->filter(fn ($i) => str_contains(strtolower($i['name']), strtolower($this->search))))
            ->when($this->category, fn ($items) => $items->where('category', $this->category))
            ->when($this->platform, fn ($items) => $items->filter(fn ($i) => isset($i['platforms'][$this->platform])))
            ->values();
    }

    public function unfollow(string $slug): void
    {
        Demo::unfollow($slug);
        unset($this->influencers);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1" class="font-bold!">My Influencers</flux:heading>
            <flux:text class="mt-1">Manage the creators you're tracking.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('influencers.create')" wire:navigate>Add Influencer</flux:button>
    </div>

    <div class="mt-6 rounded-2xl border border-zinc-200 bg-white">
        <div class="flex flex-wrap items-center gap-3 border-b border-zinc-200 p-4">
            <div class="min-w-48 flex-1">
                <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" placeholder="Search my influencers..." size="sm" class="max-w-xs" />
            </div>

            <flux:select wire:model.live="category" size="sm" class="w-40!">
                <flux:select.option value="">All Categories</flux:select.option>
                @foreach (\App\Support\Demo::CATEGORIES as $item)
                    <flux:select.option :value="$item['name']">{{ $item['name'] }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="platform" size="sm" class="w-40!">
                <flux:select.option value="">All Platforms</flux:select.option>
                @foreach (\App\Support\Demo::PLATFORMS as $key => $item)
                    <flux:select.option :value="$key">{{ $item['label'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <ul class="divide-y divide-zinc-100">
            @forelse ($this->influencers as $influencer)
                <li class="flex items-center gap-4 px-4 py-3.5" wire:key="row-{{ $influencer['slug'] }}">
                    <a href="{{ route('influencers.show', $influencer['slug']) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                        <flux:avatar circle :src="$influencer['avatar_url']" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-zinc-900">{{ $influencer['name'] }}</p>
                            <p class="text-xs text-zinc-500">{{ $influencer['category'] }}</p>
                        </div>
                    </a>

                    <div class="hidden items-center gap-1.5 sm:flex">
                        @foreach (array_keys($influencer['platforms']) as $platform)
                            <x-platform-icon :platform="$platform" size="size-4" />
                        @endforeach
                    </div>

                    <flux:button size="sm" class="w-24" wire:click="unfollow('{{ $influencer['slug'] }}')">Unfollow</flux:button>

                    <flux:dropdown align="end">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item icon="user" :href="route('influencers.show', $influencer['slug'])" wire:navigate>View profile</flux:menu.item>
                            <flux:menu.item icon="bell-slash">Mute updates</flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item icon="trash" variant="danger" wire:click="unfollow('{{ $influencer['slug'] }}')">Unfollow</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </li>
            @empty
                <li class="px-4 py-14 text-center">
                    <flux:icon.users class="mx-auto size-10 text-zinc-300" />
                    <flux:heading class="mt-3">No influencers found</flux:heading>
                    <flux:text class="mt-1">Try another filter or add someone new.</flux:text>
                </li>
            @endforelse
        </ul>
    </div>
</div>
