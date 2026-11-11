<?php

use App\Analytics\CreatorReport;
use App\Models\Influencer;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/**
 * One creator's report on the Analytics page (My Creators tab): KPIs against the previous period,
 * performance chart, posting heatmap, platforms, formats, best posts and follower growth.
 */
new class extends Component {
    public Influencer $influencer;

    public int $days = 30;

    public string $metric = 'views';

    public function mount(): void
    {
        $this->days = in_array($this->days, CreatorReport::RANGES) ? $this->days : 30;
    }

    #[Computed]
    public function report(): CreatorReport
    {
        return new CreatorReport([$this->influencer->id], $this->days);
    }

    /**
     * Per account: platform, followers per day, and an SVG polyline of them.
     */
    #[Computed]
    public function growth()
    {
        return $this->influencer->accounts()
            ->with(['snapshots' => fn ($q) => $q->whereDate('captured_on', '>=', today()->subDays($this->days))->orderBy('captured_on')])
            ->get()
            ->filter(fn ($account) => $account->snapshots->isNotEmpty())
            ->map(function ($account) {
                $values = $account->snapshots->pluck('followers');
                [$min, $max] = [$values->min(), $values->max()];
                $last = max($values->count() - 1, 1);

                return [
                    'platform' => $account->platform,
                    'first' => $values->first(),
                    'last' => $values->last(),
                    'change' => $values->first() ? ($values->last() - $values->first()) / $values->first() * 100 : 0,
                    'from' => $account->snapshots->first()->captured_on,
                    // 100×30 viewBox, y flipped so growth goes up; a flat line sits in the middle.
                    'points' => $values->values()->map(fn ($v, $i) => round($i / $last * 100, 2).','.round($max > $min ? 30 - ($v - $min) / ($max - $min) * 30 : 15, 2))->join(' '),
                ];
            })
            ->values();
    }
}; ?>

@use('Illuminate\Support\Number')

@php
    $influencer = $this->influencer;
    $accounts = $influencer->accounts;
    $report = $this->report;
    $kpis = $report->kpis();
    $breakdown = $report->breakdown();
    $total = $report->posts->count();
@endphp

