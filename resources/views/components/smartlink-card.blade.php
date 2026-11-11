@props(['post'])

{{-- A post card; for a SmartLink group, a bar with the same story on each platform. --}}
@php($versions = $post->relationLoaded('versions') ? $post->versions->filter->permalink->unique('platform') : collect())

@if ($versions->count() > 1)
    <div {{ $attributes->class('card') }}>
        <x-post-card :post="$post" class="border-0! bg-transparent! shadow-none!" />
        <div class="flex flex-wrap items-center gap-2 border-t border-zinc-100 px-4 py-2.5 sm:px-5">
            <flux:icon.link class="size-4 text-accent" />
            <span class="rounded-full bg-accent/10 px-2 py-0.5 text-xs font-semibold text-accent">SmartLink</span>
            <span class="text-xs text-zinc-500">Posted on {{ $versions->count() }} platforms</span>
            <span class="flex items-center">
                @foreach ($versions as $version)
                    <a href="{{ $version->permalink }}" target="_blank" rel="noopener" class="inline-flex rounded-full p-1.5 transition hover:scale-110" aria-label="Open on {{ $version->platform->label() }}" title="Open on {{ $version->platform->label() }}">
                        <x-platform-icon :platform="$version->platform" size="size-4" />
                    </a>
                @endforeach
            </span>
            <flux:dropdown align="end" class="ms-auto">
                <flux:button variant="ghost" size="sm" icon="chevron-down" aria-label="Open this post on another platform" />
                <flux:menu>
                    @foreach ($versions as $version)
                        <flux:menu.item :href="$version->permalink" target="_blank">
                            <x-platform-icon :platform="$version->platform" size="size-4" class="me-2" /> Open on {{ $version->platform->label() }}
                        </flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>
@else
    <x-post-card :post="$post" {{ $attributes }} />
@endif
