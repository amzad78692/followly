{{-- Social links come from the admin panel (Settings > Contact); empty ones are left out. --}}
@php
    $socials = array_filter([
        'youtube' => setting('contact.youtube'),
        'instagram' => setting('contact.instagram'),
        'tiktok' => setting('contact.tiktok'),
        'x' => setting('contact.x'),
    ]);
@endphp

<footer {{ $attributes->class('border-t border-zinc-200 bg-sand') }}>
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:grid-cols-2 sm:px-6 lg:grid-cols-[2fr_1fr_1fr_1fr]">
        <div>
            <x-brand />
            <p class="mt-3 text-sm text-zinc-500">Follow Once, Track Everywhere.</p>
        </div>

        <div>
            <p class="font-display text-base font-semibold text-zinc-900">Product</p>
            <ul class="mt-4 space-y-3 text-sm text-zinc-500">
                <li><a href="{{ route('home') }}#features" class="hover:text-zinc-900">Features</a></li>
                <li><a href="{{ route('home') }}#explore" class="hover:text-zinc-900">Explore</a></li>
                <li><a href="{{ route('home') }}#about" class="hover:text-zinc-900">About</a></li>
                <li><a href="{{ route('home') }}#faq" class="hover:text-zinc-900">FAQ</a></li>
            </ul>
        </div>

        <div>
            <p class="font-display text-base font-semibold text-zinc-900">Legal</p>
            <ul class="mt-4 space-y-3 text-sm text-zinc-500">
                <li><a href="{{ route('privacy') }}" wire:navigate class="hover:text-zinc-900">Privacy Policy</a></li>
                <li><a href="{{ route('terms') }}" wire:navigate class="hover:text-zinc-900">Terms of Service</a></li>
                <li><a href="{{ route('contact') }}" wire:navigate class="hover:text-zinc-900">Contact</a></li>
            </ul>
        </div>

        @if ($socials)
            <div>
                <p class="font-display text-base font-semibold text-zinc-900">Follow Us</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($socials as $platform => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($platform) }}">
                            <x-platform-icon :platform="$platform" size="size-8" class="bg-accent! bg-none!" />
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <p class="border-t border-zinc-200 px-4 py-5 text-center text-xs text-zinc-500">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
</footer>
