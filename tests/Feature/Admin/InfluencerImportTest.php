<?php

use App\Enums\Platform;
use App\Filament\Imports\InfluencerImporter;
use App\Jobs\SyncAccountPosts;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Taxonomy;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->admin = superAdmin();
    $import = new Import;
    $import->user()->associate($this->admin);
    $this->import = InfluencerImporter::test(import: $import);
});

it('creates an influencer from profile links and skips search links', function () {
    $drivers = fakePlatforms(profile(Platform::YouTube, 'chef', ['avatarUrl' => 'https://img/chef.jpg']), profile(Platform::X, 'chef'));
    $category = Taxonomy::create(['type' => 'category', 'name' => 'Entertainment', 'slug' => 'entertainment']);

    $this->import->import([
        'name' => 'Chef Mo',
        'category' => 'Entertainment',
        'language' => 'English',
        'youtube' => 'https://www.youtube.com/@chef',
        'instagram' => 'https://www.instagram.com/explore/search/keyword/?q=Chef',
        'tiktok' => '',
        'x' => 'https://x.com/chef',
    ])->assertImported();

    $influencer = Influencer::sole();

    expect($influencer->name)->toBe('Chef Mo')
        ->and($influencer->category_id)->toBe($category->id)
        ->and($influencer->avatar_url)->toBe('https://img/chef.jpg')
        ->and($influencer->createdBy->is($this->admin))->toBeTrue()
        ->and($influencer->accounts->pluck('platform')->all())->toBe([Platform::YouTube, Platform::X])
        ->and($drivers['instagram']->lookups)->toBe([]);
    Queue::assertPushed(SyncAccountPosts::class);
});

it('fails rows without a profile link', function () {
    fakePlatforms();

    $this->import->import([
        'name' => 'Stokes Twins',
        'youtube' => 'https://www.youtube.com/results?search_query=Stokes%20Twins',
        'x' => 'https://x.com/search?q=Stokes%20Twins',
    ])->assertHasRowFailure();

    expect(Influencer::count())->toBe(0);
});

it('adds new accounts to an influencer already stored', function () {
    fakePlatforms(profile(Platform::YouTube, 'chef'), profile(Platform::TikTok, 'chef'));
    $influencer = Influencer::factory()->create(['name' => 'Chef Mo']);
    InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'handle' => '@chef', 'external_id' => 'youtube-chef']);

    $this->import->import([
        'name' => 'Chef Mo',
        'youtube' => 'https://www.youtube.com/@chef',
        'tiktok' => 'https://www.tiktok.com/@chef',
    ])->assertImported();

    expect(Influencer::count())->toBe(1)
        ->and($influencer->accounts()->pluck('platform')->all())->toBe([Platform::YouTube, Platform::TikTok]);
});
