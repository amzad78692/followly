<?php

use App\Models\Collection;
use App\Models\Influencer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('stars followed influencers and blocks an 11th star', function () {
    $influencers = Influencer::factory()->count(11)->create();
    $influencers->each(fn ($i) => $this->user->follow($i));

    $influencers->take(10)->each(fn ($i) => Volt::test('influencer-actions', ['influencerId' => $i->id])->call('toggleWatchlist')->assertSet('error', null));

    Volt::test('influencer-actions', ['influencerId' => $influencers->last()->id])
        ->call('toggleWatchlist')
        ->assertSee('Your Watchlist is full');

    expect($this->user->watchlist()->count())->toBe(10);

    // Unstarring frees a slot.
    Volt::test('influencer-actions', ['influencerId' => $influencers->first()->id])->call('toggleWatchlist');
    expect($this->user->watchlist()->count())->toBe(9);
});

it('requires following before starring', function () {
    $influencer = Influencer::factory()->create();

    Volt::test('influencer-actions', ['influencerId' => $influencer->id])
        ->call('toggleWatchlist')
        ->assertSee('Follow this influencer');

    expect($this->user->watchlist()->count())->toBe(0);
});

it('creates, renames, fills and deletes a collection', function () {
    $influencer = Influencer::factory()->create();

    Volt::test('collections.index')->set('name', 'Tech')->call('create')->assertSee('Tech');
    $collection = $this->user->collections()->first();

    Volt::test('influencer-actions', ['influencerId' => $influencer->id])->call('toggleCollection', $collection->id);
    expect($collection->influencers()->count())->toBe(1);

    Volt::test('collections.index')->call('edit', $collection->id)->set('editingName', 'Gadgets')->call('rename')->assertSee('Gadgets');
    Volt::test('collections.index')->call('delete', $collection->id);

    expect(Collection::count())->toBe(0);
});

it("cannot change another user's collection", function () {
    $theirs = Collection::create(['user_id' => User::factory()->create()->id, 'name' => 'Theirs']);
    $influencer = Influencer::factory()->create();

    Volt::test('collections.index')->call('edit', $theirs->id)->assertForbidden();
    Volt::test('collections.index')->call('delete', $theirs->id)->assertForbidden();
    Volt::test('collections.index')->call('share', $theirs->id)->assertForbidden();
    Volt::test('influencer-actions', ['influencerId' => $influencer->id])->call('toggleCollection', $theirs->id)->assertForbidden();

    expect($theirs->fresh())->name->toBe('Theirs')->share_token->toBeNull()
        ->and($theirs->influencers()->count())->toBe(0);
});

it('shares a collection publicly and stops after unshare', function () {
    $collection = $this->user->collections()->create(['name' => 'Best of Bikes']);
    $collection->influencers()->attach(Influencer::factory()->create(['name' => 'Bike Creator']));

    Volt::test('collections.index')->call('share', $collection->id);
    $token = $collection->fresh()->share_token;
    expect($token)->not->toBeNull();

    auth()->logout();
    $this->get("/c/{$token}")
        ->assertOk()
        ->assertSee('Best of Bikes')
        ->assertSee('Bike Creator')
        ->assertDontSee($this->user->email);

    $this->actingAs($this->user);
    Volt::test('collections.index')->call('unshare', $collection->id);
    auth()->logout();

    $this->get("/c/{$token}")->assertNotFound();
});

it('renders the collections page', function () {
    $this->user->collections()->create(['name' => 'Tech']);

    $this->get('/collections')->assertOk()->assertSee('Tech');
});
