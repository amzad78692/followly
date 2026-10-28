<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('renders the auth pages for guests', function (string $uri) {
    $this->get($uri)->assertOk();
})->with(['/login', '/register', '/forgot-password', '/reset-password/token']);

it('redirects guests from dashboard pages to login', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with(['/feed', '/explore', '/influencers', '/influencers/add']);

it('sends logged-in users from the landing page to the feed', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('feed'));
});

it('shows the dashboard to logged-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/feed')
        ->assertOk();
});

it('registers a new user', function () {
    Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('feed', absolute: false));

    $this->assertAuthenticated();
    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

it('rejects a duplicate email on register', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'taken@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors('email');
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('feed', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('does not log in with a wrong password', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('rate-limits login attempts', function () {
    $user = User::factory()->create();

    $component = Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password');

    foreach (range(1, 5) as $attempt) {
        $component->call('login');
    }

    // Correct password is still refused once locked out.
    $component->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

it('sends a password reset link', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('rate-limits password reset requests', function () {
    Notification::fake();

    $component = Volt::test('auth.forgot-password');

    foreach (range(1, 5) as $attempt) {
        $component->set('email', "user{$attempt}@example.com")->call('sendPasswordResetLink');
    }

    $component->set('email', 'another@example.com')
        ->call('sendPasswordResetLink')
        ->assertHasErrors('email');
});

it('resets the password with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        Volt::test('auth.reset-password', ['token' => $notification->token])
            ->set('email', $user->email)
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});

it('verifies an email with a signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('feed', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('resends the verification email', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    Volt::test('auth.verify-email')->call('sendVerification');

    Notification::assertSentTo($user, VerifyEmail::class);
});
