{{-- Navbar for the landing page, public profiles and static pages. --}}
<header {{ $attributes->class('mx-auto flex max-w-7xl items-center gap-8 px-4 py-5 sm:px-6') }}>
    <x-brand />

    <nav class="hidden items-center gap-7 text-sm font-medium text-zinc-500 lg:flex">
        <a href="{{ route('home') }}" wire:navigate class="hover:text-zinc-900">Home</a>
        <a href="{{ route('home') }}#explore" class="hover:text-zinc-900">Explore</a>
        <a href="{{ route('home') }}#features" class="hover:text-zinc-900">Features</a>
        <a href="{{ route('home') }}#about" class="hover:text-zinc-900">About</a>
        <a href="{{ route('home') }}#faq" class="hover:text-zinc-900">FAQ</a>
    </nav>

    <flux:spacer />

    <div class="hidden w-full max-w-xs md:block">
        <livewire:search-bar />
    </div>

    <div class="flex shrink-0 items-center gap-2">
        @auth
            <flux:button size="sm" variant="primary" :href="route('dashboard')" wire:navigate>Go to app</flux:button>
        @else
            <flux:button size="sm" variant="ghost" :href="route('login')" wire:navigate>Sign in</flux:button>
            <flux:button size="sm" variant="primary" :href="route('register')" wire:navigate>Get Started</flux:button>
        @endauth
    </div>
</header>
