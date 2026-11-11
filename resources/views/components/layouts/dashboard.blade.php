<!DOCTYPE html>
<html lang="{{ $lang ?? app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-sand">
        <flux:sidebar.header>
            <x-brand class="px-2" />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Dashboard</flux:sidebar.item>
            <flux:sidebar.item icon="magnifying-glass" :href="route('explore')" :current="request()->routeIs('explore')" wire:navigate>Explore</flux:sidebar.item>
            <flux:sidebar.item icon="users" :href="route('influencers.index')" :current="request()->routeIs('influencers.*')" wire:navigate>My Influencers</flux:sidebar.item>
            <flux:sidebar.item icon="folder" :href="route('collections.index')" :current="request()->routeIs('collections.*')" wire:navigate>Collections</flux:sidebar.item>
            <livewire:notification-count variant="sidebar" :current="request()->routeIs('notifications')" />
            <flux:sidebar.item icon="cog-6-tooth" :href="route('settings')" :current="request()->routeIs('settings')" wire:navigate>Settings</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <div class="rounded-2xl bg-accent p-4 text-white">
            <p class="font-display text-sm font-semibold">Track a new creator</p>
            <p class="mt-1 text-xs text-white/85">Paste any profile link and we find the rest.</p>
            <flux:button size="sm" :href="route('influencers.create')" wire:navigate class="mt-3 w-full">Add Influencer</flux:button>
        </div>
    </flux:sidebar>

    <flux:main class="p-0!">
        <header class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-zinc-200 bg-ivory/90 px-4 backdrop-blur lg:px-8">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <livewire:search-bar />

            <flux:spacer />

            <livewire:notification-count variant="bell" />

            <flux:dropdown position="bottom" align="end">
                <button type="button" class="cursor-pointer">
                    <flux:avatar circle size="sm" :name="auth()->user()->name" :src="auth()->user()->avatarUrl()" />
                </button>
                <flux:menu>
                    <div class="px-2 py-1.5">
                        <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</p>
                    </div>
                    <flux:menu.separator />
                    <flux:menu.item icon="user" :href="route('settings')" wire:navigate>Profile</flux:menu.item>
                    <flux:menu.item icon="bell" :href="route('settings').'#notifications'">Notification settings</flux:menu.item>
                    <flux:menu.item icon="cog-6-tooth" :href="route('settings')" wire:navigate>Settings</flux:menu.item>
                    @if (\App\Models\Admin::where('email', auth()->user()->email)->exists())
                        @if (auth()->user()->hasVerifiedEmail())
                            <form method="POST" action="{{ route('admin.login') }}" class="w-full">
                                @csrf
                                <flux:menu.item as="button" type="submit" icon="shield-check" class="w-full">Admin panel</flux:menu.item>
                            </form>
                        @else
                            <flux:menu.item icon="shield-check" :href="route('verification.notice')">Verify email for admin panel</flux:menu.item>
                        @endif
                    @endif
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">Log out</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </header>

        <div class="px-4 py-6 lg:px-8">
            {{ $slot }}
        </div>
    </flux:main>

    <x-player-modal />

    @fluxScripts
</body>
</html>
