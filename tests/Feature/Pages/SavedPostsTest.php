<?php

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

    $account = InfluencerAccount::factory()->for(Influencer::factory())->create(['platform' => 'youtube']);
    $this->user->follow($account->influencer);
    $this->post = Post::factory()->for($account, 'account')->create(['title' => 'Keep this one']);
    $this->other = Post::factory()->for($account, 'account')->create(['title' => 'Someone else saved']);
});

it('bookmarks a post on and off', function () {
    $toggle = Volt::test('save-post', ['post' => $this->post->id])
        ->call('toggle')
        ->assertSet('saved', true)
        ->assertDispatched('post-saved');

    expect($this->user->savedPosts()->pluck('posts.id')->all())->toBe([$this->post->id]);

    $toggle->call('toggle')->assertSet('saved', false);

    expect($this->user->savedPosts()->count())->toBe(0);
});

it('does not save a hidden post', function () {
    $this->post->update(['hidden_at' => now()]);

    Volt::test('save-post', ['post' => $this->post->id])->call('toggle')->assertSet('saved', false);

    expect($this->user->savedPosts()->count())->toBe(0);
});

it('shows only your own saved posts on the Saved tab', function () {
    $this->user->savedPosts()->attach($this->post);
    User::factory()->create()->savedPosts()->attach($this->other);

    Volt::test('dashboard')
        ->assertSee('Someone else saved')
        ->call('scope', 'saved')
        ->assertSee('Keep this one')
        ->assertDontSee('Someone else saved');
});

it('sorts the feed latest first', function () {
    $this->post->update(['published_at' => now()->subDay()]);
    $this->other->update(['published_at' => now()]);

    expect(Volt::test('dashboard')->set('sort', 'latest')->get('posts')->pluck('id')->all())
        ->toBe([$this->other->id, $this->post->id]);
});

it('shows the SmartLink bar for a post on several platforms', function () {
    $x = InfluencerAccount::factory()->for($this->post->influencer)->create(['platform' => 'x']);
    $this->user->follow($this->post->influencer->fresh());
    Post::factory()->text()->for($x, 'account')->create(['group_id' => $this->post->id, 'permalink' => 'https://x.com/a/status/1']);
    $this->post->update(['group_id' => $this->post->id, 'permalink' => 'https://youtube.com/watch?v=1']);

    Volt::test('dashboard')->assertSee('SmartLink')->assertSee('Posted on 2 platforms');
});
