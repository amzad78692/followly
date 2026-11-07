<?php

use App\Enums\DigestFrequency;
use App\Enums\Platform;
use App\Events\PostsSynced;
use App\Models\Influencer;
use App\Models\InfluencerAccount;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $this->actingAs($this->user);
});

/**
 * Have the user follow a creator's YouTube and X accounts, then sync a fresh post on the given one.
 */
function syncFreshPost(User $user, Platform $platform): void
{
    $influencer = Influencer::factory()->create();
    $accounts = collect([Platform::YouTube, Platform::X])
        ->map(fn ($p) => InfluencerAccount::factory()->for($influencer)->create(['platform' => $p]));

    test()->travelTo(now()->subDay());
    $user->follow($influencer);
    test()->travelBack();

    $account = $accounts->firstWhere('platform', $platform);
    PostsSynced::dispatch($account, new Collection([
        Post::factory()->for($account, 'account')->create(['published_at' => now()->subHour()]),
    ]));
}

it('shows the settings page with the current values', function () {
    $this->user->update(['email_digest' => DigestFrequency::Weekly, 'muted_platforms' => [Platform::TikTok]]);

    $this->get(route('settings'))->assertOk()->assertSee('Settings')->assertSee('old@example.com');

    Volt::test('settings')
        ->assertSet('name', 'Old Name')
        ->assertSet('digest', 'weekly')
        ->assertSet('notifyInApp', true)
        ->assertSet('platforms', ['youtube', 'instagram', 'x']);
});

it('sends guests to the login page', function () {
    Auth::logout();

    $this->get(route('settings'))->assertRedirect(route('login'));
});

it('updates the profile and clears verification when the email changes', function () {
    $this->user->forceFill(['email_verified_at' => now()])->save();

    Volt::test('settings')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertDispatched('profile-saved');

    $this->user->refresh();
    expect($this->user->name)->toBe('New Name')
        ->and($this->user->email)->toBe('new@example.com')
        ->and($this->user->email_verified_at)->toBeNull();
});

it('rejects an email another user has', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Volt::test('settings')
        ->set('email', 'taken@example.com')
        ->call('saveProfile')
        ->assertHasErrors(['email' => 'unique']);
});

it('uploads and removes an avatar', function () {
    Storage::fake('public');

    Volt::test('settings')
        ->set('avatar', UploadedFile::fake()->image('me.jpg'))
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSet('avatar', null);

    expect($this->user->fresh()->avatarUrl())->not->toBeNull();

    Volt::test('settings')->call('removeAvatar');

    expect($this->user->fresh()->avatarUrl())->toBeNull();
});

it('uploads and removes a banner', function () {
    Storage::fake('public');

    Volt::test('settings')
        ->set('banner', UploadedFile::fake()->image('cover.jpg', 1500, 500))
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSet('banner', null);

    expect($this->user->fresh()->bannerUrl())->not->toBeNull();

    Volt::test('settings')->call('removeBanner');

    expect($this->user->fresh()->bannerUrl())->toBeNull();
});

it('rejects an avatar that is not an image', function () {
    Storage::fake('public');

    Volt::test('settings')
        ->set('avatar', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->assertHasErrors(['avatar' => 'image']);
});

it('changes the password after checking the current one', function () {
    Volt::test('settings')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);

    Volt::test('settings')
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('current_password', '')
        ->assertDispatched('password-saved');

    expect(Hash::check('new-password-123', $this->user->fresh()->password))->toBeTrue();
});

it('saves notification preferences', function () {
    Volt::test('settings')
        ->set('notifyInApp', false)
        ->set('digest', 'daily')
        ->set('platforms', ['youtube', 'x'])
        ->call('saveNotifications')
        ->assertHasNoErrors()
        ->assertDispatched('notifications-saved');

    $this->user->refresh();
    expect($this->user->notify_in_app)->toBeFalse()
        ->and($this->user->email_digest)->toBe(DigestFrequency::Daily)
        ->and($this->user->muted_platforms->all())->toBe([Platform::Instagram, Platform::TikTok])
        ->and($this->user->wantsPostsFrom(Platform::X))->toBeTrue()
        ->and($this->user->wantsPostsFrom(Platform::TikTok))->toBeFalse();

    Volt::test('settings')
        ->set('digest', 'off')
        ->set('platforms', ['youtube', 'instagram', 'tiktok', 'x'])
        ->call('saveNotifications');

    $this->user->refresh();
    expect($this->user->email_digest)->toBeNull()
        ->and($this->user->muted_platforms)->toBeNull();
});

it('rejects unknown digest and platform values', function () {
    Volt::test('settings')
        ->set('digest', 'hourly')
        ->set('platforms', ['myspace'])
        ->call('saveNotifications')
        ->assertHasErrors(['digest', 'platforms.0']);
});

it('skips new post notifications for muted platforms', function () {
    $this->user->update(['muted_platforms' => [Platform::X]]);

    syncFreshPost($this->user, Platform::X);
    expect($this->user->notifications()->count())->toBe(0);

    syncFreshPost($this->user, Platform::YouTube);
    expect($this->user->notifications()->count())->toBe(1);
});

it('keeps notifications for the digest when in-app alerts are off, and hides the badge', function () {
    $this->user->update(['notify_in_app' => false]);

    syncFreshPost($this->user, Platform::YouTube);
    expect($this->user->notifications()->count())->toBe(0);

    $this->user->update(['email_digest' => DigestFrequency::Daily]);

    syncFreshPost($this->user, Platform::YouTube);
    expect($this->user->unreadNotifications()->count())->toBe(1);

    Volt::test('notification-count')->assertSeeHtml('aria-label="Notifications"');
    $this->get(route('notifications'))->assertSee('In-app notifications are off');
});

it('deletes the account after checking the password', function () {
    $influencer = Influencer::factory()->create();
    InfluencerAccount::factory()->for($influencer)->create();
    $this->user->follow($influencer);
    expect($influencer->fresh()->followers_count)->toBe(1);

    Volt::test('settings')
        ->set('delete_password', 'wrong-password')
        ->call('deleteAccount')
        ->assertHasErrors(['delete_password']);

    expect(User::find($this->user->id))->not->toBeNull();

    Volt::test('settings')
        ->set('delete_password', 'password')
        ->call('deleteAccount')
        ->assertRedirect('/');

    expect(User::find($this->user->id))->toBeNull()
        ->and($influencer->fresh()->followers_count)->toBe(0);
    $this->assertGuest();
});
