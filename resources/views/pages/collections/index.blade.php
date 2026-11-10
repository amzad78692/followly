<?php

use App\Models\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.dashboard'), Title('Collections')] class extends Component {
    public string $name = '';

    public ?int $editingId = null;

    public string $editingName = '';

    #[Computed]
    public function collections()
    {
        return auth()->user()->collections()->withCount('influencers')->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->validate(['name' => 'required|string|max:60']);

        auth()->user()->collections()->create(['name' => $this->name]);

        $this->reset('name');
        unset($this->collections);
    }

    public function edit(int $id): void
    {
        $collection = $this->owned($id);
        $this->editingId = $collection->id;
        $this->editingName = $collection->name;
    }

    public function rename(): void
    {
        $this->validate(['editingName' => 'required|string|max:60']);

        $this->owned($this->editingId)->update(['name' => $this->editingName]);

        $this->reset('editingId', 'editingName');
        unset($this->collections);
    }

    public function delete(int $id): void
    {
        $collection = Collection::findOrFail($id);
        $this->authorize('delete', $collection);
        $collection->delete();

        unset($this->collections);
    }

    public function share(int $id): void
    {
        $this->owned($id)->update(['share_token' => Str::random(32)]);
        unset($this->collections);
    }

    public function unshare(int $id): void
    {
        $this->owned($id)->update(['share_token' => null]);
        unset($this->collections);
    }

    protected function owned(?int $id): Collection
    {
        $collection = Collection::findOrFail($id);
        $this->authorize('update', $collection);

        return $collection;
    }
}; ?>

<div class="mx-auto max-w-3xl">
    <flux:heading size="xl" class="font-bold!">Collections</flux:heading>
    <flux:text class="mt-1">Group influencers and open a collection as a filtered feed.</flux:text>

    <form wire:submit="create" class="mt-6 flex gap-2">
        <flux:input wire:model="name" placeholder="New collection name" class="flex-1" aria-label="New collection name" />
        <flux:button type="submit" variant="primary" icon="plus">Create</flux:button>
    </form>
    <flux:error name="name" />

    <ul class="mt-6 space-y-3">
        @forelse ($this->collections as $collection)
            <li class="rounded-2xl border border-zinc-200 bg-white p-4" wire:key="collection-{{ $collection->id }}">
                @if ($editingId === $collection->id)
                    <form wire:submit="rename" class="flex gap-2">
                        <flux:input wire:model="editingName" class="flex-1" aria-label="Collection name" />
                        <flux:button type="submit" variant="primary">Save</flux:button>
                        <flux:button wire:click="$set('editingId', null)">Cancel</flux:button>
                    </form>
                    <flux:error name="editingName" />
                @else
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ url('/dashboard') }}?collection={{ $collection->id }}" wire:navigate class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-zinc-900">{{ $collection->name }}</p>
                            <p class="text-xs text-zinc-500">{{ trans_choice(':count influencer|:count influencers', $collection->influencers_count) }}</p>
                        </a>
                        @if ($collection->share_token)
                            <flux:button size="sm" wire:click="unshare({{ $collection->id }})">Unshare</flux:button>
                        @else
                            <flux:button size="sm" icon="share" wire:click="share({{ $collection->id }})">Share</flux:button>
                        @endif
                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $collection->id }})" aria-label="Rename" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $collection->id }})" wire:confirm="Delete this collection?" aria-label="Delete" />
                    </div>
                    @if ($collection->share_token)
                        <flux:input class="mt-3" size="sm" readonly copyable :value="route('collections.shared', $collection->share_token)" aria-label="Share link" />
                    @endif
                @endif
            </li>
        @empty
            <li class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
                <flux:text>No collections yet. Create one, then add influencers from their card or profile.</flux:text>
            </li>
        @endforelse
    </ul>
</div>
