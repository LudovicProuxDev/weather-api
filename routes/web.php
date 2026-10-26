<?php

use App\Http\Controllers\WeatherForecastController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('weather', WeatherForecastController::class)->name('weather');
});

require __DIR__.'/settings.php';
