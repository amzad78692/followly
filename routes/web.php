<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\RemoteImageController;
use App\Http\Controllers\SitemapController;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'home')->middleware('guest')->name('home');

Route::middleware('auth')->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Route::redirect('feed', 'dashboard', 301);
    Volt::route('explore', 'explore')->name('explore');
    Volt::route('search', 'search')->name('search');
    Volt::route('notifications', 'notifications.index')->name('notifications');
    Volt::route('alerts', 'alerts')->name('alerts');
    Volt::route('analytics', 'analytics')->name('analytics');
    Volt::route('settings', 'settings')->name('settings');
    // The short address shown on a watch while it waits for its pairing code.
    Route::get('connect', fn () => redirect(route('settings').'#devices'))->name('connect');
    Volt::route('influencers', 'influencers.index')->name('influencers.index');
    Volt::route('influencers/add', 'influencers.create')->name('influencers.create');
    Volt::route('collections', 'collections.index')->name('collections.index');

    // Users whose verified email is also an admin's can open the panel without logging in again.
    // Verification is required: anyone can register with any email address.
    Route::post('admin-login', function () {
        $user = auth()->user();
        $admin = $user->hasVerifiedEmail() ? Admin::where('email', $user->email)->first() : null;

        abort_unless($admin, 403);

        Auth::guard('admin')->login($admin);
        request()->session()->regenerate();

        return redirect('/admin');
    })->name('admin.login');
});

// Profiles are public so they can be shared and indexed; guests get the public layout.
Volt::route('influencers/{influencer}', 'influencers.show')->name('influencers.show');
Volt::route('c/{token}', 'collections.shared')->name('collections.shared');

Route::view('privacy', 'static.privacy')->name('privacy');
Route::view('terms', 'static.terms')->name('terms');
Route::view('data-deletion', 'static.data-deletion')->name('data-deletion');
Route::view('contact', 'static.contact')->name('contact');

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

require __DIR__.'/auth.php';

Route::get('/{locale}', function ($locale) {
    if (in_array($locale, config('app.supported_locales'))) {
        session(['locale' => $locale]);

        return redirect()->back();
    }
})->name('lang.switch')
    ->where('locale', implode('|', config('app.supported_locales')));

Route::get('storage/.cache/{imgPath}', [ImageController::class, 'manipulation'])
    ->where('imgPath', '.*')
    ->name('image.manipulation');

Route::get('img', RemoteImageController::class)
    ->middleware('signed')
    ->name('image.remote');

Route::get('robots.txt', fn () => response(implode("\n", [
    'User-agent: *',
    'Disallow: /admin',
    '',
    'Sitemap: '.route('sitemap'),
    '',
]), 200, ['Content-Type' => 'text/plain']))->name('robots');
