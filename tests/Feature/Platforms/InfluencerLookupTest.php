<?php

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\User;
use App\Platforms\AccountFinder;
use App\Platforms\Data\AccountData;
use App\Platforms\Exceptions\PlatformException;
use App\Support\InfluencerImporter;
use App\Support\InfluencerLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('finds other accounts through profile links, then by the same handle', function () {
    fakePlatforms(
        $youtube = profile(Platform::YouTube, 'chef', ['links' => ['https://www.instagram.com/chefcooks']]),
        profile(Platform::Instagram, 'chefcooks', ['links' => ['https://x.com/chef_x']]),
        profile(Platform::X, 'chef_x'),
        profile(Platform::TikTok, 'chef'),
    );

    $found = app(AccountFinder::class)->discover($youtube);

    expect($found->keys()->sort()->values()->all())->toBe(['instagram', 'tiktok', 'x'])
        ->and($found['instagram']->source)->toBe('link')
        ->and($found['x']->source)->toBe('link')
        ->and($found['tiktok']->source)->toBe('handle');
});

it('skips same-handle guesses when turned off and survives a failing platform', function () {
    config(['platforms.discovery.same_handle' => false]);
    $drivers = fakePlatforms($youtube = profile(Platform::YouTube, 'chef', ['links' => ['https://www.tiktok.com/@chef']]), profile(Platform::X, 'chef'));
    $drivers['tiktok']->fails = true;

    expect(app(AccountFinder::class)->discover($youtube))->toBeEmpty()
        ->and($drivers['x']->lookups)->toBe([]);
});

it('looks up a new creator through the drivers', function () {
    fakePlatforms(
        profile(Platform::YouTube, 'chef', ['links' => ['https://www.instagram.com/chef']]),
        profile(Platform::Instagram, 'chef'),
    );

    $result = app(InfluencerLookup::class)->lookup('https://www.youtube.com/@chef');

    expect($result->influencer)->toBeNull()
        ->and($result->candidates->keys()->all())->toBe(['youtube', 'instagram'])
        ->and($result->candidates['youtube']->source)->toBe('search');
});

it('matches a stored account by platform id even when its handle changed', function () {
    $influencer = Influencer::factory()->create(['name' => 'Chef']);
    InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'handle' => '@oldname', 'external_id' => 'youtube-chef']);

    fakePlatforms(
        profile(Platform::YouTube, 'chef', ['links' => ['https://www.instagram.com/chef']]),
        profile(Platform::Instagram, 'chef'),
    );

    $result = app(InfluencerLookup::class)->lookup('@chef');

    expect($result->influencer->is($influencer))->toBeTrue()
        ->and($result->candidates->keys()->all())->toBe(['instagram']);
});

it('does not call the drivers for creators already stored', function () {
    $influencer = Influencer::factory()->create();
    InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'handle' => '@stored']);
    $drivers = fakePlatforms();

    expect(app(InfluencerLookup::class)->lookup('youtube.com/@stored')->influencer->is($influencer))->toBeTrue()
        ->and(collect($drivers)->flatMap->lookups)->toBeEmpty();
});

