<?php

use App\Livewire\Concerns\TogglesFollow;
use App\Models\Collection;
use Livewire\Volt\Component;

/**
 * Public view of a shared collection. Shows only the collection name and influencer cards, never owner details.
 */
new class extends Component {
    use TogglesFollow;

    public Collection $collection;

    public function mount(string $token): void
    {
        $this->collection = Collection::where('share_token', $token)->firstOrFail();
    }

    public function rendering($view): void
    {
        $view->layout(auth()->check() ? 'components.layouts.dashboard' : 'components.layouts.public')
            ->title($this->collection->name);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <flux:heading size="xl" class="font-bold!">{{ $collection->name }}</flux:heading>
    <flux:text class="mt-1">{{ $collection->description ?: 'A shared collection on '.config('app.name').'.' }}</flux:text>

    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
        @forelse ($collection->influencers()->with(['category', 'country', 'accounts'])->get() as $influencer)
            <x-influencer-card :influencer="$influencer" :followed="in_array($influencer->id, $this->followedIds)" wire:key="shared-{{ $influencer->id }}" />
        @empty
            <flux:text class="col-span-full">This collection is empty.</flux:text>
        @endforelse
    </div>
</div>
