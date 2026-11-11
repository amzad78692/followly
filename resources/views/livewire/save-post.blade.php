<?php

use App\Models\Post;
use Livewire\Volt\Component;

/**
 * Bookmark toggle on a post card. Tells the page via "post-saved" so a Saved list can refresh.
 */
new class extends Component {
    public int $post;

    public bool $saved = false;

    public function toggle(): void
    {
        $user = auth()->user();

        if ($this->saved) {
            $user->savedPosts()->detach($this->post);
        } elseif (Post::visible()->whereKey($this->post)->exists()) {
            $user->savedPosts()->syncWithoutDetaching([$this->post]);
        }

        $this->saved = $user->savedPosts()->whereKey($this->post)->exists();
        $this->dispatch('post-saved');
    }
}; ?>

<div>
    <flux:button variant="ghost" size="sm" icon="bookmark" :icon:variant="$saved ? 'solid' : 'outline'" wire:click="toggle" @class(['text-accent!' => $saved])
        aria-pressed="{{ $saved ? 'true' : 'false' }}" aria-label="{{ $saved ? 'Remove from saved' : 'Save post' }}" />
</div>
