<?php

use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a unique slug from the name', function () {
    $first = Influencer::factory()->create(['name' => 'Same Name']);
    $second = Influencer::factory()->create(['name' => 'Same Name']);

    expect($first->slug)->toBe('same-name')
        ->and($second->slug)->toBe('same-name-2');
});

it('keeps combined reach in sync with account followers', function () {
    $influencer = Influencer::factory()->create();
    $account = InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::YouTube, 'followers' => 1_000_000]);
    InfluencerAccount::factory()->for($influencer)->create(['platform' => Platform::X, 'followers' => 500_000]);

    expect($influencer->fresh()->combined_reach)->toBe(1_500_000);

    $account->update(['followers' => 2_000_000]);
    expect($influencer->fresh()->combined_reach)->toBe(2_500_000)
        ->and($influencer->fresh()->formatted_reach)->toBe('2.5M');
});

it('fills the profile url from the platform and handle', function () {
    $account = InfluencerAccount::factory()->create(['platform' => Platform::TikTok, 'handle' => '@someone']);

    expect($account->url)->toBe('https://www.tiktok.com/@someone');
});

it('follows and unfollows an influencer and updates the tracking count', function () {
    $user = User::factory()->create();
    $influencer = Influencer::factory()->withAccounts([Platform::YouTube, Platform::Instagram])->create();

    $follow = $user->follow($influencer);

    expect($user->isFollowing($influencer))->toBeTrue()
        ->and($follow->accounts)->toHaveCount(2)
        ->and($influencer->fresh()->followers_count)->toBe(1);

    $user->follow($influencer); // following twice is a no-op
    expect($user->follows()->count())->toBe(1);

    $user->unfollow($influencer);

    expect($user->isFollowing($influencer))->toBeFalse()
        ->and($influencer->fresh()->followers_count)->toBe(0);
});

it('only shows posts from tracked accounts in the feed', function () {
    $user = User::factory()->create();
    $influencer = Influencer::factory()->withAccounts([Platform::YouTube, Platform::Instagram])->create();
    [$youtube, $instagram] = $influencer->accounts()->orderBy('id')->get();

    $tracked = Post::factory()->for($youtube, 'account')->create(['published_at' => now()->subHour()]);
    Post::factory()->for($instagram, 'account')->create();
    Post::factory()->create(); // someone the user does not follow

    $user->follow($influencer, [$youtube->id]);

    expect(Post::feedFor($user)->pluck('id')->all())->toBe([$tracked->id])
        ->and($tracked->influencer_id)->toBe($influencer->id)
        ->and($tracked->platform)->toBe(Platform::YouTube);
});

it('formats post durations', function () {
    expect(Post::factory()->make(['duration_seconds' => 754])->duration)->toBe('12:34')
        ->and(Post::factory()->make(['duration_seconds' => 8285])->duration)->toBe('2:18:05')
        ->and(Post::factory()->text()->make()->duration)->toBeNull();
});

it('detects the platform from a link', function (string $url, ?Platform $platform) {
    expect(Platform::fromUrl($url))->toBe($platform);
})->with([
    ['https://www.youtube.com/@MrBeast', Platform::YouTube],
    ['instagram.com/mrbeast', Platform::Instagram],
    ['https://www.tiktok.com/@mrbeast', Platform::TikTok],
    ['https://twitter.com/MrBeast', Platform::X],
    ['https://example.com/someone', null],
]);

it('seeds demo data idempotently', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $user = User::where('email', 'test@example.com')->first();

    expect(Influencer::count())->toBe(7)
        ->and(Post::count())->toBe(11)
        ->and($user->follows()->count())->toBe(6)
        ->and(Post::feedFor($user)->count())->toBe(7);
});
