<?php

use App\Models\Influencer;
use App\Platforms\Data\AccountData;
use App\Platforms\Exceptions\PlatformException;
use App\Support\InfluencerImporter;
use App\Support\InfluencerLookup;
use Illuminate\Support\Collection;
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

    /** Accounts the drivers found that are not stored yet, keyed by platform. */
    #[Locked]
    public array $candidates = [];

    /** Ids of stored accounts and "new:<platform>" keys of candidates to track. */
    public array $selected = [];

    public bool $searched = false;

    /** The landing page's form sends its link here as ?q=. */
    public function mount(): void
    {
        $this->query = (string) request()->query('q', '');
    }

    #[Computed]
    public function result(): ?Influencer
    {
        return $this->influencerId
            ? Influencer::with(['category', 'country', 'accounts'])->find($this->influencerId)
            : null;
    }

    /**
     * @return Collection<string, AccountData>
     */
    #[Computed]
    public function found(): Collection
    {
        return collect($this->candidates)->map(fn (array $account) => AccountData::fromArray($account));
    }

    #[Computed]
    public function alreadyFollowing(): bool
    {
        return $this->result && auth()->user()->isFollowing($this->result);
    }

    public function search(InfluencerLookup $lookup): void
    {
        $this->validate();
        $this->reset('influencerId', 'candidates', 'selected');
        unset($this->result, $this->found, $this->alreadyFollowing);

        try {
            $found = $lookup->lookup($this->query);
        } catch (PlatformException $e) {
            report($e);
            $this->addError('query', "We couldn't reach {$e->platform->label()} right now. Please try again in a minute.");

            return;
        }

        $influencer = $found->influencer;
        $this->influencerId = $influencer?->id;
        $this->candidates = $found->candidates->map->toArray()->all();

        // Pre-tick what the user already tracks, or every account for a new follow.
        $tracked = $influencer
            ? auth()->user()->follows()->where('influencer_id', $influencer->id)->first()?->accounts()->pluck('influencer_accounts.id')
            : null;

        $this->selected = collect($influencer ? ($tracked ?? $influencer->accounts()->pluck('id')) : [])
            ->map(fn ($id) => (string) $id)
            // Same-username matches are guesses; the user ticks them if they are right.
            ->merge($found->candidates->reject(fn (AccountData $account) => $account->source === 'handle')->keys()->map(fn ($platform) => "new:{$platform}"))
            ->values()
            ->all();

        $this->searched = true;
    }

    public function track(InfluencerImporter $importer)
    {
        $this->validate(
            ['selected' => 'required|array|min:1', 'selected.*' => 'string'],
            ['selected.required' => 'Select at least one account to track.'],
        );

        [$new, $ids] = collect($this->selected)->partition(fn (string $key) => str_starts_with($key, 'new:'));
        $new = $this->found->only($new->map(fn (string $key) => substr($key, 4))->all());

        abort_unless($this->result || $new->isNotEmpty(), 404);

        $influencer = $new->isNotEmpty()
            ? $importer->import($new, $this->result, auth()->user())
            : $this->result;

        $ids = $ids->map('intval')->merge(
            $influencer->accounts->filter(fn ($account) => $new->has($account->platform->value))->pluck('id')
        );

        auth()->user()->follow($influencer, $ids->all());

        return $this->redirectRoute('influencers.show', $influencer, navigate: true);
    }

    public function cancel(): void
    {
        $this->reset();
        $this->resetValidation();
        unset($this->result, $this->found, $this->alreadyFollowing);
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

    @if ($this->result || $this->found->isNotEmpty())
        @php
            $result = $this->result;
            $primary = $this->found->first();
        @endphp
        <div wire:loading.remove wire:target="search">
            <flux:heading class="mt-8 font-semibold!">We found these accounts</flux:heading>

            <div class="mt-3 rounded-2xl border border-zinc-200 bg-white p-5">
                <div class="flex items-center gap-4">
                    <img src="{{ $result?->avatar ?? $primary->avatarUrl ?? 'https://ui-avatars.com/api/?background=f9f3e5&color=91754e&name='.urlencode($primary->name) }}" alt="" class="size-20 rounded-full object-cover ring-4 ring-sand">
                    <div class="min-w-0">
                        <p class="flex items-center gap-1.5 text-xl font-bold text-zinc-900">
                            {{ $result?->name ?? $primary->name }}
                            @if ($result?->is_verified ?? $primary->isVerified)
                                <x-verified class="size-5" />
                            @endif
                        </p>
                        @if ($result?->real_name)
                            <p class="text-sm text-zinc-500">{{ $result->real_name }}</p>
                        @elseif (! $result && $primary->bio)
                            <p class="line-clamp-2 text-sm text-zinc-500">{{ $primary->bio }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @if (! $result)
                                <flux:badge size="sm">New on {{ config('app.name') }}</flux:badge>
                            @endif
                            @foreach (array_filter([$result?->category?->name, $result?->country?->name, $result?->language]) as $badge)
                                <flux:badge size="sm">{{ $badge }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($this->alreadyFollowing)
                    <flux:callout icon="check-circle" class="mt-5" heading="You already follow {{ $result->name }}. Change which accounts you track below." />
                @endif

                <ul class="mt-5 divide-y divide-zinc-100 border-y border-zinc-100">
                    @foreach ($result?->accounts ?? [] as $account)
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

                    @foreach ($this->found as $platform => $account)
                        <li wire:key="new-{{ $platform }}">
                            <label class="flex cursor-pointer items-center gap-4 py-3">
                                <flux:checkbox wire:model.live="selected" value="new:{{ $platform }}" />
                                <x-platform-icon :platform="$account->platform" size="size-6" />
                                <span class="w-28 text-sm font-medium text-zinc-800">{{ $account->platform->label() }}</span>
                                <span class="flex min-w-0 flex-1 flex-wrap items-center gap-2 text-sm text-zinc-500">
                                    <a href="{{ $account->url }}" target="_blank" rel="noopener" class="hover:underline">{{ $account->displayHandle() }}</a>
                                    @if ($account->source === 'link')
                                        <flux:badge size="sm" color="green">Linked from their profile</flux:badge>
                                    @elseif ($account->source === 'handle')
                                        <flux:badge size="sm" color="amber">Same username, check it's them</flux:badge>
                                    @endif
                                </span>
                                <span class="text-sm text-zinc-500">{{ \Illuminate\Support\Number::abbreviate($account->followers, maxPrecision: 1) }} {{ strtolower($account->platform->unit()) }}</span>
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
                We couldn't find this creator. Try a profile link or username, e.g.
                <span class="font-medium">@mkbhd</span> or <span class="font-medium">youtube.com/@MrBeast</span>.
            </flux:text>
        </div>
    @endif
</div>
