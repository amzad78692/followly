<?php

use App\Models\Follow;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard'), Title('Watchlist')] class extends Component {
    #[Computed]
    public function influencers()
    {
        return auth()->user()->watchlist()->with('media')->orderByPivot('watchlisted_at')->orderBy('influencers.id')->get()
            // shortcut: one posts query per starred influencer (at most 10).
            ->each(fn ($influencer) => $influencer->setRelation('latestPosts', $influencer->posts()->visible()
                ->latest('published_at')->limit(3)->get()->each->setRelation('influencer', $influencer)));
    }

    public function unstar(int $influencerId): void
    {
        auth()->user()->follows()->where('influencer_id', $influencerId)->update(['watchlisted_at' => null]);
        unset($this->influencers);
    }

    /**
     * Move a starred influencer one place up (-1) or down (1). The order is kept in watchlisted_at,
     * so the list is rewritten with one-second steps in the new order.
     */
    public function move(int $influencerId, int $direction): void
    {
        $follows = auth()->user()->follows()->whereNotNull('watchlisted_at')
            ->orderBy('watchlisted_at')->orderBy('influencer_id')->get()->values();
        $from = $follows->search(fn (Follow $f) => $f->influencer_id === $influencerId);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($to === false || $to < 0 || $to >= $follows->count()) {
            return;
        }

        $order = $follows->all();
        [$order[$from], $order[$to]] = [$order[$to], $order[$from]];
        $start = now()->subSeconds(count($order));
        foreach ($order as $i => $follow) {
            $follow->update(['watchlisted_at' => $start->copy()->addSeconds($i)]);
        }

        unset($this->influencers);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <x-page-header icon="star" title="Watchlist" subtitle="Your starred creators, up to 10, with their latest posts." />

    <div class="mt-6 space-y-6">
        @forelse ($this->influencers as $influencer)
            <section class="card p-4 sm:p-5" wire:key="watch-{{ $influencer->id }}" aria-labelledby="watch-name-{{ $influencer->id }}">
                <div class="flex items-center gap-3">
                    <a href="{{ route('influencers.show', $influencer) }}" wire:navigate>
                        <flux:avatar circle size="lg" :src="image_url($influencer->avatar, 96, 96)" :name="$influencer->name" />
                    </a>
                    <div class="min-w-0 flex-1">
                        <a id="watch-name-{{ $influencer->id }}" href="{{ route('influencers.show', $influencer) }}" wire:navigate class="flex items-center gap-1 font-display font-semibold text-zinc-900 hover:underline">
                            <span class="truncate">{{ $influencer->name }}</span>
                            @if ($influencer->is_verified)
                                <x-verified class="size-4" />
                            @endif
                        </a>
                        <p class="text-xs text-zinc-500">#{{ $loop->iteration }} on your Watchlist</p>
                    </div>
                    <div class="flex items-center">
                        <flux:button variant="ghost" size="sm" icon="chevron-up" wire:click="move({{ $influencer->id }}, -1)" :disabled="$loop->first" aria-label="Move {{ $influencer->name }} up" />
                        <flux:button variant="ghost" size="sm" icon="chevron-down" wire:click="move({{ $influencer->id }}, 1)" :disabled="$loop->last" aria-label="Move {{ $influencer->name }} down" />
                        <flux:button variant="ghost" size="sm" icon="star" icon:variant="solid" class="text-accent!" wire:click="unstar({{ $influencer->id }})" aria-label="Remove {{ $influencer->name }} from Watchlist" />
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($influencer->latestPosts as $post)
                        <x-post-card :post="$post" compact wire:key="watch-post-{{ $post->id }}" />
                    @empty
                        <flux:text class="col-span-full">No posts yet.</flux:text>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
                <flux:text>Your Watchlist is empty. Star a creator you follow to keep them here.</flux:text>
                <flux:button class="mt-4" variant="primary" :href="route('influencers.index')" wire:navigate>Find creators</flux:button>
            </div>
        @endforelse
    </div>
</div>
