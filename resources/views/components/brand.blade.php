<a href="{{ route('home') }}" wire:navigate {{ $attributes->class('font-display text-lg font-bold uppercase tracking-tight text-accent') }}>
    @if ($logo = setting('general.logo'))
        <img src="{{ asset('storage/'.$logo) }}" alt="{{ config('app.name') }}" class="h-10 w-auto">
    @else
        {{ config('app.name') }}
    @endif
</a>
