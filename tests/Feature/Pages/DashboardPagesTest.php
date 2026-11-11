<?php

use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\User;
use App\Support\InfluencerLookup;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function creator(string $name, array $accounts = ['youtube' => '@creator'], array $attributes = []): Influencer
{
    $influencer = Influencer::factory()->create(['name' => $name] + $attributes);

    foreach ($accounts as $platform => $handle) {
        InfluencerAccount::factory()->for($influencer)->create(['platform' => $platform, 'handle' => $handle]);
    }

    return $influencer->fresh();
}

it('renders every dashboard page with the demo data', function (string $uri) {
    $this->seed(DemoSeeder::class);

    $this->get($uri)->assertOk();
})->with(['/dashboard', '/explore', '/explore?tab=category', '/explore?tab=country', '/explore?tab=language', '/explore?tab=recommended', '/influencers', '/influencers/add', '/influencers/mrbeast', '/influencers/mrbeast?tab=about', '/influencers/mrbeast?tab=related']);

it('returns 404 for an unknown profile', function () {
    $this->get('/influencers/nobody-here')->assertNotFound();
});

it('shows only posts from tracked accounts in the feed and filters by platform', function () {
    $followed = creator('Followed', ['youtube' => '@followed', 'x' => '@followed']);
    $other = creator('Other', ['youtube' => '@other']);

    $this->user->follow($followed);

    Post::factory()->for($followed->accounts->firstWhere('platform', Platform::YouTube), 'account')->create(['title' => 'Followed video']);
    Post::factory()->text()->for($followed->accounts->firstWhere('platform', Platform::X), 'account')->create(['body' => 'Followed tweet']);
    Post::factory()->for($other->accounts->first(), 'account')->create(['title' => 'Other video']);

    Volt::test('dashboard')
        ->assertSee('Followed video')
        ->assertSee('Followed tweet')
        ->assertDontSee('Other video')
        ->set('platform', 'x')
        ->assertDontSee('Followed video')
        ->assertSee('Followed tweet');
});

it('loads more feed posts', function () {
    $influencer = creator('Busy', ['youtube' => '@busy']);
    $this->user->follow($influencer);

    Post::factory()->count(20)->for($influencer->accounts->first(), 'account')->create();

    $component = Volt::test('dashboard');
    expect($component->get('posts'))->toHaveCount(15)
        ->and($component->get('hasMore'))->toBeTrue();

    $component->call('loadMore');
    expect($component->get('posts'))->toHaveCount(20)
        ->and($component->get('hasMore'))->toBeFalse();
});

it('follows and unfollows from explore', function () {
    $influencer = creator('Explorer');

    Volt::test('explore')
        ->assertSee('Explorer')
        ->call('toggle', $influencer->id);

    expect($this->user->isFollowing($influencer))->toBeTrue()
        ->and($influencer->fresh()->followers_count)->toBe(1);

    Volt::test('explore')->call('toggle', $influencer->id);

    expect($this->user->isFollowing($influencer))->toBeFalse();
});

it('filters explore by category', function () {
    $tech = Taxonomy::create(['type' => 'category', 'name' => 'Technology', 'slug' => 'technology']);
    creator('Tech Person', attributes: ['category_id' => $tech->id]);
    creator('Sports Person');

    Volt::test('explore')
        ->call('browse', 'technology')
        ->assertSee('Tech Person')
        ->assertDontSee('Sports Person');
});

it('recommends unfollowed creators in the user\'s categories', function () {
    $tech = Taxonomy::create(['type' => 'category', 'name' => 'Technology', 'slug' => 'technology']);
    $sports = Taxonomy::create(['type' => 'category', 'name' => 'Sports', 'slug' => 'sports']);

    $this->user->follow(creator('Followed Tech', attributes: ['category_id' => $tech->id]));
    creator('Other Tech', attributes: ['category_id' => $tech->id]);
    creator('Sports Star', attributes: ['category_id' => $sports->id]);

    Volt::test('explore')
        ->call('setTab', 'recommended')
        ->assertSee('Other Tech')
        ->assertDontSee('Sports Star')
        ->assertDontSee('Followed Tech');
});

