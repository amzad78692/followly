@props(['icon' => null, 'title', 'subtitle' => null])

{{-- Page title row: large gold outline icon, title, muted subtitle and optional actions on the right. --}}
<header {{ $attributes->class('flex flex-wrap items-center gap-4') }}>
    @if ($icon)
        <flux:icon :icon="$icon" variant="outline" class="size-10 shrink-0 text-accent" />
    @endif
    <div class="min-w-0 flex-1">
        <h1 class="font-display text-2xl font-bold text-zinc-900 sm:text-3xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-zinc-500">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</header>
