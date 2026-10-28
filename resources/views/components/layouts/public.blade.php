<!DOCTYPE html>
<html lang="{{ $lang ?? app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50/60 font-sans text-zinc-900 antialiased">
    <div class="border-b border-zinc-200 bg-white">
        <x-site-header class="py-4!" />
    </div>

    <main class="px-4 py-6 lg:px-8">
        {{ $slot }}
    </main>

    <x-player-modal />

    @fluxScripts
</body>
</html>
