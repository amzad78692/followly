<?php

namespace App\Livewire\Concerns;

use App\Models\Influencer;
use Livewire\Attributes\Computed;

/**
 * Follow/unfollow buttons for pages that list influencer cards.
 */
trait TogglesFollow
{
    /**
     * Ids of the influencers the current user follows (none for guests).
     *
     * @return array<int, int>
     */
    #[Computed]
    public function followedIds(): array
    {
        return auth()->user()?->follows()->pluck('influencer_id')->all() ?? [];
    }

    public function toggle(int $influencerId): void
    {
        $influencer = Influencer::findOrFail($influencerId);
        $user = auth()->user();

        // Guests on a public profile sign up first, then come back to it.
        if (! $user) {
            session()->put('url.intended', route('influencers.show', $influencer));
            $this->redirectRoute('register', navigate: true);

            return;
        }

        $user->isFollowing($influencer) ? $user->unfollow($influencer) : $user->follow($influencer);

        unset($this->followedIds);
        $this->followToggled();
    }

    /**
     * Clear any computed lists that show follow state or tracking counts.
     */
    protected function followToggled(): void
    {
        //
    }
}
