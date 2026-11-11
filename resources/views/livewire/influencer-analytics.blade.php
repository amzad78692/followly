<?php

use App\Models\Influencer;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/**
 * "Analytics" tab on the influencer page: follower growth per platform (account_snapshots)
 * and post stats over the last DAYS days.
 */
new class extends Component {
    public const DAYS = 90;

    public const WEEKS = 12;

    public Influencer $influencer;

    /**
     * Per account: platform, followers per day, and an SVG polyline of them.
     */
    #[Computed]
    public function growth()
    {
        return $this->influencer->accounts()
            ->with(['snapshots' => fn ($q) => $q->whereDate('captured_on', '>=', today()->subDays(self::DAYS))->orderBy('captured_on')])
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

    #[Computed]
    public function posts()
    {
        return $this->influencer->posts()->visible()->where('published_at', '>=', now()->subDays(self::DAYS))->get();
    }

    /**
     * Post count per week for the last WEEKS weeks, oldest first: [week start label => count].
     */
    #[Computed]
    public function perWeek(): array
    {
        $counts = $this->posts->countBy(fn ($post) => $post->published_at->startOfWeek()->toDateString());

        return collect(range(self::WEEKS - 1, 0))
            ->mapWithKeys(fn ($ago) => [($week = now()->subWeeks($ago)->startOfWeek())->format('M j') => $counts[$week->toDateString()] ?? 0])
            ->all();
    }

    /**
     * Average of each metric over the posts that report it, or null when none do.
     */
    #[Computed]
    public function averages(): array
    {
        return collect(['likes', 'comments', 'views'])
            ->mapWithKeys(fn ($metric) => [$metric => $this->posts->pluck("metrics.{$metric}")->filter(fn ($v) => $v !== null)->avg()])
            ->all();
    }

    #[Computed]
    public function popular()
    {
        return $this->posts
            ->filter(fn ($post) => $post->metrics)
            ->sortByDesc(fn ($post) => [($post->metrics['likes'] ?? 0) + ($post->metrics['comments'] ?? 0), $post->metrics['views'] ?? 0])
            ->take(5)
            ->each->setRelation('influencer', $this->influencer)
            ->values();
    }
}; ?>

<div class="space-y-6">
    <section class="card p-6">
        <flux:heading size="lg" class="font-semibold!">Follower growth</flux:heading>
        <flux:text class="mt-1">Last {{ $this::DAYS }} days, per platform.</flux:text>

        @if ($this->growth->isEmpty())
            <flux:text class="mt-4">No follower history yet. It builds up as {{ config('app.name') }} syncs this creator each day.</flux:text>
        @else
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
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card p-6">
            <flux:heading size="lg" class="font-semibold!">Posts per week</flux:heading>
            @php $peak = max(max($this->perWeek), 1); @endphp
            <div class="mt-4 flex h-32 items-end gap-1.5">
                @foreach ($this->perWeek as $week => $count)
                    <div class="flex h-full flex-1 flex-col justify-end" title="Week of {{ $week }}: {{ $count }} posts">
                        <div class="rounded-t bg-accent/80" style="height: {{ $count / $peak * 100 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-xs text-zinc-500">
                <span>{{ array_key_first($this->perWeek) }}</span>
                <span>{{ array_sum($this->perWeek) }} posts in {{ $this::WEEKS }} weeks</span>
            </div>
        </section>

        <section class="card p-6">
            <flux:heading size="lg" class="font-semibold!">Average per post</flux:heading>
            <dl class="mt-4 grid grid-cols-3 gap-4">
                @foreach ($this->averages as $metric => $average)
                    <div class="rounded-xl bg-zinc-50 p-4">
                        <dt class="text-xs capitalize text-zinc-500">{{ $metric }}</dt>
                        <dd class="mt-1 font-display text-xl font-bold text-zinc-900">{{ $average === null ? '—' : \Illuminate\Support\Number::abbreviate($average, maxPrecision: 1) }}</dd>
                    </div>
                @endforeach
            </dl>
            <flux:text class="mt-3 text-xs">Posts from the last {{ $this::DAYS }} days.</flux:text>
        </section>
    </div>

    <section>
        <flux:heading size="lg" class="font-semibold!">Most popular posts</flux:heading>
        <div class="mt-4 space-y-4">
            @forelse ($this->popular as $post)
                <x-post-card :post="$post" compact wire:key="popular-{{ $post->id }}" />
            @empty
                <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
                    <flux:text>No engagement data for recent posts yet.</flux:text>
                </div>
            @endforelse
        </div>
    </section>
</div>
