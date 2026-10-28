<?php

use App\Support\Demo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('My Feed')] class extends Component {
    #[Url]
    public string $platform = 'all';

    public string $sort = 'latest';

    #[Computed]
    public function posts()
    {
        $followed = Demo::followed();

        return Demo::posts()
            ->filter(fn ($post) => in_array($post['influencer'], $followed))
            ->when($this->platform !== 'all', fn ($posts) => $posts->where('platform', $this->platform))
            ->when($this->sort === 'oldest', fn ($posts) => $posts->sortBy('posted_at'), fn ($posts) => $posts->sortByDesc('posted_at'))
            ->values();
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <flux:heading size="xl" level="1" class="font-bold!">My Feed</flux:heading>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @foreach (['all' => 'All Updates'] + collect(\App\Support\Demo::PLATFORMS)->map->label->all() + ['other' => 'Other'] as $key => $label)
            <button type="button" wire:click="$set('platform', '{{ $key }}')" @class([
                'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition',
                'border-indigo-600 bg-indigo-600 text-white shadow-sm' => $platform === $key,
                'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' => $platform !== $key,
            ])>
                @if (isset(\App\Support\Demo::PLATFORMS[$key]))
                    <x-platform-icon :platform="$key" size="size-4" />
                @elseif ($key === 'other')
                    <flux:icon.globe-alt class="size-4" />
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

    <div class="mt-3 space-y-4">
        @forelse ($this->posts as $post)
            <x-post-card :post="$post" wire:key="post-{{ $post['id'] }}" />
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center">
                <flux:icon.inbox class="mx-auto size-10 text-zinc-300" />
                <flux:heading class="mt-3">No updates yet</flux:heading>
                <flux:text class="mt-1">Follow more creators to fill your feed.</flux:text>
                <flux:button variant="primary" size="sm" class="mt-4" :href="route('explore')" wire:navigate>Explore creators</flux:button>
            </div>
        @endforelse
    </div>
</div>