it('lists, searches and unfollows my influencers', function () {
    $alpha = creator('Alpha Creator', ['youtube' => '@alpha']);
    $beta = creator('Beta Creator', ['tiktok' => '@beta']);
    creator('Not Followed');

    $this->user->follow($alpha);
    $this->user->follow($beta);

    $component = Volt::test('influencers.index')
        ->assertSee('Alpha Creator')
        ->assertSee('Beta Creator')
        ->assertDontSee('Not Followed')
        ->set('platform', 'tiktok')
        ->assertDontSee('Alpha Creator')
        ->assertSee('Beta Creator')
        ->set('platform', '')
        ->set('search', 'alpha')
        ->assertSee('Alpha Creator')
        ->assertDontSee('Beta Creator');

    $component->call('confirmUnfollow', $alpha->id)
        ->assertSet('unfollowing', $alpha->id)
        ->call('unfollow')
        ->assertSet('unfollowing', null);

    expect($this->user->isFollowing($alpha))->toBeFalse()
        ->and($this->user->isFollowing($beta))->toBeTrue();
});

it('shows a profile with its posts and follows from it', function () {
    $influencer = creator('Profile Person', ['youtube' => '@profile']);
    Post::factory()->for($influencer->accounts->first(), 'account')->create(['title' => 'Profile video']);

    Volt::test('influencers.show', ['influencer' => $influencer])
        ->assertSee('Profile Person')
        ->assertSee('Profile video')
        ->call('toggle', $influencer->id)
        ->assertSee('Following');

    expect($this->user->isFollowing($influencer))->toBeTrue();
});

it('finds existing influencers from links, handles and names', function (string $query) {
    $influencer = creator('Marques Brownlee', ['youtube' => '@mkbhd', 'x' => '@MKBHD']);
    $influencer->accounts->firstWhere('platform', Platform::YouTube)->update(['external_id' => 'UCBJycsmduvYEL83R_U4JriQ']);

    expect(app(InfluencerLookup::class)->find($query)?->is($influencer))->toBeTrue();
})->with([
    'https://www.youtube.com/@mkbhd',
    'youtube.com/@MKBHD',
    'https://www.youtube.com/channel/UCBJycsmduvYEL83R_U4JriQ',
    'https://x.com/mkbhd?lang=en',
    'https://twitter.com/MKBHD',
    '@mkbhd',
    'mkbhd',
    'Marques Brownlee',
]);

it('does not match unknown links or handles', function (string $query) {
    creator('Marques Brownlee', ['youtube' => '@mkbhd']);

    expect(app(InfluencerLookup::class)->find($query))->toBeNull();
})->with([
    'https://www.instagram.com/mkbhd',
    'https://example.com/mkbhd',
    '@someoneelse',
    '',
]);

it('adds an influencer with the selected accounts', function () {
    $influencer = creator('Track Me', ['youtube' => '@trackme', 'instagram' => '@trackme']);
    $instagram = $influencer->accounts->firstWhere('platform', Platform::Instagram);

    $component = Volt::test('influencers.create')
        ->set('query', 'https://www.youtube.com/@trackme')
        ->call('search')
        ->assertSee('Track Me')
        ->assertCount('selected', 2)
        ->set('selected', [(string) $instagram->id])
        ->call('track')
        ->assertRedirect(route('influencers.show', $influencer));

    $follow = $this->user->follows()->where('influencer_id', $influencer->id)->first();

    expect($follow->accounts->pluck('id')->all())->toBe([$instagram->id]);
});

it('shows an empty state when no creator matches', function () {
    Volt::test('influencers.create')
        ->set('query', '@nobody')
        ->call('search')
        ->assertSee('No accounts found');
});
