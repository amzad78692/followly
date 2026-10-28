<?php

use App\Support\Demo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] class extends Component {
    public string $slug;

    #[Url]
    public string $tab = 'posts';

    public function mount(string $slug): void
    {
        abort_unless(Demo::find($slug), 404);

        $this->slug = $slug;
    }

    #[Computed]
    public function influencer(): array
    {
        return Demo::find($this->slug);
    }

    #[Computed]
    public function posts()
    {
        return Demo::posts()->where('influencer', $this->slug)->sortByDesc('posted_at')->values();
    }

    #[Computed]
    public function related()
    {
        return Demo::influencers()
            ->where('slug', '!=', $this->slug)
            ->sortByDesc(fn ($i) => $i['category'] === $this->influencer['category'])
            ->take(4)
            ->values();
    }

    public function toggle(string $slug): void
    {
        Demo::toggle($slug);
        unset($this->influencer, $this->related);
    }

    public function rendering($view): void
    {
        $view->title($this->influencer['name']);
    }
}; ?>

@php $influencer = $this->influencer; @endphp

<div class="mx-auto max-w-5xl">
    {{-- Cover + identity --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white">
        <div class="relative h-40 bg-linear-to-r from-sky-400 via-indigo-500 to-violet-500 sm:h-52">
            <img src="https://picsum.photos/seed/flw-cover-{{ $influencer['slug'] }}/1400/400" alt="" class="size-full object-cover opacity-60 mix-blend-overlay">
            <div class="absolute top-3 end-3 flex gap-2">
                <flux:button size="sm" variant="ghost" icon="share" class="bg-white/80! backdrop-blur" aria-label="Share" />
                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" class="bg-white/80! backdrop-blur" aria-label="More" />
            </div>
        </div>

        <div class="px-5 pb-5 sm:px-8">
            <div class="-mt-14 flex flex-wrap items-end gap-4 sm:-mt-16">
                <img src="{{ $influencer['avatar_url'] }}" alt="" class="relative size-28 rounded-full bg-white object-cover ring-4 ring-white sm:size-32">

                <div class="min-w-0 flex-1 pt-16 pb-1">
                    <h1 class="flex items-center gap-2 text-2xl font-bold text-zinc-900">{{ $influencer['name'] }} <x-verified class="size-5" /></h1>
                    <p class="text-sm text-zinc-500">{{ array_values($influencer['platforms'])[0]['handle'] }} · {{ $influencer['real_name'] }}</p>
                </div>

                <div class="flex items-center gap-2 pb-1">
                    @if ($influencer['followed'])
                        <flux:button icon="check" wire:click="toggle('{{ $influencer['slug'] }}')">Following</flux:button>
                    @else
                        <flux:button variant="primary" class="px-6!" wire:click="toggle('{{ $influencer['slug'] }}')">Follow</flux:button>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-1.5">
                <flux:badge size="sm">{{ $influencer['category'] }}</flux:badge>
                <flux:badge size="sm">{{ $influencer['country'] }}</flux:badge>
                <flux:badge size="sm">{{ $influencer['language'] }}</flux:badge>
            </div>

            {{-- Stats --}}
            <div class="mt-6 grid grid-cols-3 gap-y-5 sm:grid-cols-6">
                @foreach ($influencer['platforms'] as $platform => $account)
                    <div>
                        <p class="flex items-center gap-1.5 text-lg font-bold text-zinc-900"><x-platform-icon :platform="$platform" size="size-4" /> {{ $account['count'] }}</p>
                        <p class="text-xs text-zinc-500">{{ \App\Support\Demo::PLATFORMS[$platform]['unit'] }}</p>
                    </div>
                @endforeach
                <div class="sm:border-s sm:border-zinc-200 sm:ps-4">
                    <p class="text-lg font-bold text-zinc-900">{{ $influencer['combined'] }}</p>
                    <p class="text-xs text-zinc-500">Combined Followers<br><span class="text-zinc-400">(Not unique people)</span></p>
                </div>
                <div>
                    <p class="text-lg font-bold text-zinc-900">{{ $influencer['tracking'] }}</p>
                    <p class="text-xs text-zinc-500">Tracking on {{ config('app.name') }}</p>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="flex gap-6 border-t border-zinc-200 px-5 sm:px-8">
            @foreach (['posts' => 'Latest Posts', 'about' => 'About', 'related' => 'Related Influencers'] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                    '-mb-px border-b-2 py-3 text-sm font-medium transition',
                    'border-indigo-600 text-indigo-600' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800' => $tab !== $key,
                ])>{{ $label }}</button>
            @endforeach
        </nav>
    </div>

    {{-- Tab content --}}
    <div class="mt-6">
        @if ($tab === 'posts')
            <div class="grid gap-6 lg:grid-cols-[1fr_16rem]">
                <div class="space-y-4">
                    @forelse ($this->posts as $post)
                        <x-post-card :post="$post" compact wire:key="post-{{ $post['id'] }}" />
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
                            <flux:text>No recent posts.</flux:text>
                        </div>
                    @endforelse
                </div>

                <aside class="h-fit rounded-2xl border border-zinc-200 bg-white p-4 lg:sticky lg:top-24">
                    <flux:heading size="sm" class="font-semibold!">Across Platforms</flux:heading>
                    <ul class="mt-3 space-y-3">
                        @foreach (array_keys($influencer['platforms']) as $platform)
                            @php $last = $this->posts->firstWhere('platform', $platform); @endphp
                            <li class="flex items-center gap-2 text-sm">
                                <x-platform-icon :platform="$platform" size="size-4" />
                                <span class="flex-1 text-zinc-700">{{ \App\Support\Demo::PLATFORMS[$platform]['label'] }}</span>
                                <span class="text-xs text-zinc-400">{{ $last ? $last['posted_at']->diffForHumans(short: true) : '—' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </aside>
            </div>
        @elseif ($tab === 'about')
            <div class="rounded-2xl border border-zinc-200 bg-white p-6">
                <flux:heading size="lg" class="font-semibold!">About {{ $influencer['name'] }}</flux:heading>
                <flux:text class="mt-2">{{ $influencer['bio'] }}</flux:text>

                <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                    @foreach (['Category' => $influencer['category'], 'Country' => $influencer['country'], 'Language' => $influencer['language']] as $label => $value)
                        <div class="rounded-xl bg-zinc-50 p-4">
                            <dt class="text-xs text-zinc-500">{{ $label }}</dt>
                            <dd class="mt-1 font-semibold text-zinc-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <flux:heading class="mt-6 font-semibold!">Accounts</flux:heading>
                <ul class="mt-2 divide-y divide-zinc-100">
                    @foreach ($influencer['platforms'] as $platform => $account)
                        <li class="flex items-center gap-3 py-2.5 text-sm">
                            <x-platform-icon :platform="$platform" size="size-5" />
                            <span class="w-24 font-medium text-zinc-800">{{ \App\Support\Demo::PLATFORMS[$platform]['label'] }}</span>
                            <span class="flex-1 text-zinc-500">{{ $account['handle'] }}</span>
                            <span class="text-zinc-500">{{ $account['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($this->related as $item)
                    <x-influencer-card :influencer="$item" wire:key="rel-{{ $item['slug'] }}" />
                @endforeach
            </div>
        @endif
    </div>
</div>
