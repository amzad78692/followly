<?php

use App\Discovery\TrendingRanker;
use App\Enums\Platform;
use App\Models\AccountSnapshot;
use App\Models\Country;
use App\Models\Follow;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function discoverable(string $name, array $attributes = [], string $handle = '@creator'): Influencer
{
    $influencer = Influencer::factory()->create(['name' => $name] + $attributes);

    InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'handle' => $handle, 'followers' => 1000]);

    return $influencer->fresh();
}

function kuwait(): Country
{
    return Country::create(['iso2' => 'KW', 'iso3' => 'KWT', 'name' => 'Kuwait', 'phone_code' => '965', 'region' => 'Asia', 'subregion' => 'Western Asia']);
}

/**
 * Store follower counts per day ago for the influencer's first account, e.g. [6 => 1000, 0 => 1100].
 */
function snapshots(Influencer $influencer, array $followersByDaysAgo): void
{
    foreach ($followersByDaysAgo as $daysAgo => $followers) {
        AccountSnapshot::create([
            'influencer_account_id' => $influencer->accounts->first()->id,
            'followers' => $followers,
            'captured_on' => today()->subDays($daysAgo),
        ]);
    }
}

it('scores new Followly followers in the last 7 days', function () {
    $influencer = discoverable('Popular');

    User::factory()->count(3)->create()->each->follow($influencer);
    $this->user->follow($influencer);
    Follow::where('user_id', $this->user->id)->update(['created_at' => now()->subDays(10)]);

    expect(app(TrendingRanker::class)->scores()->all())->toBe([$influencer->id => 3.0]);
});

it('scores platform follower growth from snapshots, capped either way', function () {
    $growing = discoverable('Growing', handle: '@growing');
    $shrinking = discoverable('Shrinking', handle: '@shrinking');
    $exploding = discoverable('Exploding', handle: '@exploding');
    $flat = discoverable('Flat', handle: '@flat');
    $stale = discoverable('Stale', handle: '@stale');

    snapshots($growing, [6 => 1000, 3 => 1020, 0 => 1100]);  // +10%
    snapshots($shrinking, [5 => 1000, 0 => 950]);            // -5%
    snapshots($exploding, [6 => 100, 0 => 1000]);            // +900%, capped at 50
    snapshots($flat, [6 => 1000, 0 => 1000]);
    snapshots($stale, [20 => 500, 0 => 1000]);               // only one snapshot in the window

    expect(app(TrendingRanker::class)->scores()->all())->toEqual([
        $growing->id => 20.0,
        $shrinking->id => -10.0,
        $exploding->id => 100.0,
    ]);
});

it('adds growth across all of an influencer\'s accounts', function () {
    $influencer = discoverable('Two Accounts');
    $second = InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::X, 'handle' => '@two']);

    snapshots($influencer, [6 => 1000, 0 => 1000]);
    AccountSnapshot::create(['influencer_account_id' => $second->id, 'followers' => 1000, 'captured_on' => today()->subDays(6)]);
    AccountSnapshot::create(['influencer_account_id' => $second->id, 'followers' => 1200, 'captured_on' => today()]);

    // (2200 - 2000) / 2000 = 10%
    expect(app(TrendingRanker::class)->scores()->all())->toEqual([$influencer->id => 20.0]);
});

it('stores scores and resets influencers that stopped trending', function () {
    $old = discoverable('Old News', ['trending_score' => 42], '@old');
    $new = discoverable('New Hit', handle: '@new');
    User::factory()->create()->follow($new);

    expect(app(TrendingRanker::class)->rank())->toBe(1)
        ->and($old->fresh()->trending_score)->toBe(0.0)
        ->and($new->fresh()->trending_score)->toBe(1.0);
});

it('recounts cached follower counts and ranks from the command', function () {
    $influencer = discoverable('Drifted');
    $this->user->follow($influencer);
    Influencer::whereKey($influencer->id)->update(['followers_count' => 99]);

    $this->artisan('influencers:rank')->expectsOutput('1 influencers are trending.')->assertSuccessful();

    expect($influencer->fresh())
        ->followers_count->toBe(1)
        ->trending_score->toBe(1.0);
});

