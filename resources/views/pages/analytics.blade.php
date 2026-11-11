<?php

use App\Analytics\CreatorReport;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('Creator Analytics')] class extends Component {
    public const TABS = ['creators' => 'My Creators', 'top' => 'Top Performers', 'trends' => 'Activity Trends'];

    #[Url]
    public string $tab = 'creators';

    #[Url]
    public int $days = 30;

    #[Url]
    public ?int $influencer = null;

    public string $metric = 'views';

    /**
     * Only creators the user follows can be reported on.
     */
    #[Computed]
    public function followed()
    {
        return auth()->user()->followedInfluencers()->with('accounts')->orderBy('name')->get();
    }

    #[Computed]
    public function creator()
    {
        return $this->followed->firstWhere('id', $this->influencer) ?? $this->followed->first();
    }

    /**
     * Every followed creator together (Top Performers and Activity Trends).
     */
    #[Computed]
    public function report(): CreatorReport
    {
        return new CreatorReport($this->followed->modelKeys(), $this->range());
    }

    public function range(): int
    {
        return in_array($this->days, CreatorReport::RANGES) ? $this->days : 30;
    }
}; ?>

@use('Illuminate\Support\Number')

@php $days = $this->range(); @endphp

<div class="mx-auto max-w-7xl space-y-6 p-4 lg:p-8">
    <x-page-header icon="chart-bar" title="Creator Analytics" subtitle="Insights and activity trends for the influencers you follow." />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap gap-2" aria-label="Analytics views">
            @foreach ($this::TABS as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                    'rounded-full px-4 py-2 text-sm font-medium transition',
                    'bg-accent text-white shadow-sm' => $tab === $key,
                    'bg-white text-zinc-600 ring-1 ring-zinc-200 hover:text-zinc-900' => $tab !== $key,
                ]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</button>
            @endforeach
        </nav>

        <div class="flex flex-wrap items-center gap-2">
            @if ($tab === 'creators' && $this->followed->count() > 1)
                <div class="w-48"><flux:select wire:model.live="influencer" size="sm" aria-label="Creator">
                    @foreach ($this->followed as $item)
                        <flux:select.option :value="$item->id" :selected="$item->id === $this->creator->id">{{ $item->name }}</flux:select.option>
                    @endforeach
                </flux:select></div>
            @endif
            <div class="w-36"><flux:select wire:model.live="days" size="sm" aria-label="Date range">
                @foreach (CreatorReport::RANGES as $range)
                    <flux:select.option :value="$range">Last {{ $range }} Days</flux:select.option>
                @endforeach
            </flux:select></div>
        </div>
    </div>

    @if ($this->followed->isEmpty())
        <div class="card px-6 py-16 text-center">
            <flux:heading size="lg">No creators yet</flux:heading>
            <flux:text class="mt-1">Follow some influencers to see their analytics here.</flux:text>
            <flux:button variant="primary" class="mt-4" :href="route('explore')" wire:navigate>Discover creators</flux:button>
        </div>
    @elseif ($tab === 'top')
        @php
            $rows = $this->report->perCreator();
            $creators = $this->followed->keyBy('id');
        @endphp
        <section class="card overflow-hidden">
            <div class="border-b border-zinc-200/60 p-5">
                <flux:heading size="lg" class="font-semibold!">Top Performers</flux:heading>
                <flux:text class="mt-1">Followed creators ranked by views in the last {{ $days }} days.</flux:text>
            </div>
            <ol class="divide-y divide-zinc-200/60">
                @forelse ($rows as $row)
                    @php $creator = $creators[$row['influencer_id']]; @endphp
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4" wire:key="top-{{ $creator->id }}">
                        <span class="w-6 font-display text-lg font-bold text-zinc-400">{{ $loop->iteration }}</span>
                        <a href="{{ route('analytics', ['influencer' => $creator->id, 'days' => $days]) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                            <img src="{{ image_url($creator->avatar, 80, 80) }}" alt="" class="size-10 rounded-full object-cover">
                            <span class="min-w-0">
                                <span class="flex items-center gap-1 font-semibold text-zinc-900"><span class="truncate">{{ $creator->name }}</span> @if ($creator->is_verified) <x-verified class="size-3.5" /> @endif</span>
                                <span class="flex gap-1.5">@foreach ($creator->accounts as $account) <x-platform-icon :platform="$account->platform" size="size-3.5" /> @endforeach</span>
                            </span>
                        </a>
                        <dl class="grid w-full grid-cols-4 gap-3 text-sm sm:w-auto sm:gap-6">
                            @foreach (['Posts' => $row['posts'], 'Views' => $row['views'], 'Engagement' => $row['engagement']] as $term => $value)
                                <div class="sm:text-end">
                                    <dt class="text-xs text-zinc-500">{{ $term }}</dt>
                                    <dd class="font-semibold text-zinc-900">{{ Number::abbreviate($value, maxPrecision: 1) }}</dd>
                                </div>
                            @endforeach
                            <div class="sm:text-end">
                                <dt class="text-xs text-zinc-500">Change</dt>
                                <dd><x-analytics.change :value="$row['change']" class="text-sm" /></dd>
                            </div>
                        </dl>
                    </li>
                @empty
                    <li class="px-5 py-12 text-center"><flux:text>No posts from your creators in the last {{ $days }} days.</flux:text></li>
                @endforelse
            </ol>
        </section>
    @elseif ($tab === 'trends')
        <section class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg" class="font-semibold!">Performance Over Time</flux:heading>
                    <flux:text class="mt-1">All {{ $this->followed->count() }} followed creators, {{ $this->report->posts->count() }} posts.</flux:text>
                </div>
                <x-analytics.metric-switch :metric="$metric" />
            </div>
            <x-analytics.chart :data="$this->report->daily($metric)" :label="ucfirst($metric)" class="mt-5" />
        </section>

        <section class="card p-5">
            <flux:heading size="lg" class="font-semibold!">Posting Activity</flux:heading>
            <x-analytics.heatmap :weeks="$this->report->heatmap()" class="mt-4" />
        </section>
    @else
        <livewire:influencer-analytics :influencer="$this->creator" :days="$days" :key="'creator-'.$this->creator->id.'-'.$days" />
    @endif
</div>
