<?php

use App\Models\Collection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/**
 * Watchlist star and "add to collection" menu for an influencer card or profile.
 */
new class extends Component {
    public const WATCHLIST_LIMIT = 10;

    public int $influencerId;

    public ?string $error = null;

    #[Computed]
    public function follow()
    {
        return auth()->user()->follows()->where('influencer_id', $this->influencerId)->first();
    }

    #[Computed]
    public function collections()
    {
        return auth()->user()->collections()->orderBy('name')
            ->withExists(['influencers as has_influencer' => fn ($q) => $q->whereKey($this->influencerId)])
            ->get();
    }

    public function toggleWatchlist(): void
    {
        $this->error = null;
        unset($this->follow);
        $follow = $this->follow;

        if (! $follow) {
            $this->error = 'Follow this influencer to add them to your Watchlist.';

            return;
        }

        if ($follow->watchlisted_at) {
            $follow->update(['watchlisted_at' => null]);

            return;
        }

        if (auth()->user()->watchlist()->count() >= self::WATCHLIST_LIMIT) {
            $this->error = 'Your Watchlist is full ('.self::WATCHLIST_LIMIT.'). Remove a star to add another.';

            return;
        }

        $follow->update(['watchlisted_at' => now()]);
    }

    public function toggleCollection(int $collectionId): void
    {
        $collection = Collection::findOrFail($collectionId);
        $this->authorize('update', $collection);

        $collection->influencers()->toggle([$this->influencerId]);
        unset($this->collections);
    }
}; ?>

@php($starred = (bool) $this->follow?->watchlisted_at)
<div class="flex flex-wrap items-center justify-center gap-1">
    <flux:button variant="ghost" icon="star" :icon:variant="$starred ? 'solid' : 'outline'"
        wire:click="toggleWatchlist" :aria-label="$starred ? 'Remove from Watchlist' : 'Add to Watchlist'"
        :class="$starred ? 'text-amber-500!' : ''" />

    <flux:dropdown>
        <flux:button variant="ghost" icon="folder-plus" aria-label="Add to collection" />
        <flux:menu>
            @forelse ($this->collections as $collection)
                <flux:menu.checkbox :checked="$collection->has_influencer" wire:click="toggleCollection({{ $collection->id }})" wire:key="col-{{ $collection->id }}">{{ $collection->name }}</flux:menu.checkbox>
            @empty
                <flux:menu.item :href="route('collections.index')" wire:navigate>Create a collection</flux:menu.item>
            @endforelse
        </flux:menu>
    </flux:dropdown>

    @if ($error)
        <flux:text size="sm" class="w-full text-center text-red-600!" role="alert">{{ $error }}</flux:text>
    @endif
</div>
