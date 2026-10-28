<?php

use App\Support\Demo;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard')] #[Title('Add Influencer')] class extends Component {
    #[Validate('required|string|max:255', as: 'link or username')]
    public string $query = '';

    public ?array $result = null;

    public array $selected = [];

    public bool $searched = false;

    public function search(): void
    {
        $this->validate();

        $this->result = Demo::lookup($this->query);
        $this->selected = $this->result ? array_keys($this->result['platforms']) : [];
        $this->searched = true;
    }

    public function track()
    {
        $this->validate(['selected' => 'required|array|min:1'], ['selected.required' => 'Select at least one account to track.']);

        Demo::follow($this->result['slug']);

        return $this->redirectRoute('influencers.show', $this->result['slug'], navigate: true);
    }

    public function cancel(): void
    {
        $this->reset();
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <flux:heading size="xl" level="1" class="font-bold!">Add Influencer</flux:heading>
    <flux:text class="mt-1">Paste a link or username and we'll find their social media accounts.</flux:text>

    <form wire:submit="search" class="mt-6 flex gap-3">
        <div class="flex-1">
            <flux:input wire:model="query" placeholder="https://www.youtube.com/@MrBeast" icon="link" />
            <flux:error name="query" />
        </div>
        <flux:button type="submit" variant="primary" class="px-6!">Search</flux:button>
    </form>

    <div wire:loading.flex wire:target="search" class="mt-6 items-center gap-2 text-sm text-zinc-500">
        <flux:icon.loading class="size-4" /> Looking for accounts…
    </div>

    @if ($result)
        <div wire:loading.remove wire:target="search">
            <flux:heading class="mt-8 font-semibold!">We found these accounts</flux:heading>

            <div class="mt-3 rounded-2xl border border-zinc-200 bg-white p-5">
                <div class="flex items-center gap-4">
                    <img src="{{ $result['avatar_url'] }}" alt="" class="size-20 rounded-full object-cover ring-4 ring-indigo-50">
                    <div>
                        <p class="flex items-center gap-1.5 text-xl font-bold text-zinc-900">{{ $result['name'] }} <x-verified class="size-5" /></p>
                        <p class="text-sm text-zinc-500">{{ $result['real_name'] }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <flux:badge size="sm">{{ $result['category'] }}</flux:badge>
                            <flux:badge size="sm">{{ $result['country'] }}</flux:badge>
                            <flux:badge size="sm">{{ $result['language'] }}</flux:badge>
                        </div>
                    </div>
                </div>

                <ul class="mt-5 divide-y divide-zinc-100 border-y border-zinc-100">
                    @foreach ($result['platforms'] as $platform => $account)
                        <li wire:key="acc-{{ $platform }}">
                            <label class="flex cursor-pointer items-center gap-4 py-3">
                                <flux:checkbox wire:model.live="selected" :value="$platform" />
                                <x-platform-icon :platform="$platform" size="size-6" />
                                <span class="w-28 text-sm font-medium text-zinc-800">{{ \App\Support\Demo::PLATFORMS[$platform]['label'] }}</span>
                                <span class="flex-1 text-sm text-zinc-500">{{ $account['handle'] }}</span>
                                <span class="text-sm text-zinc-500">{{ $account['count'] }} {{ strtolower(\App\Support\Demo::PLATFORMS[$platform]['unit']) }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>

                <flux:error name="selected" class="mt-3" />

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-[2fr_1fr]">
                    <flux:button variant="primary" wire:click="track" :disabled="empty($selected)">
                        Start Tracking Selected ({{ count($selected) }})
                    </flux:button>
                    <flux:button wire:click="cancel">Cancel</flux:button>
                </div>
            </div>
        </div>
    @elseif ($searched)
        <div wire:loading.remove wire:target="search" class="mt-8 rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
            <flux:icon.magnifying-glass class="mx-auto size-10 text-zinc-300" />
            <flux:heading class="mt-3">No accounts found</flux:heading>
            <flux:text class="mt-1">Try a different link or username, e.g. <span class="font-medium">@mkbhd</span> or <span class="font-medium">youtube.com/@MrBeast</span>.</flux:text>
        </div>
    @endif
</div>
