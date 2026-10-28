<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a successful response', function () {
    $this->get('/')->assertOk();
});

it('stores a supported locale in the session and redirects back', function () {
    $this->from('/')
        ->get('/ar')
        ->assertRedirect('/')
        ->assertSessionHas('locale', 'ar');
});

it('does not treat unsupported locales as locale switches', function () {
    $this->get('/xx')->assertNotFound();
});

it('requires authentication for the api user endpoint', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns the authenticated user from the api', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('hides the api docs from guests', function () {
    $this->get('/docs/api')->assertForbidden();
});

it('shows the api docs to admins', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/docs/api')
        ->assertOk();
});
