{{-- Navbar for guests: the landing page and public profiles. The slot holds page links. --}}
<header {{ $attributes->class('mx-auto flex max-w-7xl items-center gap-8 px-4 py-5 sm:px-6') }}>
    <x-brand />

    @if ($slot->isNotEmpty())
        <nav class="hidden items-center gap-7 text-sm font-medium text-zinc-600 md:flex">
            {{ $slot }}
        </nav>
    @endif

    <flux:spacer />

    <div class="flex items-center gap-2">
        <flux:button size="sm" :href="route('login')" wire:navigate>Sign in</flux:button>
        <flux:button size="sm" variant="primary" :href="route('register')" wire:navigate>Get Started</flux:button>
    </div>
</header>
