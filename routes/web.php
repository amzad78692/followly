<?php

use App\Http\Controllers\ImageController;
use App\Http\Controllers\RemoteImageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'home')->middleware('guest')->name('home');

Route::middleware('auth')->group(function () {
    Volt::route('feed', 'feed')->name('feed');
    Volt::route('explore', 'explore')->name('explore');
    Volt::route('search', 'search')->name('search');
    Volt::route('notifications', 'notifications.index')->name('notifications');
    Volt::route('settings', 'settings')->name('settings');
    Volt::route('influencers', 'influencers.index')->name('influencers.index');
    Volt::route('influencers/add', 'influencers.create')->name('influencers.create');
});

// Profiles are public so they can be shared and indexed; guests get the public layout.
Volt::route('influencers/{influencer}', 'influencers.show')->name('influencers.show');

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
