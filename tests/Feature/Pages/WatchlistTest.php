<?php

use App\Models\Influencer;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->star = function (string $name) {
        $influencer = Influencer::factory()->create(['name' => $name]);
        $this->user->follow($influencer)->update(['watchlisted_at' => now()]);

        return $influencer;
    };
});

it('shows starred influencers with their latest posts', function () {
    $influencer = ($this->star)('Starry Creator');
    Post::factory()->create(['influencer_id' => $influencer->id, 'title' => 'Fresh upload']);
    $this->user->follow(Influencer::factory()->create(['name' => 'Not Starred']));

    $this->get('/watchlist')->assertOk()->assertSee('Starry Creator')->assertSee('Fresh upload')->assertDontSee('Not Starred');
});

it('unstars and reorders', function () {
    $this->travelTo(now()->subMinute());
    $first = ($this->star)('First');
    $this->travelBack();
    $second = ($this->star)('Second');

    Volt::test('watchlist')->call('move', $second->id, -1);
    expect($this->user->watchlist()->orderByPivot('watchlisted_at')->pluck('name')->all())->toBe(['Second', 'First']);

    Volt::test('watchlist')->call('move', $second->id, -1); // already first: no change
    expect($this->user->watchlist()->orderByPivot('watchlisted_at')->pluck('name')->all())->toBe(['Second', 'First']);

    Volt::test('watchlist')->call('unstar', $first->id);
    expect($this->user->watchlist()->pluck('name')->all())->toBe(['Second']);
});
