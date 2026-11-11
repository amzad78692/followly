@props(['value'])

{{-- "↑ +18%" in green, "↓ -5%" in red, or "—" when there is no previous period to compare with. --}}
@if ($value === null)
    <p {{ $attributes->class('text-xs font-semibold text-zinc-400') }} title="No posts in the previous period">—</p>
@else
    <p {{ $attributes->class(['text-xs font-semibold', 'text-green-600' => $value >= 0, 'text-red-600' => $value < 0]) }}>
        {{ $value >= 0 ? '↑ +' : '↓ ' }}{{ abs($value) == (int) abs($value) ? (int) $value : $value }}%
        <span class="sr-only">vs previous period</span>
    </p>
@endif
