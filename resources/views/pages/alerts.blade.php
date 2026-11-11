<?php

use App\Enums\Platform;
use App\Models\Post;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Followly Alerts: keywords the user wants to hear about, on/off switches, and the posts that matched.
 * Matching itself happens in SendKeywordAlerts after each sync.
 */
new #[Layout('components.layouts.dashboard')] #[Title('Alerts')] class extends Component {
    use WithPagination;

    public const MAX_KEYWORDS = 20;

    #[Url]
    public string $tab = 'posts';

    public bool $alertsEnabled = true;

    public string $keyword = '';

    /** Keyword being renamed in the modal; null when adding. */
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->alertsEnabled = auth()->user()->alerts_enabled;
    }

    public function updatedAlertsEnabled(): void
    {
        auth()->user()->update(['alerts_enabled' => $this->alertsEnabled]);
    }

    #[Computed]
    public function keywords()
    {
        return auth()->user()->keywordAlerts()->oldest()->get();
    }

    /**
     * The user's keyword alert notifications, newest first, with their posts attached (missing when deleted).
     */
    #[Computed]
    public function alerts()
    {
        $alerts = auth()->user()->notifications()->where('type', 'keyword-alert')->paginate(10);
        $posts = Post::with('influencer')->whereKey($alerts->pluck('data.post_id'))->get()->keyBy('id');

        $alerts->each(fn ($alert) => $alert->post = $posts[$alert->data['post_id'] ?? 0] ?? null);

        return $alerts;
    }

    public function startAdd(): void
    {
        $this->reset('keyword', 'editingId');
        $this->resetErrorBag();
        Flux::modal('keyword')->show();
    }

    public function startRename(int $id): void
    {
        $alert = auth()->user()->keywordAlerts()->findOrFail($id);
        $this->editingId = $alert->id;
        $this->keyword = $alert->keyword;
        $this->resetErrorBag();
        Flux::modal('keyword')->show();
    }

    public function saveKeyword(): void
    {
        $this->keyword = Str::squish($this->keyword);

        $this->validate(['keyword' => ['required', 'string', 'min:2', 'max:50',
            Rule::unique('keyword_alerts')->where('user_id', auth()->id())->ignore($this->editingId),
        ]], ['keyword.unique' => 'You already have this keyword.']);

        if ($this->editingId) {
            auth()->user()->keywordAlerts()->findOrFail($this->editingId)->update(['keyword' => $this->keyword]);
        } elseif ($this->keywords->count() >= self::MAX_KEYWORDS) {
            $this->addError('keyword', 'You can have up to '.self::MAX_KEYWORDS.' keywords. Remove one first.');

            return;
        } else {
            auth()->user()->keywordAlerts()->create(['keyword' => $this->keyword]);
        }

        $this->reset('keyword', 'editingId');
        unset($this->keywords);
        Flux::modal('keyword')->close();
    }

    public function toggleKeyword(int $id): void
    {
        $alert = auth()->user()->keywordAlerts()->findOrFail($id);
        $alert->update(['enabled' => ! $alert->enabled]);

        unset($this->keywords);
    }

    public function removeKeyword(int $id): void
    {
        auth()->user()->keywordAlerts()->whereKey($id)->delete();

        unset($this->keywords);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6">
    <div class="card flex flex-wrap items-center gap-4 p-5 sm:p-6">
        <x-page-header icon="bell-alert" title="Followly Alerts" subtitle="Get notified when your followed influencers post about topics you care about." class="min-w-0 flex-1 basis-72" />
        <flux:switch wire:model.live="alertsEnabled" :label="$alertsEnabled ? 'Alerts On' : 'Alerts Off'" align="left" class="shrink-0" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card min-w-0 p-5 sm:p-6 lg:col-span-2" aria-labelledby="my-keywords">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="my-keywords" class="font-display text-lg font-semibold text-zinc-900">My Keyword Alerts</h2>
                <flux:button variant="primary" size="sm" icon="plus" wire:click="startAdd">Add Keyword</flux:button>
            </div>

            @unless ($alertsEnabled)
                <flux:callout icon="bell-slash" color="amber" class="mt-4" text="Alerts are off. Your keywords are kept, but you won't be notified until you switch Alerts on." />
            @endunless

            <ul class="mt-4 space-y-2">
                @forelse ($this->keywords as $alert)
                    <li wire:key="keyword-{{ $alert->id }}" @class(['flex items-center gap-3 rounded-2xl border border-zinc-200/70 bg-white px-3 py-2.5', 'opacity-60' => ! $alert->enabled])>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent/15 font-semibold text-accent">
                            {{ Str::upper(Str::substr($alert->keyword, 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-zinc-900">{{ $alert->keyword }}</p>
                            <p class="truncate text-xs text-zinc-500">Across my followed influencers</p>
                        </div>
                        <flux:switch :checked="$alert->enabled" wire:click="toggleKeyword({{ $alert->id }})" aria-label="Alert for {{ $alert->keyword }}" />
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" aria-label="Options for {{ $alert->keyword }}" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="startRename({{ $alert->id }})">Rename</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="removeKeyword({{ $alert->id }})" wire:confirm="Delete the alert for “{{ $alert->keyword }}”?">Delete</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </li>
                @empty
                    <li class="rounded-2xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500">
                        No keywords yet. Add one, like a product, brand or topic.
                    </li>
                @endforelse
            </ul>

            <p class="mt-3 text-xs text-zinc-500">{{ $this->keywords->count() }} of {{ $this::MAX_KEYWORDS }} keywords · whole words, any capitalisation.</p>
        </section>

        <section class="card min-w-0 p-5 sm:p-6" aria-labelledby="how-alerts-work">
            <h2 id="how-alerts-work" class="font-display text-lg font-semibold text-zinc-900">How Alerts Work</h2>
            <ol class="mt-4 space-y-4">
                @foreach ([
                    ['plus-circle', 'Add keywords', 'Topics, products or names you care about.'],
                    ['magnifying-glass', 'We scan new posts', 'From every influencer you follow, on every platform.'],
                    ['bell-alert', 'Get notified', 'When they mention one of your topics.'],
                ] as [$icon, $step, $text])
                    <li class="flex gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent/15 text-accent">
                            <flux:icon :icon="$icon" class="size-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-zinc-900">{{ $step }}</p>
                            <p class="text-sm text-zinc-500">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <div>
        <nav class="flex gap-6 border-b border-zinc-200 text-sm font-medium" aria-label="Alerts sections">
            @foreach (['posts' => 'Alert Posts', 'settings' => 'Alert Settings'] as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                    '-mb-px border-b-2 pb-3 transition',
                    'border-accent text-zinc-900' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800' => $tab !== $key,
                ]) @if ($tab === $key) aria-current="page" @endif>{{ $label }}</button>
            @endforeach
        </nav>

        @if ($tab === 'settings')
            @php($user = auth()->user())
            <section class="card mt-6 p-5 sm:p-6">
                <h2 class="font-display text-lg font-semibold text-zinc-900">Delivery</h2>
                <p class="mt-1 text-sm text-zinc-500">Alerts reach you the same way as new post notifications.</p>
                <dl class="mt-4 divide-y divide-zinc-100 text-sm">
                    @foreach ([
                        'In-app notifications' => $user->notify_in_app ? 'On' : 'Off',
                        'Email digest' => $user->email_digest?->label() ?? 'Off',
                        'Push to watch' => $user->notify_push ? 'On' : 'Off',
                        'Platforms' => collect(Platform::cases())->filter(fn ($p) => $user->wantsPostsFrom($p))->map->label()->join(', ') ?: 'None',
                    ] as $name => $value)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-zinc-600">{{ $name }}</dt>
                            <dd class="text-end font-medium text-zinc-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <flux:button :href="route('settings').'#notifications'" wire:navigate icon="cog-6-tooth" class="mt-4">Change notification preferences</flux:button>
            </section>
        @else
            <div class="mt-6 space-y-4">
                @forelse ($this->alerts as $alert)
                    @continue(! $alert->post)
                    @php($post = $alert->post)
                    <div wire:key="alert-{{ $alert->id }}" class="card p-4 sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="rounded-full bg-accent px-3 py-1 text-xs font-semibold text-white">{{ $alert->data['keywords'][0] }}</span>
                            <span class="rounded-full bg-accent/15 px-3 py-1 text-xs font-medium text-accent">Matched: {{ implode(', ', $alert->data['keywords']) }}</span>
                        </div>
                        <x-post-card :post="$post" class="mt-1 border-0! bg-transparent! p-0! shadow-none!" />
                        @if ($post->permalink)
                            <div class="mt-3 flex justify-end">
                                <flux:button size="sm" variant="primary" :href="$post->permalink" target="_blank" icon:trailing="arrow-top-right-on-square">
                                    {{ $post->duration ? 'Watch' : 'View' }} on {{ $post->platform->label() }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="card p-8 text-center">
                        <flux:icon.bell-alert class="mx-auto size-10 text-accent" />
                        <p class="mt-3 font-semibold text-zinc-900">No alert posts yet</p>
                        <p class="mt-1 text-sm text-zinc-500">When an influencer you follow mentions one of your keywords, the post shows up here.</p>
                    </div>
                @endforelse

                <flux:pagination :paginator="$this->alerts" />
            </div>
        @endif
    </div>

    <flux:modal name="keyword" class="w-full max-w-md">
        <form wire:submit="saveKeyword" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Rename keyword' : 'Add a keyword' }}</flux:heading>
                <flux:text class="mt-1">Matches whole words in new posts, any capitalisation.</flux:text>
            </div>
            <flux:input wire:model="keyword" label="Keyword" placeholder="e.g. Bitcoin" maxlength="50" autocomplete="off" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save' : 'Add keyword' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
