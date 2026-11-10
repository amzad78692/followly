<?php

use App\Models\Collection;
use App\Models\Influencer;
use App\Models\KeywordAlert;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lists only watchlisted follows in the watchlist', function () {
    $user = User::factory()->create();
    [$watched, $plain] = Influencer::factory()->count(2)->create();

    $user->follow($watched)->update(['watchlisted_at' => now()]);
    $user->follow($plain);

    expect($user->watchlist()->pluck('influencers.id')->all())->toBe([$watched->id])
        ->and($user->watchlist()->first()->pivot->watchlisted_at)->not->toBeNull();
});

it('links collections, influencers and keyword alerts to the user', function () {
    $user = User::factory()->create();
    $influencer = Influencer::factory()->create();

    $collection = $user->collections()->create(['name' => 'Tech']);
    $collection->influencers()->attach($influencer);
    $user->keywordAlerts()->create(['keyword' => 'launch']);

    expect($collection->user->is($user))->toBeTrue()
        ->and($collection->influencers->first()->is($influencer))->toBeTrue()
        ->and($influencer->collections->first()->is($collection))->toBeTrue()
        ->and($user->keywordAlerts()->first()->keyword)->toBe('launch');
});

it('stores a SmartLink group id on posts', function () {
    $post = Post::factory()->create();
    $post->update(['group_id' => $post->id]);

    expect($post->fresh()->group_id)->toBe($post->id);
});

it('removes the new rows when the account is deleted', function () {
    $user = User::factory()->create();
    $collection = $user->collections()->create(['name' => 'Tech']);
    $collection->influencers()->attach(Influencer::factory()->create());
    $user->keywordAlerts()->create(['keyword' => 'launch']);
    $this->actingAs($user)->get('/');

    $user->deleteAccount();

    expect(Collection::count())->toBe(0)
        ->and(DB::table('collection_influencer')->count())->toBe(0)
        ->and(KeywordAlert::count())->toBe(0)
        ->and(DB::table('user_activity_days')->count())->toBe(0);
});

it('records activity once per user per day', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/');
    DB::enableQueryLog();
    $this->get('/');
    $writes = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'user_activity_days'));

    expect(DB::table('user_activity_days')->where('user_id', $user->id)->count())->toBe(1)
        ->and($writes)->toBeEmpty();

    $this->travel(1)->day();
    $this->get('/');

    expect(DB::table('user_activity_days')->where('user_id', $user->id)->count())->toBe(2);
});

it('records nothing for guests', function () {
    $this->get('/');

    expect(DB::table('user_activity_days')->count())->toBe(0);
});
