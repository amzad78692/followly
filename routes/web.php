<?php

use App\Http\Controllers\ImageController;
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
    Volt::route('influencers/{influencer}', 'influencers.show')->name('influencers.show');
});

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
