<?php

use App\Http\Controllers\SavedLocationController;
use App\Http\Controllers\WeatherForecastController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('weather', WeatherForecastController::class)
        ->middleware('throttle:60,1')
        ->name('weather');

    Route::post('weather/locations', [SavedLocationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('saved-locations.store');
    Route::delete('weather/locations/{savedLocation}', [SavedLocationController::class, 'destroy'])
        ->name('saved-locations.destroy');
});

require __DIR__.'/settings.php';
