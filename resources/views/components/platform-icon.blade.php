@props(['platform', 'size' => 'size-4'])

@php
$bg = match ($platform) {
    'youtube' => 'bg-red-600',
    'instagram' => 'bg-linear-to-tr from-amber-400 via-pink-500 to-purple-600',
    default => 'bg-zinc-900',
};
@endphp

<span {{ $attributes->class([$size, $bg, 'inline-flex shrink-0 items-center justify-center rounded-[25%] text-white']) }} title="{{ \App\Support\Demo::PLATFORMS[$platform]['label'] ?? $platform }}">
    @switch($platform)
        @case('youtube')
            <svg viewBox="0 0 24 24" class="size-[55%]" fill="currentColor"><path d="M8 5.5v13l11-6.5z"/></svg>
            @break
        @case('instagram')
            <svg viewBox="0 0 24 24" class="size-[62%]" fill="none" stroke="currentColor" stroke-width="2.4"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
            @break
        @case('tiktok')
            <svg viewBox="0 0 24 24" class="size-[62%]" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
            @break
        @case('x')
            <svg viewBox="0 0 24 24" class="size-[55%]" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            @break
    @endswitch
</span>
