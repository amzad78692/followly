<?php

use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

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
