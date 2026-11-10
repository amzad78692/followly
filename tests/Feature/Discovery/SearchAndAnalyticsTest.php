<?php

use App\Discovery\GlobalSearch;
use App\Enums\Platform;
use App\Models\AccountSnapshot;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->category = Taxonomy::create(['type' => 'category', 'name' => 'Gaming', 'slug' => 'gaming']);
    $this->influencer = Influencer::factory()->create(['name' => 'Gaming Guru', 'category_id' => $this->category->id]);
    $this->account = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::YouTube, 'handle' => '@guru', 'followers' => 1000]);
});

it('returns influencers, posts and categories as grouped results', function () {
    $post = Post::factory()->for($this->account, 'account')->create(['title' => null, 'body' => 'Best gaming setup of the year']);
    Post::factory()->for($this->account, 'account')->create(['title' => null, 'body' => 'Hidden gaming post', 'hidden_at' => now()]);
    Post::factory()->for($this->account, 'account')->create(['title' => null, 'body' => 'Cooking day']);

    $results = app(GlobalSearch::class)->grouped('gaming', 5);

    expect($results['influencers']->pluck('id')->all())->toBe([$this->influencer->id])
        ->and($results['posts']->pluck('id')->all())->toBe([$post->id])
        ->and($results['categories']->pluck('id')->all())->toBe([$this->category->id]);
});

it('shows posts in the search bar dropdown and on the search page tabs', function () {
    Post::factory()->for($this->account, 'account')->create(['title' => null, 'body' => 'Unboxing the new console']);

    Volt::test('search-bar')->set('q', 'console')->assertSee('Unboxing the new console');

    Volt::test('search')->set('q', 'console')->set('tab', 'posts')->assertSee('Unboxing the new console');
    Volt::test('search')->set('q', 'console')->set('tab', 'influencers')->assertDontSee('Unboxing the new console');
});

it('lists fast-growing creators under Rising This Week on Explore', function () {
    AccountSnapshot::create(['influencer_account_id' => $this->account->id, 'followers' => 1000, 'captured_on' => today()->subDays(5)]);
    AccountSnapshot::create(['influencer_account_id' => $this->account->id, 'followers' => 1500, 'captured_on' => today()]);

    expect(Volt::test('explore')->instance()->sections['Rising This Week']->pluck('id')->all())->toBe([$this->influencer->id]);
});

it('shows the analytics tab for an influencer with no snapshots or posts', function () {
    Volt::test('influencer-analytics', ['influencer' => $this->influencer])
        ->assertOk()
        ->assertSee('No follower history yet')
        ->assertSee('0 posts in 12 weeks')
        ->assertSee('No engagement data for recent posts yet');

    $this->get(route('influencers.show', ['influencer' => $this->influencer, 'tab' => 'analytics']))->assertOk()->assertSee('Analytics');
});

it('shows follower growth, averages and popular posts', function () {
    AccountSnapshot::create(['influencer_account_id' => $this->account->id, 'followers' => 1000, 'captured_on' => today()->subDays(10)]);
    AccountSnapshot::create(['influencer_account_id' => $this->account->id, 'followers' => 1100, 'captured_on' => today()]);
    Post::factory()->for($this->account, 'account')->create(['body' => 'Viral clip', 'metrics' => ['likes' => 300, 'comments' => 10, 'views' => 5000], 'published_at' => now()->subDay()]);
    Post::factory()->for($this->account, 'account')->create(['body' => 'Quiet clip', 'metrics' => ['likes' => 100, 'comments' => 30], 'published_at' => now()->subDays(2)]);

    $component = Volt::test('influencer-analytics', ['influencer' => $this->influencer])
        ->assertSee('+10.0%')
        ->assertSeeInOrder(['Viral clip', 'Quiet clip']);

    expect($component->instance()->averages)->toBe(['likes' => 200, 'comments' => 20, 'views' => 5000]);
});
