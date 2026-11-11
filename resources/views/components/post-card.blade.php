@props(['post', 'compact' => false, 'bookmark' => false])

@use('App\Enums\Platform')
@use('Illuminate\Support\Js')

@php
    $author = $post->influencer;
    $embed = $post->embed;
    // Opens the shared x-player-modal; the media becomes a button when the post can play here.
    $play = $embed ? "\$dispatch('play-post', ".Js::from($embed).')' : null;
    $media = $play ? 'button' : 'div';
    $platforms = $post->relationLoaded('versions') && $post->versions->isNotEmpty()
        ? $post->versions->pluck('platform')->unique()
        : collect([$post->platform]);
    $images = $post->images;
@endphp

<article {{ $attributes->class('card p-4 sm:p-5') }}>
    <div class="flex items-start gap-3">
        <a href="{{ route('influencers.show', $author) }}" wire:navigate>
            <flux:avatar circle :src="image_url($author->avatar, 80, 80)" />
        </a>

        <div class="min-w-0 flex-1">
            <a href="{{ route('influencers.show', $author) }}" wire:navigate class="flex items-center gap-1 font-display text-sm font-semibold text-zinc-900 hover:underline">
                <span class="truncate">{{ $author->name }}</span>
                @if ($author->is_verified)
                    <x-verified class="size-3.5" />
                @endif
            </a>
            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-500">
                {{ $post->published_at->diffForHumans() }}
                <span class="text-zinc-300">·</span>
                @foreach ($platforms as $platform)
                    <x-platform-icon :platform="$platform" size="size-3.5" />
                @endforeach
                <span class="sr-only">{{ $post->platform->label() }}</span>
            </p>
        </div>

        <div class="-me-2 -mt-1 flex shrink-0 items-center">
            @if ($bookmark)
                {{-- UI only for now; saving posts arrives with the My Feed page. --}}
                <flux:button variant="ghost" size="sm" icon="bookmark" aria-label="Save post" />
            @endif

            @if ($post->permalink || $embed)
                <flux:dropdown align="end">
                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" aria-label="Post options" />
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
    </div>

    <div class="mt-3 flex gap-3 sm:gap-4">
        @if ($images)
            <{{ $media }} @if ($play) type="button" x-on:click="{{ $play }}" aria-label="{{ $post->duration ? 'Play video' : 'View post' }}" @endif @class([
                'group relative block shrink-0 self-start overflow-hidden rounded-xl bg-zinc-100',
                'w-1/3 max-w-40' => $compact,
                'w-2/5 max-w-60' => ! $compact,
                'cursor-pointer' => $play,
            ])>
                <img src="{{ image_url($images[0], 448, 252) }}" alt="" loading="lazy" class="aspect-video w-full object-cover transition group-hover:scale-105">

                @if ($post->duration)
                    <span class="absolute bottom-1.5 end-1.5 inline-flex items-center gap-0.5 rounded-md bg-black/75 px-1.5 py-0.5 text-[11px] font-medium text-white">
                        <flux:icon.play variant="solid" class="size-3" /> {{ $post->duration }}
                    </span>
                @elseif (count($images) > 1)
                    <span class="absolute bottom-1.5 end-1.5 rounded-md bg-black/75 px-1.5 py-0.5 text-[11px] font-medium text-white">+{{ count($images) - 1 }}</span>
                @endif
            </{{ $media }}>
        @endif

        <div class="min-w-0 flex-1">
            @if ($post->title)
                <h3 class="line-clamp-2 font-semibold text-zinc-900">{{ $post->title }}</h3>
            @endif
            @if ($post->body)
                <p @class([
                    'text-sm whitespace-pre-line text-zinc-600',
                    'mt-1' => $post->title,
                    'line-clamp-2' => $images,
                    'line-clamp-4' => ! $images,
                ])>{{ $post->body }}</p>
            @endif
        </div>
    </div>
</article>
