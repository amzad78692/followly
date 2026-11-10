@props(['influencer', 'followed' => false])

<div {{ $attributes->class('flex flex-col rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-lg hover:shadow-zinc-900/5') }}>
    <a href="{{ route('influencers.show', $influencer) }}" wire:navigate class="block text-center">
        <img src="{{ image_url($influencer->avatar, 128, 128) }}" alt="" class="mx-auto size-16 rounded-full object-cover ring-4 ring-sand">
        <p class="mt-3 flex items-center justify-center gap-1 font-display text-sm font-semibold text-zinc-900">
            <span class="truncate">{{ $influencer->name }}</span>
            @if ($influencer->is_verified)
                <x-verified class="size-3.5" />
            @endif
        </p>
        <p class="truncate text-xs text-zinc-500">{{ collect([$influencer->category?->name, $influencer->country?->name])->filter()->join(' · ') ?: '—' }}</p>
    </a>

    <ul class="mt-3 space-y-1.5">
        @foreach ($influencer->accounts as $account)
            <li class="flex items-center gap-2 rounded-lg bg-zinc-50 px-2 py-1 text-xs font-medium text-zinc-700">
                <x-platform-icon :platform="$account->platform" size="size-4" />
                <span class="flex-1 truncate">{{ $account->platform->label() }}</span>
                {{ $account->formatted_followers }}
            </li>
        @endforeach
    </ul>

    <p class="mt-3 text-center text-[11px] text-zinc-500">{{ $influencer->formatted_tracking }} tracking on {{ config('app.name') }}</p>

    <div class="mt-auto pt-3">
        @auth <div class="mb-2"><livewire:influencer-actions :influencer-id="$influencer->id" :key="'actions-'.$attributes->get('wire:key', $influencer->id)" /></div> @endauth
        @if ($followed)
            <flux:button size="sm" icon="check" class="w-full" wire:click="toggle({{ $influencer->id }})">Following</flux:button>
        @else
            <flux:button size="sm" variant="primary" class="w-full" wire:click="toggle({{ $influencer->id }})">Follow</flux:button>
        @endif
    </div>
</div>
