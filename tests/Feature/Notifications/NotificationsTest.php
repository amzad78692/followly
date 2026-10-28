<?php

use App\Enums\DigestFrequency;
use App\Enums\Platform;
use App\Events\PostsSynced;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostsDigest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->influencer = Influencer::factory()->create(['name' => 'Poster']);
    $this->youtube = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::YouTube, 'handle' => '@poster']);
    $this->x = InfluencerAccount::factory()->for($this->influencer)->create(['platform' => Platform::X, 'handle' => '@poster']);
});

/**
 * Store posts on the account and fire the sync event for them.
 */
function syncPosts(InfluencerAccount $account, array $hoursAgo): Collection
{
    $posts = new Collection(array_map(fn ($hours) => Post::factory()->for($account, 'account')->create([
        'title' => "Post from {$hours}h ago",
        'published_at' => now()->subHours($hours),
    ]), $hoursAgo));

    PostsSynced::dispatch($account, $posts);

    return $posts;
}

/**
 * Give the user a stored new post notification.
 */
function notifyUser(User $user, InfluencerAccount $account, string $title = 'A new video', ?DateTimeInterface $at = null): void
{
    test()->travelTo($at ?? now());

    $user->notifyNow(new NewPostNotification($account, new Collection([
        Post::factory()->for($account, 'account')->create(['title' => $title]),
    ])));

    test()->travelBack();
}

it('notifies users who track the account, one notification per sync', function () {
    $this->travel(-1)->hours();
    $this->user->follow($this->influencer);
    $other = User::factory()->create();
    $other->follow($this->influencer, [$this->x->id]); // doesn't track YouTube
    User::factory()->create(); // doesn't follow
    $this->travelBack();

    syncPosts($this->youtube, [0, 0.5]);

    expect($this->user->notifications)->toHaveCount(1)
        ->and($other->notifications)->toHaveCount(0)
        ->and(DatabaseNotification::count())->toBe(1);

    expect($this->user->notifications->first())
        ->type->toBe('new-post')
        ->data->toMatchArray([
            'influencer_name' => 'Poster',
            'influencer_slug' => $this->influencer->slug,
            'platform' => 'youtube',
            'count' => 2,
            'title' => 'Post from 0h ago',
        ]);
});

it('skips old posts and posts from before the user followed', function () {
    $this->travel(-10)->hours();
    $this->user->follow($this->influencer);
    $this->travelBack();

    syncPosts($this->youtube, [72, 20]);  // 72h is older than max_post_age_hours, 20h is before the follow
    expect($this->user->notifications)->toHaveCount(0);

    syncPosts($this->youtube, [2, 72]);
    expect($this->user->fresh()->notifications->first()->data['count'])->toBe(1);
});

it('lists notifications, opens one and marks them read', function () {
    $this->user->follow($this->influencer);
    notifyUser($this->user, $this->youtube, 'First video', now()->subHour());
    notifyUser($this->user, $this->x, 'Second tweet');
    [$tweet, $video] = $this->user->notifications->all();

    $this->get('/notifications')->assertOk()->assertSee('First video')->assertSee('Second tweet');

    Volt::test('notifications.index')
        ->assertSeeInOrder(['Second tweet', 'First video'])
        ->call('markRead', $tweet->id)
        ->assertDispatched('notifications-read')
        ->set('filter', 'unread')
        ->assertSee('First video')
        ->assertDontSee('Second tweet')
        ->call('open', $video->id)
        ->assertRedirect(route('influencers.show', $this->influencer));

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications read', function () {
    notifyUser($this->user, $this->youtube);
    notifyUser($this->user, $this->x);

    Volt::test('notifications.index')
        ->assertSee('Mark all as read')
        ->call('markAllRead')
        ->assertDispatched('notifications-read')
        ->assertDontSee('Mark all as read');

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('does not open other users\' notifications', function () {
    $other = User::factory()->create();
    notifyUser($other, $this->youtube);

    Volt::test('notifications.index')->call('open', $other->notifications->first()->id)->assertNotFound();
});

it('shows empty states', function () {
    Volt::test('notifications.index')
        ->assertSee('No notifications yet')
        ->set('filter', 'unread')
        ->assertSee("You're all caught up");
});

it('shows the unread count in the sidebar and on the bell', function () {
    Volt::test('notification-count', ['variant' => 'bell'])->assertDontSee('99+');

    notifyUser($this->user, $this->youtube);
    notifyUser($this->user, $this->x);

    $this->get('/feed')->assertOk()->assertSee('Notifications, 2 unread');

    $badge = Volt::test('notification-count', ['variant' => 'sidebar'])->assertSee('2');
    $this->user->unreadNotifications()->first()->markAsRead();
    $badge->dispatch('notifications-read');
    expect($badge->instance()->label())->toBe('1');
});

it('caps the badge at 99+', function () {
    DB::table('notifications')->insert(collect(range(1, 100))->map(fn () => [
        'id' => (string) Str::uuid(),
        'type' => 'new-post',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ])->all());

    expect(Volt::test('notification-count')->instance()->label())->toBe('99+');
});

it('emails digests of unread notifications since the last one', function () {
    $this->user->update(['email_digest' => DigestFrequency::Daily]);
    $weekly = User::factory()->create(['email_digest' => DigestFrequency::Weekly]);
    $off = User::factory()->create();
    $caughtUp = User::factory()->create(['email_digest' => DigestFrequency::Daily]);

    notifyUser($this->user, $this->youtube, 'Fresh video');
    notifyUser($this->user, $this->x, 'Too old', now()->subDays(2));
    notifyUser($weekly, $this->youtube, 'Weekly video', now()->subDays(3));
    notifyUser($off, $this->youtube);
    notifyUser($caughtUp, $this->youtube);
    $caughtUp->unreadNotifications->markAsRead();

    Notification::fake();

    $this->artisan('notifications:digest daily')->expectsOutput('Sent 1 daily digests.')->assertSuccessful();

    Notification::assertSentTo($this->user, NewPostsDigest::class, function (NewPostsDigest $digest) {
        $mail = $digest->toMail($this->user);

        return $digest->items->count() === 1
            && $mail->subject === '1 new post from creators you follow'
            && str_contains(implode(' ', $mail->introLines), 'Fresh video');
    });
    Notification::assertNotSentTo([$weekly, $off, $caughtUp], NewPostsDigest::class);
    expect($this->user->fresh()->digest_sent_at)->not->toBeNull();

    // Nothing new since the last digest.
    $this->artisan('notifications:digest daily')->expectsOutput('Sent 0 daily digests.');

    $this->artisan('notifications:digest weekly')->expectsOutput('Sent 1 weekly digests.');
    Notification::assertSentTo($weekly, NewPostsDigest::class);
});

it('rejects an unknown digest frequency', function () {
    $this->artisan('notifications:digest monthly')->assertFailed();
});
