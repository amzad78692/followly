<?php

use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Admin;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jacobtims\FilamentLogger\Resources\ActivityResource\Pages\ListActivities;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Mirrors scripts/migrate.sh: Shield generates the permissions and super_admin gets all of them
 * (super_admin is a plain role here, not a gate bypass).
 */
function superAdmin(array $attributes = []): Admin
{
    test()->artisan('shield:generate', ['--all' => true, '--option' => 'permissions', '--panel' => 'admin'])
        ->assertSuccessful();

    $role = Role::findOrCreate('super_admin', 'admin');
    $role->syncPermissions(Permission::where('guard_name', 'admin')->get());

    $admin = Admin::factory()->create($attributes);
    $admin->assignRole($role);

    return $admin;
}

test('guests are redirected to the admin login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('the admin login page renders', function () {
    $this->get('/admin/login')->assertOk()->assertSee('Sign in');
});

test('admins can log in with valid credentials', function () {
    $admin = superAdmin();

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'Admin@123'])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    $this->assertAuthenticatedAs($admin, 'admin');
});

test('admins cannot log in with an invalid password', function () {
    $admin = superAdmin();

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'wrong-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest('admin');
});

test('super admins can open every panel page', function (string $uri) {
    $this->actingAs(superAdmin(), 'admin')
        ->get($uri)
        ->assertOk();
})->with([
    'dashboard' => '/admin',
    'admins' => '/admin/admins',
    'create admin' => '/admin/admins/create',
    'users' => '/admin/users',
    'create user' => '/admin/users/create',
    'roles' => '/admin/shield/roles',
    'create role' => '/admin/shield/roles/create',
    'general settings' => '/admin/general',
    'contact settings' => '/admin/contact',
    'activity logs' => '/admin/activity-logs',
    'log viewer' => '/admin/logs',
]);

test('admins without a role cannot manage users', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/admin/users')
        ->assertForbidden();
});

test('super admins can create a user', function () {
    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'mobile' => '99887766',
            'password' => 'secret-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::firstWhere('email', 'jane@example.com');

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->mobile)->toBe('99887766');
});

test('user creation validates required and unique fields', function () {
    $this->actingAs(superAdmin(), 'admin');
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => 'taken@example.com', 'password' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique', 'password' => 'required']);
});

test('super admins can edit a user', function () {
    $this->actingAs(superAdmin(), 'admin');
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['name' => 'Renamed User'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Renamed User');
});

test('the users table lists and searches users', function () {
    $this->actingAs(superAdmin(), 'admin');
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->email)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1));
});

test('super admins can create an admin with a role', function () {
    $this->actingAs(superAdmin(), 'admin');
    $role = Role::findOrCreate('panel_user', 'admin');

    Livewire::test(CreateAdmin::class)
        ->fillForm([
            'name' => 'Second Admin',
            'email' => 'second@example.com',
            'mobile' => '55443322',
            'password' => 'secret-password',
            'roles' => [$role->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Admin::firstWhere('email', 'second@example.com')->hasRole('panel_user'))->toBeTrue();
});

test('activity log filters can be applied', function () {
    $this->actingAs(superAdmin(), 'admin');

    Livewire::test(ListActivities::class)
        ->filterTable('old_properties', ['old' => 'name'])
        ->assertOk()
        ->filterTable('new_properties', ['new' => 'name'])
        ->assertOk()
        ->resetTableFilters()
        ->assertOk();
});
