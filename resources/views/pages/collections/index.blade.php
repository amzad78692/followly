<?php

use App\Models\Collection;
use chillerlan\QRCode\QRCode;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.dashboard'), Title('Collections')] class extends Component {
    use WithFileUploads;

    #[Url]
    public string $tab = 'mine';

    #[Url(as: 'c')]
    public ?int $selectedId = null;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public $cover = null;

    public ?string $notice = null;

    #[Computed]
    public function collections()
    {
        return auth()->user()->collections()->with(['influencers.media', 'media'])->withCount('influencers')->orderBy('name')->get();
    }

    #[Computed]
    public function shared()
    {
        return $this->collections->filter(fn (Collection $c) => $c->share_token || $c->is_public)->values();
    }

    #[Computed]
    public function selected(): ?Collection
    {
        return $this->shared->firstWhere('id', $this->selectedId) ?? $this->shared->first();
    }

    /**
     * Public collections from every user. Owners are loaded by name only.
     */
    #[Computed]
    public function explore()
    {
        // shortcut: newest 24 only, add pagination when Explore grows.
        return Collection::where('is_public', true)->with(['user:id,name', 'influencers.media', 'media'])
            ->withCount('influencers')->latest()->limit(24)->get();
    }

    /**
     * QR code (an SVG data URI) for the selected collection's link.
     */
    #[Computed]
    public function qrCode(): ?string
    {
        return $this->selected ? (new QRCode)->render($this->selected->shareUrl()) : null;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $collection = $this->owned($id);
        $this->resetForm();
        $this->editingId = $collection->id;
        $this->name = $collection->name;
        $this->description = (string) $collection->description;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:60',
            'description' => 'nullable|string|max:500',
            'cover' => 'nullable|image|max:4096',
        ]);

        $fields = ['name' => $data['name'], 'description' => $data['description'] ?: null];
        $collection = $this->editingId
            ? tap($this->owned($this->editingId))->update($fields)
            : auth()->user()->collections()->create($fields);

        if ($this->cover) {
            $collection->addMedia($this->cover->getRealPath())
                ->usingFileName($this->cover->hashName())
                ->toMediaCollection('cover');
        }

        $this->resetForm();
        $this->showForm = false;
        unset($this->collections, $this->shared, $this->selected, $this->explore, $this->qrCode);
    }

    public function delete(int $id): void
    {
        $collection = Collection::findOrFail($id);
        $this->authorize('delete', $collection);
        $collection->delete();

        unset($this->collections, $this->shared, $this->selected, $this->explore, $this->qrCode);
    }

    /**
     * Create the share link if needed and open it in the share panel.
     */
    public function share(int $id): void
    {
        $this->owned($id)->shareUrl();
        $this->tab = 'shared';
        $this->selectedId = $id;
        unset($this->collections, $this->shared, $this->selected, $this->qrCode);
    }

    /**
     * Turn the link off. A collection without a link cannot stay public.
     */
    public function unshare(int $id): void
    {
        $this->owned($id)->update(['share_token' => null, 'is_public' => false]);
        unset($this->collections, $this->shared, $this->selected, $this->explore, $this->qrCode);
    }

    public function togglePublic(int $id): void
    {
        $collection = $this->owned($id);
        $collection->shareUrl();
        $collection->update(['is_public' => ! $collection->is_public]);
        unset($this->collections, $this->shared, $this->selected, $this->explore, $this->qrCode);
    }

    /**
     * Follow every influencer in a public collection; ones already followed keep their settings.
     */
    public function followAll(int $id): void
    {
        $collection = Collection::where('is_public', true)->findOrFail($id);
        $user = auth()->user();
        $followed = $user->follows()->pluck('influencer_id');

        $new = $collection->influencers()->whereNotIn('influencers.id', $followed)->get();
        $new->each(fn ($influencer) => $user->follow($influencer));

        $this->notice = $new->isEmpty()
            ? "You already follow everyone in {$collection->name}."
            : trans_choice('Followed :count new creator from :name.|Followed :count new creators from :name.', $new->count(), ['name' => $collection->name]);
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'name', 'description', 'cover');
        $this->resetValidation();
    }

    protected function owned(?int $id): Collection
    {
        $collection = Collection::findOrFail($id);
        $this->authorize('update', $collection);

        return $collection;
    }
}; ?>

