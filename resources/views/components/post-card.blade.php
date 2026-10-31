@props(['post', 'compact' => false])

@use('App\Enums\Platform')
@use('Illuminate\Support\Js')

@php
    $author = $post->influencer;
    $embed = $post->embed;
    // Opens the shared x-player-modal; the media becomes a button when the post can play here.
    $play = $embed ? "\$dispatch('play-post', ".Js::from($embed).')' : null;
    $media = $play ? 'button' : 'div';
@endphp

<article {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5') }}>
    <div class="flex items-start gap-3">
        <a href="{{ route('influencers.show', $author) }}" wire:navigate>
            <flux:avatar circle :src="image_url($author->avatar, 80, 80)" />
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

        @if ($post->permalink || $embed)
            <flux:dropdown align="end">
                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" inset="top right" aria-label="Post options" />
                <flux:menu>
                    @if ($embed)
                        <flux:menu.item :icon="$post->platform === Platform::X ? 'eye' : 'play'" x-on:click="{{ $play }}">{{ $post->platform === Platform::X ? 'View here' : 'Watch here' }}</flux:menu.item>
                    @endif
                    @if ($post->permalink)
                        <flux:menu.item icon="arrow-top-right-on-square" :href="$post->permalink" target="_blank">Open on {{ $post->platform->label() }}</flux:menu.item>
                        <flux:menu.item icon="link" x-on:click="navigator.clipboard.writeText({{ Js::from($post->permalink) }})">Copy link</flux:menu.item>
                    @endif
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
            <{{ $media }} @if ($play) type="button" x-on:click="{{ $play }}" aria-label="View post" @endif class="mt-3 grid w-full grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach (array_slice($post->images, 0, 6) as $image)
                    <img src="{{ image_url($image, 480, 360) }}" alt="" loading="lazy" class="aspect-[4/3] w-full rounded-xl object-cover">
                @endforeach
            </{{ $media }}>
        @elseif (count($post->images) === 1)
            <{{ $media }} @if ($play) type="button" x-on:click="{{ $play }}" aria-label="{{ $post->duration ? 'Play video' : 'View post' }}" @endif @class(['group relative mt-3 block w-full overflow-hidden rounded-xl bg-zinc-100', 'max-w-md' => ! $compact, 'cursor-pointer' => $play])>
                <img src="{{ image_url($post->images[0], 896, 504) }}" alt="" loading="lazy" class="aspect-video w-full object-cover">

                @if ($post->duration)
                    <span class="absolute inset-0 flex items-center justify-center">
                        <span class="flex size-14 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-sm transition group-hover:scale-105">
                            <flux:icon.play variant="solid" class="size-6 translate-x-0.5" />
                        </span>
                    </span>
                    <span class="absolute bottom-2 end-2 rounded-md bg-black/75 px-1.5 py-0.5 text-xs font-medium text-white">{{ $post->duration }}</span>
                @endif
            </{{ $media }}>
        @endif
    </div>
</article>
