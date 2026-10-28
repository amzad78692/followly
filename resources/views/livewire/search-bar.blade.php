<?php

use App\Discovery\GlobalSearch;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/**
 * Search box in the dashboard top bar, with a dropdown of matching influencers, categories and countries.
 */
new class extends Component {
    public string $q = '';

    public function mount(): void
    {
        $this->q = request()->routeIs('search') ? (string) request()->query('q', '') : '';
    }

    /**
     * Dropdown results, or null while the query is too short.
     */
    #[Computed]
    public function results(): ?array
    {
        if (! GlobalSearch::searchable($this->q)) {
            return null;
        }

        $search = app(GlobalSearch::class);
        $limit = config('discovery.search.dropdown_limit');

        return [
            'influencers' => $search->influencers($this->q, $limit),
            'categories' => $search->categories($this->q, $limit),
            'countries' => $search->countries($this->q, $limit),
        ];
    }

    public function submit(): void
    {
        if (GlobalSearch::searchable($this->q)) {
            $this->redirectRoute('search', ['q' => trim($this->q)], navigate: true);
        }
    }
}; ?>

<form wire:submit="submit" method="GET" action="{{ route('search') }}" role="search" class="relative w-full max-w-md"
    x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false; $refs.input.blur()">
    <flux:input name="q" x-ref="input" wire:model.live.debounce.300ms="q" x-on:focus="open = true" x-on:input="open = true"
        icon="magnifying-glass" placeholder="Search influencers, topics or countries..." size="sm" autocomplete="off" aria-label="Search" />

    @if ($results = $this->results)
        <div x-show="open" x-cloak class="absolute inset-x-0 top-full z-20 mt-2 max-h-[70vh] overflow-y-auto rounded-xl border border-zinc-200 bg-white py-2 shadow-lg shadow-zinc-900/10">
            @if ($results['influencers']->isEmpty() && $results['categories']->isEmpty() && $results['countries']->isEmpty())
                <p class="px-4 py-3 text-sm text-zinc-500">No results for “{{ $q }}”.</p>
            @endif

            @if ($results['influencers']->isNotEmpty())
                <p class="px-4 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Influencers</p>
                @foreach ($results['influencers'] as $influencer)
                    <a href="{{ route('influencers.show', $influencer) }}" wire:navigate wire:key="search-influencer-{{ $influencer->id }}" class="flex items-center gap-3 px-4 py-2 hover:bg-zinc-50">
                        <img src="{{ $influencer->avatar }}" alt="" class="size-8 rounded-full object-cover">
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1 text-sm font-medium text-zinc-900">
                                <span class="truncate">{{ $influencer->name }}</span>
                                @if ($influencer->is_verified)
                                    <x-verified class="size-3.5" />
                                @endif
                            </span>
                            <span class="block truncate text-xs text-zinc-500">
                                {{ collect([$influencer->category?->name, $influencer->formatted_tracking.' tracking'])->filter()->implode(' · ') }}
                            </span>
                        </span>
                        <span class="flex shrink-0 gap-1">
                            @foreach ($influencer->accounts as $account)
                                <x-platform-icon :platform="$account->platform" size="size-3.5" />
                            @endforeach
                        </span>
                    </a>
                @endforeach
            @endif

            @if ($results['categories']->isNotEmpty())
                <p class="px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Categories</p>
                @foreach ($results['categories'] as $category)
                    <a href="{{ route('explore', ['tab' => 'category', 'category' => $category->slug]) }}" wire:navigate wire:key="search-category-{{ $category->id }}" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-zinc-50">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-zinc-100">{{ $category->meta['emoji'] ?? '🏷️' }}</span>
                        <span class="flex-1 font-medium text-zinc-900">{{ $category->name }}</span>
                        <span class="text-xs text-zinc-500">{{ $category->influencers_count }}</span>
                    </a>
                @endforeach
            @endif

            @if ($results['countries']->isNotEmpty())
                <p class="px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Countries</p>
                @foreach ($results['countries'] as $country)
                    <a href="{{ route('explore', ['tab' => 'country', 'country' => $country->iso2]) }}" wire:navigate wire:key="search-country-{{ $country->id }}" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-zinc-50">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-zinc-100"><flux:icon.globe-alt class="size-4 text-zinc-500" /></span>
                        <span class="flex-1 font-medium text-zinc-900">{{ $country->name }}</span>
                        <span class="text-xs text-zinc-500">{{ $country->influencers_count }}</span>
                    </a>
                @endforeach
            @endif

            <div class="mt-2 border-t border-zinc-100 px-4 pt-2">
                <a href="{{ route('search', ['q' => trim($q)]) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:underline">See all results for “{{ trim($q) }}”</a>
            </div>
        </div>
    @endif
</form>
