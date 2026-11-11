<?php

use App\Enums\Platform;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->influencer = Influencer::factory()->create(['name' => 'Timeline Star']);
    $this->youtube = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::YouTube]);
    $this->x = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::X]);
});

it('shows an empty state when the user follows no one', function () {
    $this->get(route('timeline'))->assertOk()->assertSee('Follow an influencer to see their timeline');
});

it('lists only followed influencers in the picker and opens the first one', function () {
    $this->user->follow($this->influencer);
    Influencer::factory()->create(['name' => 'Stranger Danger']);

    $this->get(route('timeline'))->assertOk()
        ->assertSee('Timeline Star')
        ->assertDontSee('Stranger Danger');
});

it('remembers the last influencer viewed', function () {
    $other = Influencer::factory()->create(['name' => 'Another Creator']);
    $this->user->follow($this->influencer);
    $this->user->follow($other);

    $this->get(route('timeline', ['influencer' => $other->id]))->assertOk();

    Volt::test('timeline')->assertSet('influencerId', $other->id);
});

it('filters the timeline by platform', function () {
    $this->user->follow($this->influencer);
    Post::factory()->for($this->youtube, 'account')->create(['title' => 'A YouTube video']);
    Post::factory()->for($this->x, 'account')->create(['title' => 'An X post']);

    Volt::test('influencer-timeline', ['influencer' => $this->influencer, 'platform' => 'x'])
        ->assertSee('An X post')->assertDontSee('A YouTube video');

    Volt::test('influencer-timeline', ['influencer' => $this->influencer])
        ->assertSee('An X post')->assertSee('A YouTube video');

    Volt::test('timeline', ['influencerId' => $this->influencer->id])
        ->assertSee('An X post')
        ->set('platform', 'youtube')
        ->assertSee('A YouTube video')->assertDontSee('An X post');
});

it('groups posts under Today, Yesterday and dated labels', function () {
    $this->travelTo(now()->setTime(18, 0));
    Post::factory()->for($this->youtube, 'account')->create(['title' => 'Fresh', 'published_at' => now()->subHour()]);
    Post::factory()->for($this->youtube, 'account')->create(['title' => 'Day old', 'published_at' => now()->subDay()]);
    Post::factory()->for($this->youtube, 'account')->create(['title' => 'Old one', 'published_at' => now()->subDays(5)]);

    Volt::test('influencer-timeline', ['influencer' => $this->influencer])
        ->assertSeeInOrder([
            'Today, '.now()->format('M j, Y'), 'Fresh',
            'Yesterday, '.now()->subDay()->format('M j, Y'), 'Day old',
            now()->subDays(5)->format('M j, Y'), 'Old one',
        ]);
});

it('shows a SmartLink group once with platform stats', function () {
    $first = Post::factory()->for($this->youtube, 'account')->create(['title' => 'Same story', 'published_at' => now()->subHour(), 'metrics' => ['views' => 1500, 'likes' => 20]]);
    Post::factory()->for($this->x, 'account')->create(['title' => 'Same story', 'group_id' => $first->id, 'published_at' => now()->subHours(2)]);
    $first->update(['group_id' => $first->id]);

    $component = Volt::test('influencer-timeline', ['influencer' => $this->influencer])
        ->assertSee('Also posted on')->assertSee('1.5K')->assertSee('views')->assertDontSee('shares');

    expect(substr_count($component->html(), 'Same story'))->toBe(1);
});

it('turns post alerts for a followed influencer on and off', function () {
    $follow = $this->user->follow($this->influencer)->refresh();

    Volt::test('timeline', ['influencerId' => $this->influencer->id])->call('toggleAlerts');

    expect($follow->fresh()->notify)->toBe(! $follow->notify);
});
