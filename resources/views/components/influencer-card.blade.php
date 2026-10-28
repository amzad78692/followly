@props(['influencer', 'followed' => false])

<div {{ $attributes->class('flex flex-col rounded-2xl border border-zinc-200 bg-white p-4 transition hover:shadow-lg hover:shadow-zinc-900/5') }}>
    <a href="{{ route('influencers.show', $influencer) }}" wire:navigate class="block">
        <img src="{{ $influencer->avatar }}" alt="" class="size-16 rounded-full object-cover ring-4 ring-indigo-50">
        <p class="mt-3 flex items-center gap-1 text-sm font-semibold text-zinc-900">
            <span class="truncate">{{ $influencer->name }}</span>
            @if ($influencer->is_verified)
                <x-verified class="size-3.5" />
            @endif
        </p>
        <p class="text-xs text-indigo-500">{{ $influencer->category?->name ?? '—' }}</p>
        <p class="text-xs text-zinc-500">{{ $influencer->country?->name }}</p>
    </a>

    <ul class="mt-3 space-y-1.5">
        @foreach ($influencer->accounts as $account)
            <li class="flex items-center gap-2 text-xs font-medium text-zinc-700">
                <x-platform-icon :platform="$account->platform" size="size-4" /> {{ $account->formatted_followers }}
            </li>
        @endforeach
    </ul>

    <p class="mt-3 text-[11px] text-indigo-500">{{ $influencer->formatted_tracking }} tracking on {{ config('app.name') }}</p>

    <div class="mt-auto pt-3">
        @if ($followed)
            <flux:button size="sm" icon="check" class="w-full" wire:click="toggle({{ $influencer->id }})">Following</flux:button>
        @else
            <flux:button size="sm" variant="primary" class="w-full" wire:click="toggle({{ $influencer->id }})">Follow</flux:button>
        @endif
    </div>
</div>