it('orders explore trending by the trending score', function () {
    discoverable('Big But Quiet', ['followers_count' => 500, 'trending_score' => 0], '@quiet');
    discoverable('Rising Star', ['trending_score' => 30], '@rising');

    Volt::test('explore')->assertSeeInOrder(['Rising Star', 'Big But Quiet']);
});

it('recommends from the user\'s top categories, most tracked first', function () {
    config(['discovery.recommended_categories' => 1]);
    $tech = Taxonomy::create(['type' => 'category', 'name' => 'Technology', 'slug' => 'technology']);
    $sports = Taxonomy::create(['type' => 'category', 'name' => 'Sports', 'slug' => 'sports']);

    $this->user->follow(discoverable('Tech One', ['category_id' => $tech->id], '@t1'));
    $this->user->follow(discoverable('Tech Two', ['category_id' => $tech->id], '@t2'));
    $this->user->follow(discoverable('Sports One', ['category_id' => $sports->id], '@s1'));
    discoverable('Small Tech', ['category_id' => $tech->id, 'followers_count' => 2], '@t3');
    discoverable('Big Tech', ['category_id' => $tech->id, 'followers_count' => 50], '@t4');
    discoverable('Other Sports', ['category_id' => $sports->id, 'followers_count' => 90], '@s2');

    expect(Influencer::recommendedFor($this->user)->pluck('name')->all())->toBe(['Big Tech', 'Small Tech']);
});

it('recommends the most tracked creators to users who follow no one', function () {
    discoverable('Small', ['followers_count' => 1], '@small');
    discoverable('Big', ['followers_count' => 10], '@big');

    expect(Influencer::recommendedFor($this->user)->pluck('name')->all())->toBe(['Big', 'Small']);
});

it('filters explore by country', function () {
    $kuwait = kuwait();
    discoverable('Kuwaiti Creator', ['country_id' => $kuwait->id], '@kw');
    discoverable('Elsewhere', handle: '@elsewhere');

    Volt::test('explore')
        ->set('tab', 'country')
        ->set('country', 'KW')
        ->assertSee('Kuwaiti Creator')
        ->assertDontSee('Elsewhere');
});

it('shows influencers, categories and countries in the top bar dropdown', function () {
    $tech = Taxonomy::create(['type' => 'category', 'name' => 'Technology', 'slug' => 'technology']);
    $kuwait = kuwait();
    Taxonomy::create(['type' => 'category', 'name' => 'Techno Music', 'slug' => 'techno-music']); // no creators yet
    discoverable('Tech Reviewer', ['category_id' => $tech->id, 'country_id' => $kuwait->id], '@reviewer');
    discoverable('Chef', handle: '@chef');

    Volt::test('search-bar')
        ->set('q', 't')
        ->assertDontSee('Tech Reviewer')
        ->set('q', 'tech')
        ->assertSee('Tech Reviewer')
        ->assertSee('Technology')
        ->assertDontSee('Techno Music')
        ->assertDontSee('Chef')
        ->assertSee('See all results for')
        ->set('q', 'kuwait')
        ->assertSee('Kuwait')
        ->assertSee('Tech Reviewer') // creators in a matching country or category are listed too
        ->assertDontSee('Chef')
        ->set('q', 'zzz')
        ->assertSee('No results for');
});

it('finds influencers by handle and goes to the search page on submit', function () {
    discoverable('Marques', handle: '@mkbhd');

    Volt::test('search-bar')
        ->set('q', '@mkbhd')
        ->assertSee('Marques')
        ->call('submit')
        ->assertRedirect(route('search', ['q' => '@mkbhd']));
});

it('lists results on the search page and follows from it', function () {
    $influencer = discoverable('Searchable Person', handle: '@searchable');

    $this->get('/search?q=searchable')->assertOk()->assertSee('Results for')->assertSee('Searchable Person');

    Volt::test('search')
        ->set('q', 'searchable')
        ->assertSee('Searchable Person')
        ->call('toggle', $influencer->id)
        ->assertSee('Following');

    expect($this->user->isFollowing($influencer))->toBeTrue();
});

it('shows empty states on the search page', function () {
    $this->get('/search')->assertOk()->assertSee('Type at least');
    $this->get('/search?q=nobody')->assertOk()->assertSee('Nothing matches');
});

it('keeps the search page behind login', function () {
    auth()->logout();

    $this->get('/search?q=test')->assertRedirect('/login');
});
