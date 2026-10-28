@props(['on'])

{{-- A short "Saved." that shows for two seconds when the Livewire event fires. --}}
<span x-data="{ shown: false, timeout: null }"
    x-on:{{ $on }}.window="clearTimeout(timeout); shown = true; timeout = setTimeout(() => shown = false, 2000)"
    x-show="shown" x-transition.opacity.duration.300ms style="display: none"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-sm text-emerald-600']) }}>
    <flux:icon.check class="size-4" /> Saved.
</span>
