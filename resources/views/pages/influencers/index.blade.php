<?php

use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('My Influencers')] class extends Component {
    public string $search = '';

    /** Category id. */
    public string $category = '';

    public string $platform = '';

    public ?int $unfollowing = null;

    #[Computed]
    public function categories()
    {
        return Taxonomy::where('type', 'category')->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * The user's follows, with the accounts they track on each.
     */
    #[Computed]
    public function follows()
    {
        return auth()->user()->follows()
            ->with(['influencer.category', 'accounts'])
            ->whereHas('influencer', fn (Builder $q) => $q
                ->search($this->search)
                ->when($this->category, fn (Builder $q) => $q->where('category_id', $this->category)))
            ->when(Platform::tryFrom($this->platform), fn (Builder $q, Platform $platform) => $q
                ->whereHas('accounts', fn (Builder $q) => $q->where('platform', $platform)))
            ->latest()
            ->get();
    }

    #[Computed]
    public function pendingUnfollow(): ?Influencer
    {
        return $this->unfollowing ? Influencer::find($this->unfollowing) : null;
    }

    public function confirmUnfollow(int $influencerId): void
    {
        $this->unfollowing = $influencerId;

        $this->modal('confirm-unfollow')->show();
    }

    public function unfollow(): void
    {
        if ($influencer = $this->pendingUnfollow) {
            auth()->user()->unfollow($influencer);
        }

        $this->unfollowing = null;
        unset($this->follows, $this->pendingUnfollow);

        $this->modal('confirm-unfollow')->close();
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
        <div class="grid grid-cols-2 gap-3 border-b border-zinc-200 p-4 sm:flex sm:flex-wrap sm:items-center">
            <div class="col-span-2 sm:min-w-48 sm:flex-1">
                <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" placeholder="Search my influencers..." size="sm" class="sm:max-w-xs" />
            </div>

            <flux:select wire:model.live="category" size="sm" class="sm:w-40!">
                <flux:select.option value="">All Categories</flux:select.option>
                @foreach ($this->categories as $item)
                    <flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="platform" size="sm" class="sm:w-40!">
                <flux:select.option value="">All Platforms</flux:select.option>
                @foreach (\App\Enums\Platform::cases() as $item)
                    <flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <ul class="divide-y divide-zinc-100 transition-opacity" wire:loading.class="opacity-50" wire:target="search, category, platform">
            @forelse ($this->follows as $follow)
                @php $influencer = $follow->influencer; @endphp
                <li class="flex items-center gap-3 px-4 py-3.5 sm:gap-4" wire:key="row-{{ $influencer->id }}">
                    <a href="{{ route('influencers.show', $influencer) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                        <flux:avatar circle :src="image_url($influencer->avatar, 80, 80)" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-zinc-900">{{ $influencer->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $influencer->category?->name ?? '—' }}</p>
                        </div>
                    </a>

                    <div class="hidden items-center gap-1.5 sm:flex">
                        @foreach ($follow->accounts as $account)
                            <x-platform-icon :platform="$account->platform" size="size-4" />
                        @endforeach
                    </div>

                    <flux:button size="sm" class="w-24" wire:click="confirmUnfollow({{ $influencer->id }})">Unfollow</flux:button>

                    <flux:dropdown align="end">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item icon="user" :href="route('influencers.show', $influencer)" wire:navigate>View profile</flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmUnfollow({{ $influencer->id }})">Unfollow</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </li>
            @empty
                <li class="px-4 py-14 text-center">
                    <flux:icon.users class="mx-auto size-10 text-zinc-300" />
                    @if ($search || $category || $platform)
                        <flux:heading class="mt-3">No influencers found</flux:heading>
                        <flux:text class="mt-1">Try another search or filter.</flux:text>
                    @else
                        <flux:heading class="mt-3">You're not tracking anyone yet</flux:heading>
                        <flux:text class="mt-1">Find creators on Explore or add one from a link.</flux:text>
                        <div class="mt-4 flex justify-center gap-2">
                            <flux:button size="sm" :href="route('explore')" wire:navigate>Explore</flux:button>
                            <flux:button size="sm" variant="primary" :href="route('influencers.create')" wire:navigate>Add Influencer</flux:button>
                        </div>
                    @endif
                </li>
            @endforelse
        </ul>
    </div>

    <flux:modal name="confirm-unfollow" class="max-w-sm">
        <flux:heading size="lg">Unfollow {{ $this->pendingUnfollow?->name }}?</flux:heading>
        <flux:text class="mt-2">Their posts will no longer appear in your feed. You can follow them again at any time.</flux:text>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="unfollow">Unfollow</flux:button>
        </div>
    </flux:modal>
</div>
