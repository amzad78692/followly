<?php

use App\Filament\Pages\General;
use App\Models\Admin;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('returns a successful response', function () {
    $this->get('/')->assertOk();
});

it('adds google analytics only when a measurement id is set', function () {
    $this->get('/')->assertDontSee('googletagmanager.com');

    app(GeneralSettings::class)->fill(['google_analytics_id' => 'G-TEST123'])->save();

    $this->get('/')->assertSee('googletagmanager.com/gtag/js?id=G-TEST123', false);
});

it('keeps google analytics off the password reset page', function () {
    app(GeneralSettings::class)->fill(['google_analytics_id' => 'G-TEST123'])->save();

    $this->get('/reset-password/secret-token?email=a@example.com')
        ->assertOk()
        ->assertDontSee('googletagmanager.com');
});

it('lets admins save a valid google analytics id from general settings', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(Admin::factory()->create(), 'admin');

    Livewire::test(General::class)
        ->fillForm(['google_analytics_id' => '"><script>'])
        ->call('save')
        ->assertHasFormErrors(['google_analytics_id']);

    Livewire::test(General::class)
        ->fillForm(['google_analytics_id' => 'G-TEST123'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(setting('general.google_analytics_id'))->toBe('G-TEST123');
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