@php
    $tabs = ['mine' => 'My Collections', 'shared' => 'Shared Collections', 'explore' => 'Explore Collections'];
@endphp

<div class="mx-auto max-w-6xl">
    @if ($tab === 'shared')
        <x-page-header icon="share" title="Followly Shared Collections" subtitle="Share your collections with a link, a QR code or your favourite app.">
            <flux:button variant="primary" icon="plus" wire:click="create">Create Collection</flux:button>
        </x-page-header>
    @else
        <x-page-header icon="folder" title="Collections" subtitle="Group creators, open them as a feed, and share them.">
            <flux:button variant="primary" icon="plus" wire:click="create">Create Collection</flux:button>
        </x-page-header>
    @endif

    <nav class="mt-6 flex gap-1 overflow-x-auto [scrollbar-width:none] border-b border-zinc-200" aria-label="Collection lists">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" aria-current="{{ $tab === $key ? 'page' : 'false' }}"
                @class(['shrink-0 border-b-2 px-3 py-2 text-sm font-medium', 'border-accent text-accent' => $tab === $key, 'border-transparent text-zinc-500 hover:text-zinc-800' => $tab !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </nav>

    @if ($notice)
        <flux:callout class="mt-4" variant="success" icon="check-circle" :heading="$notice" />
    @endif

    @if ($tab === 'mine')
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->collections as $collection)
                <x-collection-card :collection="$collection" wire:key="mine-{{ $collection->id }}">
                    <flux:button size="sm" variant="primary" icon="link" wire:click="share({{ $collection->id }})">Share Link</flux:button>
                    <flux:button size="sm" icon="pencil" wire:click="edit({{ $collection->id }})">Edit</flux:button>
                    <flux:dropdown align="end">
                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="More for {{ $collection->name }}" />
                        <flux:menu>
                            <flux:menu.item icon="rss" :href="route('dashboard', ['collection' => $collection->id])" wire:navigate>Open as feed</flux:menu.item>
                            @if ($collection->share_token)
                                <flux:menu.item icon="link-slash" wire:click="unshare({{ $collection->id }})">Stop sharing</flux:menu.item>
                            @endif
                            <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $collection->id }})" wire:confirm="Delete this collection?">Delete</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </x-collection-card>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
                    <flux:text>No collections yet. Create one, then add influencers from their card or profile.</flux:text>
                </div>
            @endforelse
        </div>
    @elseif ($tab === 'shared')
        @if ($selected = $this->selected)
            @php
                $link = $selected->shareUrl();
                $text = rawurlencode($selected->name.' on '.config('app.name'));
                $url = rawurlencode($link);
                $targets = [
                    'X' => ['https://twitter.com/intent/tweet?url='.$url.'&text='.$text, 'bg-zinc-900', 'M4 4l16 16M20 4L4 20'],
                    'Facebook' => ['https://www.facebook.com/sharer/sharer.php?u='.$url, 'bg-[#1877f2]', 'M14 8h2V4h-2a4 4 0 0 0-4 4v2H8v4h2v6h4v-6h2l1-4h-3V8z'],
                    'WhatsApp' => ['https://wa.me/?text='.$text.'%20'.$url, 'bg-[#25d366]', 'M5 19l1.3-3.8A7 7 0 1 1 9 18.2z'],
                    'Telegram' => ['https://t.me/share/url?url='.$url.'&text='.$text, 'bg-[#229ed9]', 'M3 11l17-7-3 16-5-4-3 3v-5l9-8-11 7z'],
                    'LinkedIn' => ['https://www.linkedin.com/sharing/share-offsite/?url='.$url, 'bg-[#0a66c2]', 'M6 9v10M6 5v.01M10 19v-6a3 3 0 0 1 6 0v6M10 9v10'],
                    'Email' => ['mailto:?subject='.$text.'&body='.$url, 'bg-accent', 'M3 6h18v12H3zM3 6l9 7 9-7'],
                ];
            @endphp
            <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
                <x-collection-card :collection="$selected" wire:key="selected-{{ $selected->id }}">
                    <flux:button size="sm" icon="pencil" wire:click="edit({{ $selected->id }})">Edit</flux:button>
                    <flux:button size="sm" variant="ghost" icon="rss" :href="route('dashboard', ['collection' => $selected->id])" wire:navigate>Open as feed</flux:button>
                </x-collection-card>

                <aside class="card p-4 sm:p-5" aria-labelledby="share-heading">
                    <h2 id="share-heading" class="font-display text-lg font-bold text-zinc-900">Share Your Collection</h2>
                    <flux:input class="mt-3" readonly copyable :value="$link" aria-label="Share link" />

                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach ($targets as $label => $target)
                            <li>
                                <a href="{{ $target[0] }}" target="_blank" rel="noopener" title="Share on {{ $label }}" aria-label="Share on {{ $label }}"
                                    class="{{ $target[1] }} inline-flex size-10 items-center justify-center rounded-full text-white hover:opacity-90">
                                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $target[2] }}"/></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-4 flex justify-center rounded-xl bg-white p-3">
                        <img src="{{ $this->qrCode }}" alt="QR code for {{ $selected->name }}" class="size-40">
                    </div>

                    <div class="mt-4 border-t border-zinc-200 pt-4">
                        <flux:switch :checked="$selected->is_public" wire:click="togglePublic({{ $selected->id }})" label="Make this collection public" description="Let others discover and follow it on Followly." />
                    </div>
                </aside>
            </div>

            <h2 class="mt-8 font-display text-lg font-bold text-zinc-900">Your Shared Collections</h2>
            <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($this->shared as $collection)
                    <button type="button" wire:click="$set('selectedId', {{ $collection->id }})" wire:key="shared-{{ $collection->id }}" aria-pressed="{{ $collection->id === $selected->id ? 'true' : 'false' }}"
                        @class(['rounded-2xl text-left', 'ring-2 ring-accent' => $collection->id === $selected->id])>
                        <x-collection-card :collection="$collection" small class="h-full" />
                    </button>
                @endforeach
            </div>
        @else
            <div class="mt-6 rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
                <flux:text>You haven't shared a collection yet. Press Share Link on one of your collections.</flux:text>
                <flux:button class="mt-4" wire:click="$set('tab', 'mine')">My Collections</flux:button>
            </div>
        @endif
    @else
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->explore as $collection)
                <x-collection-card :collection="$collection" owner wire:key="explore-{{ $collection->id }}">
                    <flux:button size="sm" variant="primary" icon="user-plus" wire:click="followAll({{ $collection->id }})">Follow all</flux:button>
                    <flux:button size="sm" icon="eye" :href="route('collections.shared', $collection->share_token)" wire:navigate>View</flux:button>
                </x-collection-card>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center">
                    <flux:text>No public collections yet. Make one of yours public from the Shared tab.</flux:text>
                </div>
            @endforelse
        </div>
    @endif

    <flux:modal wire:model.self="showForm" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Edit collection' : 'Create collection' }}</flux:heading>
            <flux:input wire:model="name" label="Name" maxlength="60" required />
            <flux:textarea wire:model="description" label="Description" rows="3" maxlength="500" />
            <flux:field>
                <flux:label>Cover image</flux:label>
                <input type="file" wire:model="cover" accept="image/*" class="block w-full text-sm text-zinc-600 file:me-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5">
                <flux:error name="cover" />
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
