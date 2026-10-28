<?php

use App\Support\Demo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('Explore')] class extends Component {
    public const TABS = [
        'trending' => 'Trending',
        'category' => 'By Category',
        'country' => 'By Country',
        'language' => 'By Language',
        'recommended' => 'Recommended',
    ];

    #[Url]
    public string $tab = 'trending';

    #[Url]
    public ?string $category = null;

    public bool $showAll = false;

    /**
     * Sections of influencers for the active tab: [heading => influencers].
     */
    #[Computed]
    public function sections()
    {
        $influencers = Demo::influencers()
            ->when($this->category, fn ($items) => $items->where('category', $this->category))
            ->sortByDesc(fn ($i) => (float) $i['tracking']);

        return match ($this->tab) {
            'category', 'country', 'language' => $influencers->groupBy($this->tab)->sortKeys(),
            'recommended' => collect(['Recommended for you' => $influencers->reject->followed]),
            default => collect([
                'Trending Now' => $this->showAll ? $influencers : $influencers->take(4),
            ]),
        };
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->category = null;
    }

    public function browse(string $category): void
    {
        $this->tab = 'category';
        $this->category = $category;
    }

    public function toggle(string $slug): void
    {
        Demo::toggle($slug);
        unset($this->sections);
    }
}; ?>

<div class="mx-auto max-w-6xl">
    <flux:heading size="xl" level="1" class="font-bold!">Explore Influencers</flux:heading>
    <flux:text class="mt-1">Discover trending creators from around the world.</flux:text>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach ($this::TABS as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" @class([
                'rounded-lg border px-4 py-1.5 text-sm font-medium transition',
                'border-indigo-600 bg-indigo-600 text-white shadow-sm' => $tab === $key,
                'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($category)
        <div class="mt-4">
            <flux:badge color="indigo">
                {{ $category }}
                <flux:badge.close wire:click="$set('category', null)" />
            </flux:badge>
        </div>
    @endif

    @foreach ($this->sections as $heading => $influencers)
        <section class="mt-8" wire:key="section-{{ $heading }}">
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="font-bold!">{{ $heading }}</flux:heading>
                @if ($tab === 'trending')
                    <button type="button" wire:click="$toggle('showAll')" class="text-sm font-medium text-indigo-600 hover:underline">
                        {{ $showAll ? 'Show Less' : 'See All' }}
                    </button>
                @endif
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                @forelse ($influencers as $influencer)
                    <x-influencer-card :influencer="$influencer" wire:key="card-{{ $heading }}-{{ $influencer['slug'] }}" />
                @empty
                    <flux:text class="col-span-full">You already follow everyone we would recommend. 🎉</flux:text>
                @endforelse
            </div>
        </section>
    @endforeach

    <section class="mt-10">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" class="font-bold!">Browse by Category</flux:heading>
            <button type="button" wire:click="setTab('category')" class="text-sm font-medium text-indigo-600 hover:underline">See All</button>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-4 sm:grid-cols-6">
            @foreach (\App\Support\Demo::CATEGORIES as $item)
                <button type="button" wire:click="browse('{{ $item['name'] }}')" class="group text-center">
                    <span class="mx-auto flex size-20 items-center justify-center rounded-2xl border border-zinc-200 text-4xl transition group-hover:-translate-y-0.5 group-hover:shadow-md {{ $item['bg'] }}">
                        {{ $item['emoji'] }}
                    </span>
                    <span class="mt-2 block text-sm font-medium text-zinc-700">{{ $item['name'] }}</span>
                </button>
            @endforeach
        </div>
    </section>
</div>
