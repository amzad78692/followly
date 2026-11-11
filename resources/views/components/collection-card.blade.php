@props(['collection', 'small' => false, 'owner' => false])

{{-- Collection card: cover, name, public badge, description, avatars, count; buttons go in the slot. --}}
@php($cover = $collection->coverUrl())
<article {{ $attributes->class('card flex flex-col overflow-hidden p-0!') }}>
    <div @class(['relative w-full bg-linear-to-br from-amber-100 via-sand to-amber-200', 'h-24' => $small, 'h-36 sm:h-44' => ! $small])>
        @if ($cover)
            <img src="{{ $cover }}" alt="" class="absolute inset-0 size-full object-cover" loading="lazy">
        @else
            <flux:icon.folder variant="outline" class="absolute inset-0 m-auto size-10 text-accent/60" />
        @endif
    </div>

    <div @class(['flex flex-1 flex-col', 'p-3' => $small, 'p-4 sm:p-5' => ! $small])>
        <div class="flex flex-wrap items-center gap-2">
            <h3 @class(['font-display font-bold text-zinc-900', 'truncate text-sm' => $small, 'text-lg' => ! $small])>{{ $collection->name }}</h3>
            @if ($collection->is_public && ! $small)
                <span class="inline-flex items-center gap-1 rounded-full bg-accent/10 px-2 py-0.5 text-xs font-medium text-accent">🌐 Public Collection</span>
            @endif
        </div>
        @if ($owner)
            <p class="mt-0.5 text-xs text-zinc-500">by {{ $collection->user->name }}</p>
        @endif
        @if ($collection->description && ! $small)
            <p class="mt-1 line-clamp-2 text-sm text-zinc-600">{{ $collection->description }}</p>
        @endif

        <div class="mt-3 flex items-center gap-2">
            <div class="flex -space-x-2">
                @foreach ($collection->influencers->take($small ? 3 : 5) as $influencer)
                    <flux:avatar circle size="xs" class="ring-2 ring-ivory" :src="image_url($influencer->avatar, 48, 48)" :name="$influencer->name" />
                @endforeach
            </div>
            <span class="text-xs font-medium text-zinc-600">{{ trans_choice(':count Influencer|:count Influencers', $collection->influencers_count ?? $collection->influencers->count()) }}</span>
        </div>

        @if ($slot->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</article>
