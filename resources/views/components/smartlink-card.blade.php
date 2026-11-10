@props(['post'])

{{-- A post card; for a SmartLink group, with links to the same story on each platform. --}}
@php($versions = $post->relationLoaded('versions') ? $post->versions->filter->permalink->unique('platform') : collect())

@if ($versions->count() > 1)
    <div {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white') }}>
        <x-post-card :post="$post" class="border-0! bg-transparent!" />
        <div class="flex flex-wrap items-center gap-0.5 border-t border-zinc-100 px-4 py-3 sm:px-5">
            <span class="text-xs text-zinc-500">Also on</span>
            @foreach ($versions as $version)
                <a href="{{ $version->permalink }}" target="_blank" rel="noopener" class="inline-flex rounded-full p-2.5 transition hover:scale-110" aria-label="Open on {{ $version->platform->label() }}" title="Open on {{ $version->platform->label() }}">
                    <x-platform-icon :platform="$version->platform" size="size-5" />
                </a>
            @endforeach
        </div>
    </div>
@else
    <x-post-card :post="$post" {{ $attributes }} />
@endif
