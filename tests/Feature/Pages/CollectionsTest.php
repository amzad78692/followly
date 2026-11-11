<?php

use App\Models\Collection;
use App\Models\Influencer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    Volt::test('collections.index')->call('create')->set('name', 'Tech')->set('description', 'Gadget reviews')->call('save')->assertSee('Tech')->assertSee('Gadget reviews');
    $collection = $this->user->collections()->first();

    Volt::test('influencer-actions', ['influencerId' => $influencer->id])->call('toggleCollection', $collection->id);
    expect($collection->influencers()->count())->toBe(1);

    Volt::test('collections.index')->call('edit', $collection->id)->set('name', 'Gadgets')->call('save')->assertSee('Gadgets');
    Volt::test('collections.index')->call('delete', $collection->id);

    expect(Collection::count())->toBe(0);
});

it("cannot change another user's collection", function () {
    $theirs = Collection::create(['user_id' => User::factory()->create()->id, 'name' => 'Theirs']);
    $influencer = Influencer::factory()->create();

    Volt::test('collections.index')->call('edit', $theirs->id)->assertForbidden();
    Volt::test('collections.index')->call('delete', $theirs->id)->assertForbidden();
    Volt::test('collections.index')->call('share', $theirs->id)->assertForbidden();
    Volt::test('collections.index')->call('togglePublic', $theirs->id)->assertForbidden();
    Volt::test('collections.index')->set('editingId', $theirs->id)->set('name', 'Mine now')->call('save')->assertForbidden();
    Volt::test('influencer-actions', ['influencerId' => $influencer->id])->call('toggleCollection', $theirs->id)->assertForbidden();

    expect($theirs->fresh())->name->toBe('Theirs')->share_token->toBeNull()->is_public->toBeFalse()
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

it('uploads a cover image', function () {
    Storage::fake('public');

    Volt::test('collections.index')->call('create')->set('name', 'Covered')
        ->set('cover', UploadedFile::fake()->image('cover.jpg'))->call('save')->assertHasNoErrors();

    expect($this->user->collections()->first()->coverUrl())->not->toBeNull();
});

it('makes a collection public and private again, keeping the link', function () {
    $collection = $this->user->collections()->create(['name' => 'Bikes']);

    Volt::test('collections.index')->call('togglePublic', $collection->id);
    $collection->refresh();
    expect($collection->is_public)->toBeTrue()->and($collection->share_token)->not->toBeNull();

    Volt::test('collections.index')->call('togglePublic', $collection->id);
    expect($collection->fresh()->is_public)->toBeFalse();

    // The link still works while the collection is private.
    $this->get("/c/{$collection->share_token}")->assertOk()->assertSee('Bikes');
});

it('lists only public collections in Explore, by owner name and never email', function () {
    $owner = User::factory()->create(['name' => 'Olivia Owner', 'email' => 'olivia@example.test']);
    $owner->collections()->create(['name' => 'Public Picks', 'is_public' => true, 'share_token' => 'tok1']);
    $owner->collections()->create(['name' => 'Secret Stash', 'share_token' => 'tok2']);

    Volt::test('collections.index')->set('tab', 'explore')
        ->assertSee('Public Picks')->assertSee('Olivia Owner')
        ->assertDontSee('Secret Stash')->assertDontSee('olivia@example.test');

    auth()->logout();
    $this->get('/c/tok1')->assertOk()->assertDontSee('olivia@example.test')->assertDontSee('Olivia Owner');
});

it('renders the share panel with a QR code and share links', function () {
    $collection = $this->user->collections()->create(['name' => 'Shared One']);

    Volt::test('collections.index')->call('share', $collection->id)
        ->assertSet('tab', 'shared')
        ->assertSee('Share Your Collection')
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertSee('https://wa.me/?text=', false)
        ->assertSee('Your Shared Collections');

    $this->get('/collections?tab=shared')->assertOk()->assertSee('Followly Shared Collections');
});

it('follows every influencer in a public collection', function () {
    [$a, $b] = Influencer::factory()->count(2)->create();
    $this->user->follow($a);
    $public = User::factory()->create()->collections()->create(['name' => 'Pub', 'is_public' => true, 'share_token' => 'p']);
    $public->influencers()->attach([$a->id, $b->id]);
    $private = User::factory()->create()->collections()->create(['name' => 'Priv', 'share_token' => 'q']);

    Volt::test('collections.index')->call('followAll', $public->id)->assertSee('Followed 1 new creator');
    expect($this->user->follows()->count())->toBe(2);

    Volt::test('collections.index')->call('followAll', $private->id)->assertNotFound();
});