<div class="space-y-6">
    {{-- Creator card --}}
    <section class="card flex flex-wrap items-center gap-4 p-5">
        <img src="{{ image_url($influencer->avatar, 160, 160) }}" alt="" class="size-16 rounded-full object-cover sm:size-20">
        <div class="min-w-44 flex-1">
            <h2 class="flex items-center gap-2 font-display text-xl font-bold text-zinc-900">
                <span class="truncate">{{ $influencer->name }}</span>
                @if ($influencer->is_verified) <x-verified class="size-5" /> @endif
            </h2>
            <p class="text-sm text-zinc-500">{{ collect([$accounts->first()?->handle, Number::abbreviate($accounts->sum('followers'), maxPrecision: 1).' total followers'])->filter()->join(' · ') }}</p>
            <div class="mt-2 flex gap-2">
                @foreach ($accounts as $account)
                    <x-platform-icon :platform="$account->platform" size="size-4" />
                @endforeach
            </div>
        </div>
        <flux:button variant="primary" icon="check" :href="route('influencers.show', $influencer)" wire:navigate>Following</flux:button>
    </section>

    {{-- KPIs --}}
    <section @class(['grid grid-cols-2 gap-3 sm:gap-4', 'lg:grid-cols-5' => count($kpis) === 5, 'lg:grid-cols-4' => count($kpis) === 4]) aria-label="Key figures">
        @foreach ($kpis as $key => $kpi)
            <div class="card p-4" wire:key="kpi-{{ $key }}">
                <flux:icon :icon="$kpi['icon']" variant="outline" class="size-6 text-accent" />
                <p class="mt-3 font-display text-2xl font-bold text-zinc-900">{{ Number::abbreviate($kpi['value'], maxPrecision: 1) }}</p>
                <p class="text-sm text-zinc-500">{{ $kpi['label'] }}</p>
                <x-analytics.change :value="$kpi['change']" class="mt-2" />
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-6">
            <section class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg" class="font-semibold!">Performance Over Time</flux:heading>
                    <x-analytics.metric-switch :metric="$metric" />
                </div>
                <x-analytics.chart :data="$report->daily($metric)" :label="ucfirst($metric)" class="mt-5" />
            </section>

            <section class="card p-5">
                <flux:heading size="lg" class="font-semibold!">Posting Activity</flux:heading>
                <x-analytics.heatmap :weeks="$report->heatmap()" class="mt-4" />
            </section>

            <section class="card p-5">
                <flux:heading size="lg" class="font-semibold!">Platform Activity</flux:heading>
                <div class="mt-4 space-y-3">
                    @forelse ($report->platforms() as $row)
                        <div class="flex items-center gap-3 text-sm" wire:key="platform-{{ $row['platform']->value }}">
                            <x-platform-icon :platform="$row['platform']" size="size-5" />
                            <span class="w-20 shrink-0 text-zinc-700">{{ $row['platform']->label() }}</span>
                            <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-zinc-100">
                                <div class="h-full rounded-full bg-accent" style="width: {{ $row['percent'] }}%"></div>
                            </div>
                            <span class="w-28 shrink-0 text-end text-zinc-500">{{ $row['count'] }} {{ Str::plural('post', $row['count']) }} ({{ $row['percent'] }}%)</span>
                        </div>
                    @empty
                        <flux:text>No posts in the last {{ $days }} days.</flux:text>
                    @endforelse
                </div>
            </section>

            @if ($this->growth->isNotEmpty())
                <section class="card p-5">
                    <flux:heading size="lg" class="font-semibold!">Follower growth</flux:heading>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($this->growth as $row)
                            <div class="rounded-xl bg-zinc-50 p-4" wire:key="growth-{{ $row['platform']->value }}">
                                <div class="flex items-center gap-2 text-sm font-medium text-zinc-800">
                                    <x-platform-icon :platform="$row['platform']" size="size-4" /> {{ $row['platform']->label() }}
                                    <span @class(['ms-auto text-xs font-semibold', 'text-green-600' => $row['change'] >= 0, 'text-red-600' => $row['change'] < 0])>
                                        {{ $row['change'] >= 0 ? '+' : '' }}{{ number_format($row['change'], 1) }}%
                                    </span>
                                </div>
                                <svg viewBox="-1 -1 102 32" preserveAspectRatio="none" class="mt-3 h-16 w-full text-accent" role="img"
                                    aria-label="{{ $row['platform']->label() }} followers from {{ number_format($row['first']) }} to {{ number_format($row['last']) }}">
                                    <polyline points="{{ $row['points'] }}" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                                </svg>
                                <div class="mt-2 flex justify-between text-xs text-zinc-500">
                                    <span>{{ $row['from']->format('M j') }} · {{ number_format($row['first']) }}</span>
                                    <span>Today · {{ number_format($row['last']) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            <section class="card p-5">
                <flux:heading size="lg" class="font-semibold!">Creator Overview</flux:heading>
                <dl class="mt-4 space-y-3 text-sm">
                    @foreach ([
                        'Platforms' => $accounts->count().' ('.$accounts->map(fn ($a) => $a->platform->label())->join(', ').')',
                        'Posting Frequency' => '~'.number_format($report->postsPerWeek(), 1).' posts/week',
                        'Most Active Day' => $report->mostActiveDay() ?? '—',
                        'Primary Format' => $report->primaryFormat() ?? '—',
                    ] as $term => $value)
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ $term }}</dt>
                            <dd class="text-end font-medium text-zinc-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="card p-5">
                <flux:heading size="lg" class="font-semibold!">Content Breakdown</flux:heading>
                <div class="mt-4 flex flex-wrap items-center gap-5">
                    @php $offset = 25; @endphp
                    <svg viewBox="0 0 42 42" class="size-32 shrink-0" role="img" aria-label="{{ $total }} posts by format">
                        <circle cx="21" cy="21" r="15.915" fill="none" class="stroke-zinc-100" stroke-width="6" />
                        @foreach ($breakdown as $format => $count)
                            @if ($count)
                                @php $share = $count / $total * 100; @endphp
                                <circle cx="21" cy="21" r="15.915" fill="none" stroke-width="6" stroke-dasharray="{{ $share }} {{ 100 - $share }}" stroke-dashoffset="{{ $offset }}"
                                    @class(['stroke-accent' => $loop->index === 0, 'stroke-gold' => $loop->index === 1, 'stroke-khaki' => $loop->index === 2, 'stroke-zinc-300' => $loop->index === 3]) />
                                @php $offset -= $share; @endphp
                            @endif
                        @endforeach
                        <text x="21" y="21" text-anchor="middle" class="fill-zinc-900 font-display text-[8px] font-bold">{{ $total }}</text>
                        <text x="21" y="27" text-anchor="middle" class="fill-zinc-500 text-[3.5px]">posts</text>
                    </svg>
                    <ul class="min-w-36 flex-1 space-y-2 text-sm">
                        @foreach ($breakdown as $format => $count)
                            <li class="flex items-center gap-2">
                                <span @class(['size-2.5 shrink-0 rounded-full', 'bg-accent' => $loop->index === 0, 'bg-gold' => $loop->index === 1, 'bg-khaki' => $loop->index === 2, 'bg-zinc-300' => $loop->index === 3])></span>
                                <span class="flex-1 text-zinc-700">{{ $format }}</span>
                                <span class="text-zinc-500">{{ $total ? round($count / $total * 100) : 0 }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            <section class="card p-5">
                <flux:heading size="lg" class="font-semibold!">Best Performing Posts</flux:heading>
                <div class="mt-4 space-y-4">
                    @forelse ($report->best() as $post)
                        <a href="{{ $post->permalink ?: route('influencers.show', $influencer) }}" @if ($post->permalink) target="_blank" rel="noopener" @endif class="flex gap-3 group" wire:key="best-{{ $post->id }}">
                            <div class="relative w-28 shrink-0 self-start overflow-hidden rounded-lg bg-zinc-100">
                                @if ($post->images)
                                    <img src="{{ image_url($post->images[0], 224, 126) }}" alt="" loading="lazy" class="aspect-video w-full object-cover">
                                @else
                                    <div class="aspect-video"></div>
                                @endif
                                @if ($post->duration)
                                    <span class="absolute bottom-1 end-1 rounded bg-black/75 px-1 text-[10px] font-medium text-white">{{ $post->duration }}</span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="line-clamp-2 text-sm font-semibold text-zinc-900 group-hover:underline">{{ $post->title ?: Str::limit($post->body, 80) }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ Number::abbreviate($post->metrics['views'], maxPrecision: 1) }} views · {{ $post->published_at->diffForHumans() }}</p>
                            </div>
                        </a>
                    @empty
                        <flux:text>No view counts for posts in this period yet.</flux:text>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
