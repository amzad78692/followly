<!DOCTYPE html>
<html lang="{{ $lang ?? app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
    @php
        $user = auth()->user();
        // Sidebar targets in one place. Watchlist, Alerts, Timeline and Analytics point at the closest
        // existing page until their own pages exist; switch the link here when they do.
        $links = [
            'feed' => route('dashboard'),
            'watchlist' => route('dashboard', ['watchlist' => 1]),
            'collections' => route('collections.index'),
            'search' => route('search'),
            'alerts' => route('alerts'),
            'timeline' => route('timeline'),
            'discover' => route('explore'),
            'analytics' => route('analytics'),
            'settings' => route('settings'),
        ];
        $onFeed = request()->routeIs('dashboard');
        $watchCount = $user->watchlist()->count();
        $myCollections = $user->collections()->withCount('influencers')->orderBy('name')->get();
    @endphp

    <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200/60 bg-sand">
        <flux:sidebar.header class="h-8">
            <x-brand class="px-2" />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" :href="$links['feed']" :current="$onFeed && ! request('watchlist') && ! request('collection')" wire:navigate>My Feed</flux:sidebar.item>
            <flux:sidebar.item icon="star" :href="$links['watchlist']" :current="$onFeed && request('watchlist')" :badge="$watchCount ?: null" badge:variant="solid" badge:class="rounded-full! bg-accent! px-2!" wire:navigate>Watchlist</flux:sidebar.item>
            <flux:sidebar.item icon="folder" :href="$links['collections']" :current="request()->routeIs('collections.*')" wire:navigate>Collections</flux:sidebar.item>
            <flux:sidebar.item icon="magnifying-glass" :href="$links['search']" :current="request()->routeIs('search')" wire:navigate>Search</flux:sidebar.item>
            <livewire:notification-count variant="sidebar" :href="$links['alerts']" :current="request()->routeIs('alerts')" />
            <flux:sidebar.item icon="clock" :href="$links['timeline']" :current="request()->routeIs('timeline')" wire:navigate>Timeline</flux:sidebar.item>
            <flux:sidebar.item icon="globe-alt" :href="$links['discover']" :current="request()->routeIs('explore')" wire:navigate>Discover</flux:sidebar.item>
            <flux:sidebar.item icon="chart-bar" :href="$links['analytics']" :current="request()->routeIs('analytics')" wire:navigate>Analytics</flux:sidebar.item>
            <flux:sidebar.item icon="cog-6-tooth" :href="$links['settings']" :current="request()->routeIs('settings')" wire:navigate>Settings</flux:sidebar.item>
        </flux:sidebar.nav>

        <section aria-labelledby="sidebar-collections" class="mt-2">
            <div class="flex items-center justify-between px-3">
                <h2 id="sidebar-collections" class="font-sans text-xs font-semibold uppercase tracking-wide text-zinc-400">My Collections</h2>
                <flux:button variant="ghost" size="xs" icon="plus" :href="$links['collections']" wire:navigate aria-label="New collection" />
            </div>
            <flux:sidebar.nav class="mt-1">
                @forelse ($myCollections as $item)
                    <flux:sidebar.item icon="folder" :href="route('dashboard', ['collection' => $item->id])" :current="$onFeed && (int) request('collection') === $item->id" :badge="$item->influencers_count" badge:class="rounded-full! bg-transparent! text-zinc-400!" wire:navigate wire:key="sidebar-collection-{{ $item->id }}">{{ $item->name }}</flux:sidebar.item>
                @empty
                    <p class="px-3 text-xs text-zinc-500">Group creators into collections to filter your feed.</p>
                @endforelse
            </flux:sidebar.nav>
        </section>
    </flux:sidebar>

    <flux:main class="p-0!">
        <header class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-zinc-200/60 bg-zinc-50/90 px-4 backdrop-blur lg:px-8">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <div class="flex min-w-0 flex-1 justify-center">
                <livewire:search-bar />
            </div>

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
