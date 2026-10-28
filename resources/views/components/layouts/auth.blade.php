<!DOCTYPE html>
<html lang="{{ $lang ?? app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-zinc-50/60 font-sans text-zinc-900 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <x-brand class="mb-8" />

        <div class="w-full max-w-sm rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm">
            {{ $slot }}
        </div>
    </div>

    @fluxScripts
</body>
</html>
