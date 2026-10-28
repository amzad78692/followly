@props(['influencer'])

<div {{ $attributes->class('flex flex-col rounded-2xl border border-zinc-200 bg-white p-4 transition hover:shadow-lg hover:shadow-zinc-900/5') }}>
    <a href="{{ route('influencers.show', $influencer['slug']) }}" wire:navigate class="block">
        <img src="{{ $influencer['avatar_url'] }}" alt="" class="size-16 rounded-full object-cover ring-4 ring-indigo-50">
        <p class="mt-3 flex items-center gap-1 text-sm font-semibold text-zinc-900">
            <span class="truncate">{{ $influencer['name'] }}</span> <x-verified class="size-3.5" />
        </p>
        <p class="text-xs text-indigo-500">{{ $influencer['category'] }}</p>
        <p class="text-xs text-zinc-500">{{ $influencer['country'] }}</p>
    </a>

    <ul class="mt-3 space-y-1.5">
        @foreach ($influencer['platforms'] as $platform => $account)
            <li class="flex items-center gap-2 text-xs font-medium text-zinc-700">
                <x-platform-icon :platform="$platform" size="size-4" /> {{ $account['count'] }}
            </li>
        @endforeach
    </ul>

    <p class="mt-3 text-[11px] text-indigo-500">{{ $influencer['tracking'] }} tracking on {{ config('app.name') }}</p>

    <div class="mt-auto pt-3">
        @if ($influencer['followed'])
            <flux:button size="sm" icon="check" class="w-full" wire:click="toggle('{{ $influencer['slug'] }}')">Following</flux:button>
        @else
            <flux:button size="sm" variant="primary" class="w-full" wire:click="toggle('{{ $influencer['slug'] }}')">Follow</flux:button>
        @endif
    </div>
</div>
