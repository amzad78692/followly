@props(['post', 'compact' => false])

@php $author = $post->influencer; @endphp

<article {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5') }}>
    <div class="flex items-start gap-3">
        <a href="{{ route('influencers.show', $author) }}" wire:navigate>
            <flux:avatar circle :src="$author->avatar" />
        </a>

        <div class="min-w-0 flex-1">
            <a href="{{ route('influencers.show', $author) }}" wire:navigate class="flex items-center gap-1 text-sm font-semibold text-zinc-900 hover:underline">
                {{ $author->name }}
                @if ($author->is_verified)
                    <x-verified class="size-3.5" />
                @endif
            </a>
            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-500">
                <flux:icon.clock class="size-3" /> {{ $post->published_at->diffForHumans() }}
                <span class="text-zinc-300">·</span>
                <x-platform-icon :platform="$post->platform" size="size-3.5" /> {{ $post->platform->label() }}
            </p>
        </div>

        @if ($post->permalink)
            <flux:dropdown align="end">
                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" inset="top right" />
                <flux:menu>
                    <flux:menu.item icon="arrow-top-right-on-square" :href="$post->permalink" target="_blank">Open on {{ $post->platform->label() }}</flux:menu.item>
                    <flux:menu.item icon="link" x-on:click="navigator.clipboard.writeText(@js($post->permalink))">Copy link</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    <div class="mt-3 sm:ps-13">
        @if ($post->title)
            <h3 class="font-semibold text-zinc-900">{{ $post->title }}</h3>
        @endif
        @if ($post->body)
            <p @class(['text-sm whitespace-pre-line text-zinc-600', 'mt-1' => $post->title])>{{ $post->body }}</p>
        @endif

        @if (count($post->images) > 1)
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach (array_slice($post->images, 0, 6) as $image)
                    <img src="{{ $image }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-xl object-cover">
                @endforeach
            </div>
        @elseif (count($post->images) === 1)
            <div @class(['relative mt-3 overflow-hidden rounded-xl bg-zinc-100', 'max-w-md' => ! $compact])>
                <img src="{{ $post->images[0] }}" alt="" loading="lazy" class="aspect-video w-full object-cover">

                @if ($post->duration)
                    <span class="absolute inset-0 flex items-center justify-center">
                        <span class="flex size-14 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-sm transition hover:scale-105">
                            <flux:icon.play variant="solid" class="size-6 translate-x-0.5" />
                        </span>
                    </span>
                    <span class="absolute bottom-2 end-2 rounded-md bg-black/75 px-1.5 py-0.5 text-xs font-medium text-white">{{ $post->duration }}</span>
                @endif
            </div>
        @endif
    </div>
</article>
