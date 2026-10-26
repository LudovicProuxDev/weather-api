<?php

namespace App\Http\Controllers;

use App\Actions\Weather\FetchHourlyForecast;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Inertia\Inertia;
use Inertia\Response;

class WeatherForecastController extends Controller
{
    /**
     * Show the hourly temperature forecast for the default location.
     */
    public function __invoke(FetchHourlyForecast $fetchHourlyForecast): Response
    {
        $location = config('services.open_meteo.default_location');

        try {
            $forecast = $fetchHourlyForecast->handle($location['latitude'], $location['longitude']);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            $forecast = null;
        }

        return Inertia::render('weather', [
            'location' => $location,
            'forecast' => $forecast,
        ]);
    }
}
