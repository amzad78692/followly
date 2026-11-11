@props(['weeks'])

@use('Illuminate\Support\Carbon')

{{-- Posts per weekday (rows) and week (columns), from CreatorReport::heatmap(). Darker gold = more posts. --}}
@php
    $peak = max(collect($weeks)->flatten()->max() ?? 0, 1);
    $shade = fn ($n) => match (true) {
        $n === 0 => 'bg-zinc-100',
        $n / $peak <= 0.25 => 'bg-khaki/40',
        $n / $peak <= 0.5 => 'bg-khaki',
        $n / $peak <= 0.75 => 'bg-gold',
        default => 'bg-accent',
    };
@endphp

<div {{ $attributes->class('overflow-x-auto') }}>
    <table class="w-full border-separate border-spacing-1 text-[11px] text-zinc-400">
        <thead>
            <tr>
                <th class="w-8"><span class="sr-only">Day</span></th>
                @foreach ($weeks as $monday => $days)
                    <th scope="col" class="min-w-6 font-normal whitespace-nowrap">{{ Carbon::parse($monday)->format('M j') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $day)
                <tr>
                    <th scope="row" class="pe-1 text-start font-normal">{{ $day }}</th>
                    @foreach ($weeks as $monday => $days)
                        <td class="h-6 rounded {{ $shade($days[$i + 1]) }}" title="Week of {{ Carbon::parse($monday)->format('M j') }}, {{ $day }}: {{ $days[$i + 1] }} {{ Str::plural('post', $days[$i + 1]) }}">
                            <span class="sr-only">{{ $days[$i + 1] }}</span>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-2 flex items-center justify-end gap-1 text-[11px] text-zinc-400" aria-hidden="true">
        Less
        @foreach (['bg-zinc-100', 'bg-khaki/40', 'bg-khaki', 'bg-gold', 'bg-accent'] as $class)
            <span class="size-3 rounded-sm {{ $class }}"></span>
        @endforeach
        More
    </div>
</div>
