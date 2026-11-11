<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function fakeSocial(string $provider, array $attributes, bool $verified = true): void
{
    Socialite::fake($provider, SocialUser::fake($attributes)->setRaw(['email_verified' => $verified]));
}

it('redirects to the provider', function (string $provider) {
    Socialite::fake($provider);

    $this->get("/auth/{$provider}/redirect")->assertRedirect();
})->with(['google', 'apple']);

it('rejects unknown providers', function () {
    $this->get('/auth/github/redirect')->assertNotFound();
});

it('creates a verified account for a new google user', function () {
    fakeSocial('google', ['id' => 'g-1', 'name' => 'Sara Ali', 'email' => 'Sara@Example.com']);

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

    $user = User::sole();
    expect($user->email)->toBe('sara@example.com')
        ->and($user->google_id)->toBe('g-1')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

// CSRF is skipped in tests, so the bootstrap/app.php exception is only proven on a real Apple login.
it('accepts the apple callback as a post', function () {
    fakeSocial('apple', ['id' => 'a-1', 'name' => null, 'email' => 'abc@privaterelay.appleid.com']);

    $this->post('/auth/apple/callback')->assertRedirect(route('dashboard', absolute: false));

    // Apple sent no name, so the email's local part is used.
    expect(User::sole())->name->toBe('abc')->apple_id->toBe('a-1');
});

it('links to an existing account by verified email', function () {
    $user = User::factory()->create(['email' => 'sara@example.com']);
    fakeSocial('google', ['id' => 'g-1', 'name' => 'Sara', 'email' => 'sara@example.com']);

    $this->get('/auth/google/callback');

    expect($user->fresh()->google_id)->toBe('g-1')->and(User::count())->toBe(1);
    $this->assertAuthenticatedAs($user);
});

it('does not link an existing account by an unverified email', function () {
    User::factory()->create(['email' => 'sara@example.com']);
    fakeSocial('google', ['id' => 'g-1', 'name' => 'Sara', 'email' => 'sara@example.com'], verified: false);

    $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('social');

    $this->assertGuest();
});

it('logs in a returning user by provider id even if their email changed', function () {
    $user = User::factory()->create(['email' => 'old@example.com', 'google_id' => 'g-1']);
    fakeSocial('google', ['id' => 'g-1', 'name' => 'Sara', 'email' => 'new@example.com']);

    $this->get('/auth/google/callback');

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

it('sends a cancelled login back to the login page', function () {
    // No fake: the real provider fails without a code.
    config(['services.google.client_id' => 'x', 'services.google.client_secret' => 'y']);

    $this->get('/auth/google/callback?error=access_denied')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('social');

    $this->assertGuest();
});

it('lets a social user set a password without a current one', function () {
    $user = User::factory()->create(['password' => null]);

    Volt::actingAs($user)->test('settings')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('lets a social user delete their account by typing their email', function () {
    $user = User::factory()->create(['password' => null, 'email' => 'sara@example.com']);

    Volt::actingAs($user)->test('settings')->set('delete_password', 'wrong@example.com')->call('deleteAccount')->assertHasErrors('delete_password');
    Volt::actingAs($user)->test('settings')->set('delete_password', 'sara@example.com')->call('deleteAccount')->assertHasNoErrors();

    expect(User::find($user->id))->toBeNull();
});
