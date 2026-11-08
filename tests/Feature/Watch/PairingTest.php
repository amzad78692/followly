<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'secret-password']);
});

function startPairing(): array
{
    return test()->postJson('/api/watch/pair/start', ['device_name' => 'My Watch', 'platform' => 'wear_os'])
        ->assertOk()
        ->json();
}

function approvePairing(User $user, string $code): void
{
    test()->actingAs($user);

    Volt::test('settings')
        ->set('pairingCode', $code)
        ->call('findDevice')
        ->assertHasNoErrors()
        ->assertSet('pendingDevice.name', 'My Watch')
        ->call('confirmDevice')
        ->assertHasNoErrors();

    Auth::forgetGuards();
}

it('pairs a watch once the user approves its code, and hands over the token once', function () {
    $pairing = startPairing();

    expect($pairing['code'])->toMatch('/^[A-HJKMNP-Z2-9]{6}$/')
        ->and($pairing['device_secret'])->toHaveLength(64);

    $poll = fn () => $this->postJson('/api/watch/pair/poll', ['device_secret' => $pairing['device_secret']]);

    $poll()->assertOk()->assertExactJson(['status' => 'pending']);

    approvePairing($this->user, $pairing['code']);

    $token = $poll()->assertOk()->assertJsonPath('status', 'approved')->json('token');

    expect($this->user->tokens()->first())
        ->name->toBe('My Watch')
        ->abilities->toBe(['watch']);

    $this->withToken($token)->getJson('/api/watch/home')->assertOk();

    $poll()->assertStatus(410)->assertExactJson(['status' => 'expired']);
});

it('accepts a lower case code', function () {
    $pairing = startPairing();

    approvePairing($this->user, strtolower($pairing['code']));

    $this->postJson('/api/watch/pair/poll', ['device_secret' => $pairing['device_secret']])
        ->assertJsonPath('status', 'approved');
});

it('rejects a wrong code', function () {
    $this->actingAs($this->user);

    Volt::test('settings')
        ->set('pairingCode', 'ZZZZZZ')
        ->call('findDevice')
        ->assertHasErrors('pairingCode')
        ->assertSet('pendingDevice', null);
});

it('rejects an expired code', function () {
    $pairing = startPairing();

    $this->travel(301)->seconds();
    $this->actingAs($this->user);

    Volt::test('settings')
        ->set('pairingCode', $pairing['code'])
        ->call('findDevice')
        ->assertHasErrors('pairingCode');

    $this->postJson('/api/watch/pair/poll', ['device_secret' => $pairing['device_secret']])->assertStatus(410);
});

it('does not let a code be used twice', function () {
    $pairing = startPairing();
    $other = User::factory()->create();

    approvePairing($this->user, $pairing['code']);

    $this->actingAs($other);

    Volt::test('settings')
        ->set('pairingCode', $pairing['code'])
        ->call('findDevice')
        ->assertHasErrors('pairingCode');

    $this->postJson('/api/watch/pair/poll', ['device_secret' => $pairing['device_secret']]);

    expect($other->tokens()->count())->toBe(0)
        ->and($this->user->tokens()->count())->toBe(1);
});

it('only pairs watches through the code flow', function () {
    $this->postJson('/api/watch/pair/start', ['device_name' => 'Phone', 'platform' => 'android'])
        ->assertUnprocessable();
});

it('stops code guessing on the website after too many wrong codes', function () {
    $this->actingAs($this->user);
    $component = Volt::test('settings')->set('pairingCode', 'ZZZZZZ');

    foreach (range(1, 10) as $attempt) {
        $component->call('findDevice');
    }

    $pairing = startPairing();
    $this->actingAs($this->user);

    Volt::test('settings')
        ->set('pairingCode', $pairing['code'])
        ->call('findDevice')
        ->assertHasErrors('pairingCode')
        ->assertSet('pendingDevice', null);
});

it('rate limits starting a pairing', function () {
    foreach (range(1, 10) as $attempt) {
        startPairing();
    }

    $this->postJson('/api/watch/pair/start', ['device_name' => 'My Watch', 'platform' => 'wear_os'])
        ->assertTooManyRequests();
});

it('does not count polling against starting or logging in', function () {
    foreach (range(1, 12) as $attempt) {
        $this->postJson('/api/watch/pair/poll', ['device_secret' => str_repeat('a', 64)])->assertStatus(410);
    }

    startPairing();

    $this->postJson('/api/mobile/login', ['email' => $this->user->email, 'password' => 'secret-password', 'device_name' => 'Phone'])
        ->assertOk();
});

it('signs in and out the phone companion', function () {
    $token = $this->postJson('/api/mobile/login', ['email' => $this->user->email, 'password' => 'secret-password', 'device_name' => 'Phone'])
        ->assertOk()
        ->json('token');

    $this->withToken($token)->getJson('/api/watch/home')->assertOk();
    $this->withToken($token)->postJson('/api/mobile/logout')->assertNoContent();

    Auth::forgetGuards();

    $this->withToken($token)->getJson('/api/watch/home')->assertUnauthorized();
});

it('refuses a wrong password and rate limits login attempts', function () {
    $attempt = fn () => $this->postJson('/api/mobile/login', ['email' => $this->user->email, 'password' => 'wrong', 'device_name' => 'Phone']);

    foreach (range(1, 5) as $i) {
        $attempt()->assertUnprocessable();
    }

    $attempt()->assertTooManyRequests();

    expect($this->user->tokens()->count())->toBe(0);
});

it('stops working when disconnected from settings, and only the owner can disconnect', function () {
    $token = $this->user->createToken('My Watch', ['watch']);
    $other = User::factory()->create();

    $this->actingAs($other);
    Volt::test('settings')->call('disconnectDevice', $token->accessToken->id);
    expect($this->user->tokens()->count())->toBe(1);

    $this->actingAs($this->user);
    Volt::test('settings')->assertSee('My Watch')->call('disconnectDevice', $token->accessToken->id);

    Auth::forgetGuards();

    $this->withToken($token->plainTextToken)->getJson('/api/watch/home')->assertUnauthorized();
});

it('refuses tokens without the watch ability', function () {
    $this->withToken($this->user->createToken('Other', ['reports'])->plainTextToken)
        ->getJson('/api/watch/home')
        ->assertForbidden();
});

it('sends a signed-out visitor from the connect address to login and a user to settings', function () {
    $this->get('/connect')->assertRedirect(route('login'));

    $this->actingAs($this->user)->get('/connect')->assertRedirect(route('settings').'#devices');
});
