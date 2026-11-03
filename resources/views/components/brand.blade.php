{{-- shortcut: styled text stands in for the logo, swap in the logo file when it exists. --}}
<a href="{{ route('home') }}" wire:navigate {{ $attributes->class('font-display text-lg font-bold uppercase tracking-tight text-accent') }}>{{ config('app.name') }}</a>