it('adds a new creator from the Add page and follows the chosen accounts', function () {
    fakePlatforms(
        profile(Platform::YouTube, 'chef', ['name' => 'Chef Mo', 'bio' => 'Cooking every day', 'avatarUrl' => 'https://img/chef.jpg', 'links' => ['https://www.instagram.com/chef']]),
        profile(Platform::Instagram, 'chef', ['isVerified' => true, 'followers' => 5000]),
        profile(Platform::TikTok, 'chef'),
    );

    $component = Volt::test('influencers.create')
        ->set('query', 'https://www.youtube.com/@chef')
        ->call('search')
        ->assertSee('Chef Mo')
        ->assertSee('New on')
        ->assertSee('Linked from their profile')
        ->assertSee('Same username')
        // The same-handle TikTok guess is left unticked.
        ->assertSet('selected', ['new:youtube', 'new:instagram'])
        ->call('track');

    $influencer = Influencer::sole();

    $component->assertRedirect(route('influencers.show', $influencer));

    expect($influencer->name)->toBe('Chef Mo')
        ->and($influencer->bio)->toBe('Cooking every day')
        ->and($influencer->avatar_url)->toBe('https://img/chef.jpg')
        ->and($influencer->is_verified)->toBeTrue()
        ->and($influencer->createdBy->is($this->user))->toBeTrue()
        ->and($influencer->combined_reach)->toBe(6000)
        ->and($influencer->accounts->pluck('handle', 'platform.value')->all())->toBe(['youtube' => '@chef', 'instagram' => '@chef'])
        // The first sync ran straight away (the test queue is synchronous).
        ->and($influencer->accounts->first()->fresh()->sync_status)->toBe(SyncStatus::Synced)
        ->and($this->user->follows()->sole()->accounts)->toHaveCount(2);
});

it('attaches newly found accounts to a creator that is already stored', function () {
    $influencer = Influencer::factory()->create(['name' => 'Chef']);
    $youtube = InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'handle' => '@oldname', 'external_id' => 'youtube-chef']);

    fakePlatforms(
        profile(Platform::YouTube, 'chef', ['links' => ['https://www.instagram.com/chef']]),
        profile(Platform::Instagram, 'chef'),
    );

    Volt::test('influencers.create')
        ->set('query', '@chef')
        ->call('search')
        ->assertSet('selected', [(string) $youtube->id, 'new:instagram'])
        ->call('track')
        ->assertRedirect(route('influencers.show', $influencer));

    expect(Influencer::count())->toBe(1)
        ->and($influencer->accounts()->count())->toBe(2)
        ->and($this->user->follows()->sole()->accounts)->toHaveCount(2);
});

it('shows an error when a platform cannot be reached', function () {
    $drivers = fakePlatforms();
    $drivers['instagram']->fails = true;

    Volt::test('influencers.create')
        ->set('query', 'https://www.instagram.com/chef')
        ->call('search')
        ->assertHasErrors('query')
        ->assertSee("We couldn't reach Instagram right now");
});

it('does not take accounts another creator owns', function () {
    Queue::fake();
    $owner = Influencer::factory()->create();
    InfluencerAccount::factory()->for($owner)->create(['platform' => Platform::Instagram, 'handle' => '@chef', 'external_id' => 'instagram-chef']);

    $influencer = app(InfluencerImporter::class)->import(collect([
        profile(Platform::YouTube, 'chef'),
        profile(Platform::Instagram, 'chef'),
    ]));

    expect($influencer->accounts->pluck('platform')->all())->toBe([Platform::YouTube])
        ->and($owner->accounts()->count())->toBe(1);
});

it('stores profiles and sync errors on accounts', function () {
    $account = InfluencerAccount::factory()->create(['platform' => Platform::YouTube, 'handle' => '@old', 'followers' => 10]);

    $account->markSyncFailed(new PlatformException(Platform::YouTube, 'HTTP 500: Backend Error'));

    expect($account->fresh())->sync_status->toBe(SyncStatus::Failed)->sync_error->toBe('YouTube: HTTP 500: Backend Error');

    $account->applyProfile(profile(Platform::YouTube, 'new', ['followers' => 99, 'externalId' => $account->external_id]));

    expect($account->fresh())
        ->handle->toBe('@new')
        ->followers->toBe(99)
        ->sync_status->toBe(SyncStatus::Synced)
        ->sync_error->toBeNull()
        ->and($account->influencer->fresh()->combined_reach)->toBe(99);
});

it('round-trips account data through Livewire state', function () {
    $data = profile(Platform::TikTok, 'chef', ['links' => ['https://a.b'], 'source' => 'handle']);

    expect(AccountData::fromArray($data->toArray()))->toEqual($data);
});
