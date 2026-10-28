<?php

use App\Models\Influencer;
use App\Support\InfluencerLookup;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('Add Influencer')] class extends Component {
    #[Validate('required|string|max:255', as: 'link or username')]
    public string $query = '';

    #[Locked]
    public ?int $influencerId = null;

    /** Ids of the accounts to track. */
    public array $selected = [];

    public bool $searched = false;

    #[Computed]
    public function result(): ?Influencer
    {
        return $this->influencerId
            ? Influencer::with(['category', 'country', 'accounts'])->find($this->influencerId)
            : null;
    }

    #[Computed]
    public function alreadyFollowing(): bool
    {
        return $this->result && auth()->user()->isFollowing($this->result);
    }

    public function search(InfluencerLookup $lookup): void
    {
        $this->validate();

        $influencer = $lookup->find($this->query);

        $this->influencerId = $influencer?->id;
        unset($this->result, $this->alreadyFollowing);

        // Pre-tick what the user already tracks, or every account for a new follow.
        $tracked = $influencer
            ? auth()->user()->follows()->where('influencer_id', $influencer->id)->first()?->accounts()->pluck('influencer_accounts.id')
            : null;

        $this->selected = $influencer
            ? ($tracked ?? $influencer->accounts()->pluck('id'))->map(fn ($id) => (string) $id)->all()
            : [];

        $this->searched = true;
    }

    public function track()
    {
        $this->validate(
            ['selected' => 'required|array|min:1', 'selected.*' => 'integer'],
            ['selected.required' => 'Select at least one account to track.'],
        );

        abort_unless($influencer = $this->result, 404);

        auth()->user()->follow($influencer, array_map('intval', $this->selected));

        return $this->redirectRoute('influencers.show', $influencer, navigate: true);
    }

    public function cancel(): void
    {
        $this->reset();
        $this->resetValidation();
        unset($this->result, $this->alreadyFollowing);
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <flux:heading size="xl" level="1" class="font-bold!">Add Influencer</flux:heading>
    <flux:text class="mt-1">Paste a link or username and we'll find their social media accounts.</flux:text>

    <form wire:submit="search" class="mt-6 flex gap-3">
        <div class="flex-1">
            <flux:input wire:model="query" placeholder="https://www.youtube.com/@MrBeast" icon="link" />
            <flux:error name="query" />
        </div>
        <flux:button type="submit" variant="primary" class="px-6!">Search</flux:button>
    </form>

    <div wire:loading.flex wire:target="search" class="mt-6 items-center gap-2 text-sm text-zinc-500">
        <flux:icon.loading class="size-4" /> Looking for accounts…
    </div>

    @if ($result = $this->result)
        <div wire:loading.remove wire:target="search">
            <flux:heading class="mt-8 font-semibold!">We found these accounts</flux:heading>

            <div class="mt-3 rounded-2xl border border-zinc-200 bg-white p-5">
                <div class="flex items-center gap-4">
                    <img src="{{ $result->avatar }}" alt="" class="size-20 rounded-full object-cover ring-4 ring-indigo-50">
                    <div>
                        <p class="flex items-center gap-1.5 text-xl font-bold text-zinc-900">
                            {{ $result->name }}
                            @if ($result->is_verified)
                                <x-verified class="size-5" />
                            @endif
                        </p>
                        @if ($result->real_name)
                            <p class="text-sm text-zinc-500">{{ $result->real_name }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach (array_filter([$result->category?->name, $result->country?->name, $result->language]) as $badge)
                                <flux:badge size="sm">{{ $badge }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($this->alreadyFollowing)
                    <flux:callout icon="check-circle" color="indigo" class="mt-5" heading="You already follow {{ $result->name }}. Change which accounts you track below." />
                @endif

                <ul class="mt-5 divide-y divide-zinc-100 border-y border-zinc-100">
                    @foreach ($result->accounts as $account)
                        <li wire:key="acc-{{ $account->id }}">
                            <label class="flex cursor-pointer items-center gap-4 py-3">
                                <flux:checkbox wire:model.live="selected" :value="(string) $account->id" />
                                <x-platform-icon :platform="$account->platform" size="size-6" />
                                <span class="w-28 text-sm font-medium text-zinc-800">{{ $account->platform->label() }}</span>
                                <span class="flex-1 text-sm text-zinc-500">{{ $account->handle }}</span>
                                <span class="text-sm text-zinc-500">{{ $account->formatted_followers }} {{ strtolower($account->platform->unit()) }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>

                <flux:error name="selected" class="mt-3" />

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-[2fr_1fr]">
                    <flux:button variant="primary" wire:click="track" :disabled="empty($selected)">
                        {{ $this->alreadyFollowing ? 'Update Tracking' : 'Start Tracking Selected' }} ({{ count($selected) }})
                    </flux:button>
                    <flux:button wire:click="cancel">Cancel</flux:button>
                </div>
            </div>
        </div>
    @elseif ($searched)
        <div wire:loading.remove wire:target="search" class="mt-8 rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
            <flux:icon.magnifying-glass class="mx-auto size-10 text-zinc-300" />
            <flux:heading class="mt-3">No accounts found</flux:heading>
            <flux:text class="mt-1">
                We couldn't find this creator on {{ config('app.name') }} yet. Try a profile link or username, e.g.
                <span class="font-medium">@mkbhd</span> or <span class="font-medium">youtube.com/@MrBeast</span>.
            </flux:text>
        </div>
    @endif
</div>
