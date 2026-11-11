<?php

use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\User;
use App\Settings\ContactSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->influencer = Influencer::factory()->create(['name' => 'Public Creator', 'slug' => 'public-creator', 'bio' => 'Makes videos about bikes.']);
    InfluencerAccount::factory()->for($this->influencer)->create(['platform' => 'youtube', 'handle' => '@publiccreator']);
    InfluencerAccount::factory()->for($this->influencer)->create(['platform' => 'x', 'handle' => 'publiccreator']);
});

it('shows profiles to guests with share tags and structured data', function () {
    $this->get('/influencers/public-creator')
        ->assertOk()
        ->assertSee('Get Started')
        ->assertDontSee('My Collections')
        ->assertSee('<meta name="description" content="Makes videos about bikes.">', false)
        ->assertSee('<link rel="canonical" href="'.route('influencers.show', $this->influencer).'">', false)
        ->assertSee('<meta property="og:type" content="profile">', false)
        ->assertSee('<meta property="og:image"', false)
        ->assertSee('"@type":"Person"', false)
        ->assertSee('"sameAs":["https://www.youtube.com/@publiccreator","https://x.com/publiccreator"]', false);
});

it('keeps the dashboard layout for signed-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/influencers/public-creator')
        ->assertOk()
        ->assertSee('My Collections');
});

it('describes a profile without a bio from its platforms', function () {
    $this->influencer->update(['bio' => null]);

    $this->get('/influencers/public-creator')
        ->assertSee('Latest posts from Public Creator on YouTube and X, in one feed on '.config('app.name').'.');
});

it('sends guests who follow to sign up and back to the profile', function () {
    Volt::test('influencers.show', ['influencer' => $this->influencer->fresh()])
        ->call('toggle', $this->influencer->id)
        ->assertRedirect(route('register'));

    expect(session('url.intended'))->toBe(route('influencers.show', $this->influencer));

    Volt::test('auth.register')
        ->set('name', 'New Fan')
        ->set('email', 'fan@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('terms', true)
        ->call('register')
        ->assertRedirect(route('influencers.show', $this->influencer));
});

it('gives the landing page a description', function () {
    $this->get('/')->assertOk()->assertSee('<meta name="description"', false)->assertSee('<meta property="og:title"', false);
});

it('shows real creators on the landing page under every tab', function (string $tab) {
    $this->influencer->update(['category_id' => null]);

    Volt::test('home')
        ->call('setTab', $tab)
        ->assertSet('tab', $tab)
        ->assertOk();
})->with(['trending', 'category', 'country', 'language', 'recommended']);

it('shows the top creator of each group once on the landing page', function () {
    $category = Taxonomy::create(['type' => 'category', 'name' => 'Bikes', 'slug' => 'bikes']);
    Influencer::factory()->create(['name' => 'Top Rider', 'category_id' => $category->id, 'followers_count' => 100]);
    Influencer::factory()->create(['name' => 'Second Rider', 'category_id' => $category->id, 'followers_count' => 10]);

    Volt::test('home')
        ->call('setTab', 'category')
        ->assertSee('Top Rider')
        ->assertDontSee('Second Rider');
});

it('falls back to the first tab for unknown landing page tabs', function () {
    Volt::test('home')->call('setTab', 'nope')->assertSet('tab', 'trending');
});

it('fills the add page from the landing page form', function () {
    $this->actingAs(User::factory()->create())
        ->get('/influencers/add?q=@publiccreator')
        ->assertOk()
        ->assertSee('@publiccreator');
});

it('shows the privacy, terms and contact pages', function (string $path, string $text) {
    $this->get($path)->assertOk()->assertSee($text)->assertSee('Get Started');
})->with([
    ['/privacy', 'Privacy Policy'],
    ['/terms', 'Terms of Service'],
    ['/data-deletion', 'Data Deletion Instructions'],
    ['/contact', 'Contact'],
]);

it('shows only the social links set in the contact settings', function () {
    $this->get('/privacy')->assertDontSee('Follow Us');

    $settings = app(ContactSettings::class);
    $settings->youtube = 'https://www.youtube.com/@followly';
    $settings->save();

    $this->get('/privacy')
        ->assertSee('Follow Us')
        ->assertSee('https://www.youtube.com/@followly')
        ->assertDontSee('aria-label="Instagram"', false);
});

it('lists the landing page, static pages and every profile in the sitemap', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<loc>'.route('home').'</loc>', false)
        ->assertSee('<loc>'.route('privacy').'</loc>', false)
        ->assertSee('<loc>'.route('influencers.show', $this->influencer).'</loc>', false);
});

it('points crawlers at the sitemap', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.route('sitemap'));
});

it('builds players for YouTube, TikTok and X but not Instagram', function (string $platform, ?string $url, ?string $shape) {
    $account = InfluencerAccount::factory()->create(['platform' => $platform]);
    $post = Post::factory()->for($account, 'account')->create(['external_id' => '123']);

    expect($post->embed)->toBe($url ? ['url' => $url, 'shape' => $shape] : null);
})->with([
    ['youtube', 'https://www.youtube-nocookie.com/embed/123?autoplay=1&rel=0', 'landscape'],
    ['tiktok', 'https://www.tiktok.com/player/v1/123?autoplay=1&rel=0', 'portrait'],
    ['x', 'https://platform.twitter.com/embed/Tweet.html?id=123&dnt=true', 'tall'],
    ['instagram', null, null],
]);

it('lets post cards open the player', function () {
    $youtube = $this->influencer->accounts->firstWhere('platform.value', 'youtube');
    Post::factory()->for($youtube, 'account')->create(['external_id' => 'abc']);

    $this->get('/influencers/public-creator')
        ->assertSee('Watch here')
        ->assertSee('aria-label="Play video"', false)
        ->assertSee('aria-label="Post player"', false);
});
