<?php

use App\Http\Controllers\Api\DeviceAuthController;
use App\Http\Controllers\Api\WatchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('watch/pair/start', [DeviceAuthController::class, 'start'])->middleware('throttle:watch-pair-start');
Route::post('watch/pair/poll', [DeviceAuthController::class, 'poll'])->middleware('throttle:watch-pair-poll');
Route::post('mobile/login', [DeviceAuthController::class, 'login'])->middleware('throttle:device-login');

Route::middleware(['auth:sanctum', CheckAbilities::class.':watch', 'throttle:watch-api'])->group(function () {
    Route::post('mobile/logout', [DeviceAuthController::class, 'logout']);

    Route::prefix('watch')->controller(WatchController::class)->group(function () {
        Route::post('devices', 'registerDevice');
        Route::delete('devices', 'unregisterDevice');

        Route::get('home', 'home');

        Route::get('updates', 'updates');
        Route::post('updates/read-all', 'readAll');
        Route::get('updates/{id}', 'update');
        Route::post('updates/{id}/read', 'read');

        Route::get('summary', 'summary');

        Route::get('creators', 'creators');
        Route::patch('creators/{influencer}', 'updateCreator');
        Route::delete('creators/{influencer}', 'unfollow');

        Route::get('categories', 'categories');
        Route::get('trending', 'trending');

        Route::get('settings', 'settings');
        Route::patch('settings', 'updateSettings');
    });
});
