<?php

use App\Http\Controllers\SocialLoginController;
use App\Livewire\Actions\Logout;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')->name('login');
    Volt::route('register', 'auth.register')->name('register');
    Volt::route('forgot-password', 'auth.forgot-password')->name('password.request');
    Volt::route('reset-password/{token}', 'auth.reset-password')->name('password.reset');

    Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', ['google', 'apple'])->name('social.redirect');
    // Apple posts the callback (form_post); Google uses GET.
    Route::match(['get', 'post'], 'auth/{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', ['google', 'apple'])->name('social.callback');
});

Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'auth.verify-email')->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('logout', Logout::class)->name('logout');
});
