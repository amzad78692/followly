<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs a verified user into the admin panel when their email is an admin', function () {
    $admin = Admin::factory()->create(['email' => 'boss@example.com']);
    $user = User::factory()->create(['email' => 'boss@example.com']);

    $this->actingAs($user)->get(route('settings'))->assertSee('Admin panel');

    $this->actingAs($user)->post(route('admin.login'))->assertRedirect('/admin');

    expect(auth('admin')->id())->toBe($admin->id);
});

it('refuses users whose email is not verified, even if it matches an admin', function () {
    Admin::factory()->create(['email' => 'boss@example.com']);
    $user = User::factory()->unverified()->create(['email' => 'boss@example.com']);

    $this->actingAs($user)->get(route('settings'))
        ->assertSee('Verify email for admin panel')
        ->assertDontSee(route('admin.login'));

    $this->actingAs($user)->post(route('admin.login'))->assertForbidden();

    expect(auth('admin')->check())->toBeFalse();
});

it('refuses users who are not admins', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings'))->assertDontSee('Admin panel');

    $this->actingAs($user)->post(route('admin.login'))->assertForbidden();
});
