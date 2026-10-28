<?php

use App\Livewire\Concerns\TogglesFollow;
use App\Models\Influencer;
use App\Models\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('Explore')] class extends Component {
    use TogglesFollow;

    public const TABS = [
        'trending' => 'Trending',
        'category' => 'By Category',
        'country' => 'By Country',
        'language' => 'By Language',
        'recommended' => 'Recommended',
    ];

    public const CATEGORY_COLORS = ['bg-sky-50', 'bg-rose-50', 'bg-emerald-50', 'bg-indigo-50', 'bg-amber-50', 'bg-violet-50'];

    #[Url]
    public string $tab = 'trending';

    /** Category slug. */
    #[Url]
    public ?string $category = null;

    public bool $showAll = false;

    #[Computed]
    public function categories()
    {
        return Taxonomy::where('type', 'category')->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function activeCategory(): ?Taxonomy
    {
        return $this->category ? $this->categories->firstWhere('slug', $this->category) : null;
    }

    /**
     * Sections of influencers for the active tab: [heading => influencers].
     */
    #[Computed]
    public function sections()
    {
        $query = fn () => Influencer::query()
            ->with(['category', 'country', 'accounts'])
            ->when($this->activeCategory, fn (Builder $q, Taxonomy $category) => $q->where('category_id', $category->id))
            ->mostTracked();

        return match ($this->tab) {
            'category' => $query()->whereNotNull('category_id')->limit(100)->get()->groupBy('category.name')->sortKeys(),
            'country' => $query()->whereNotNull('country_id')->limit(100)->get()->groupBy('country.name')->sortKeys(),
            'language' => $query()->whereNotNull('language')->limit(100)->get()->groupBy('language')->sortKeys(),
            'recommended' => collect(['Recommended for you' => $this->recommended($query())]),
            // Ranked by Followly followers until the trending score lands (Phase 7).
            default => collect(['Trending Now' => $query()->limit($this->showAll ? 24 : 4)->get()]),
        };
    }

    /**
     * Creators in the user's most-followed categories that they don't follow yet.
     */
    protected function recommended(Builder $query)
    {
        $categoryIds = Influencer::whereIn('id', $this->followedIds)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderByRaw('count(*) desc')
            ->pluck('category_id');

        return $query
            ->whereNotIn('id', $this->followedIds)
            ->when($categoryIds->isNotEmpty(), fn (Builder $q) => $q->whereIn('category_id', $categoryIds))
            ->limit(12)
            ->get();
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'trending';
        $this->category = null;
    }

    public function browse(string $slug): void
    {
        $this->tab = 'category';
        $this->category = $slug;
    }

    protected function followToggled(): void
    {
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

    @if ($this->activeCategory)
        <div class="mt-4">
            <flux:badge color="indigo">
                {{ $this->activeCategory->name }}
                <flux:badge.close wire:click="$set('category', null)" />
            </flux:badge>
        </div>
    @endif

    <div wire:loading.class="opacity-50" wire:target="setTab, browse, category, showAll" class="transition-opacity">
        @forelse ($this->sections as $heading => $influencers)
            <section class="mt-8" wire:key="section-{{ $tab }}-{{ $heading }}">
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
                        <x-influencer-card :influencer="$influencer" :followed="in_array($influencer->id, $this->followedIds)" wire:key="card-{{ $tab }}-{{ $heading }}-{{ $influencer->id }}" />
                    @empty
                        <flux:text class="col-span-full">
                            @if ($tab === 'recommended')
                                You already follow everyone we would recommend. 🎉
                            @else
                                No creators here yet.
                            @endif
                        </flux:text>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="mt-8 rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-14 text-center">
                <flux:icon.sparkles class="mx-auto size-10 text-zinc-300" />
                <flux:heading class="mt-3">No creators here yet</flux:heading>
                <flux:text class="mt-1">Be the first to add one.</flux:text>
                <flux:button variant="primary" size="sm" class="mt-4" :href="route('influencers.create')" wire:navigate>Add Influencer</flux:button>
            </div>
        @endforelse
    </div>

    @if ($this->categories->isNotEmpty())
        <section class="mt-10">
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="font-bold!">Browse by Category</flux:heading>
                <button type="button" wire:click="setTab('category')" class="text-sm font-medium text-indigo-600 hover:underline">See All</button>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-4 sm:grid-cols-6">
                @foreach ($this->categories as $item)
                    <button type="button" wire:click="browse('{{ $item->slug }}')" class="group text-center" wire:key="cat-{{ $item->id }}">
                        <span class="mx-auto flex size-20 items-center justify-center rounded-2xl border border-zinc-200 text-4xl transition group-hover:-translate-y-0.5 group-hover:shadow-md {{ $this::CATEGORY_COLORS[$loop->index % count($this::CATEGORY_COLORS)] }}">
                            {{ $item->meta['emoji'] ?? '🏷️' }}
                        </span>
                        <span class="mt-2 block text-sm font-medium text-zinc-700">{{ $item->name }}</span>
                    </button>
                @endforeach
            </div>
        </section>
    @endif
</div>
