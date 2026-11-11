@props(['data', 'label' => 'Views'])

@use('Illuminate\Support\Carbon')
@use('Illuminate\Support\Number')

{{-- Area chart of [Y-m-d => value]. The SVG stretches to fit; labels and dots are HTML so they keep their shape. --}}
@php
    $values = array_values($data);
    $dates = array_keys($data);
    $max = max(max($values ?: [0]), 1);
    $last = max(count($values) - 1, 1);
    $points = collect($values)->map(fn ($v, $i) => [round($i / $last * 100, 2), round(100 - $v / $max * 100, 2)]);
    $line = $points->map(fn ($p) => implode(',', $p))->join(' ');
    $ticks = collect(range(0, 3))->map(fn ($k) => (int) round($k * $last / 3))->unique()->filter(fn ($i) => isset($dates[$i]));
@endphp

<div {{ $attributes->class('flex gap-2') }} role="img" aria-label="{{ $label }} per day: {{ Number::abbreviate(array_sum($values), maxPrecision: 1) }} in total, peak {{ Number::abbreviate(max($values ?: [0]), maxPrecision: 1) }}">
    <div class="flex h-48 flex-col justify-between text-end text-[11px] text-zinc-400" aria-hidden="true">
        @foreach ([1, 0.75, 0.5, 0.25, 0] as $f)
            <span class="-my-1.5">{{ Number::abbreviate($max * $f, maxPrecision: 1) }}</span>
        @endforeach
    </div>

    <div class="min-w-0 flex-1" aria-hidden="true">
        <div class="relative h-48 border-b border-zinc-200 bg-[linear-gradient(to_bottom,var(--color-zinc-100)_1px,transparent_1px)] bg-size-[100%_25%]">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible text-accent">
                <polygon points="0,100 {{ $line }} 100,100" class="fill-khaki/30" />
                <polyline points="{{ $line }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
            </svg>
            @if (count($values) <= 31)
                @foreach ($points as $i => [$x, $y])
                    <span class="absolute size-2 -translate-1/2 rounded-full border-2 border-white bg-accent" style="left: {{ $x }}%; top: {{ $y }}%" title="{{ Carbon::parse($dates[$i])->format('M j') }}: {{ number_format($values[$i]) }}"></span>
                @endforeach
            @endif
        </div>
        <div class="relative mt-1.5 h-4 text-[11px] text-zinc-400">
            @foreach ($ticks as $i)
                <span @class(['absolute whitespace-nowrap', '-translate-x-1/2' => $i > 0 && $i < $last, '-translate-x-full' => $i === $last]) style="left: {{ $i / $last * 100 }}%">{{ Carbon::parse($dates[$i])->format('M j') }}</span>
            @endforeach
        </div>
    </div>
</div>
