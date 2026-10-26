<?php

namespace App\Http\Controllers;

use App\Actions\Weather\FetchHourlyForecast;
use App\Http\Requests\ShowWeatherForecastRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Inertia\Inertia;
use Inertia\Response;

class WeatherForecastController extends Controller
{
    /**
     * Show the hourly forecast for the requested coordinates, or the default location.
     */
    public function __invoke(ShowWeatherForecastRequest $request, FetchHourlyForecast $fetchHourlyForecast): Response
    {
        $defaultLocation = config('services.open_meteo.default_location');
        $latitude = $request->latitude();
        $longitude = $request->longitude();

        $isDefaultLocation = $latitude === round($defaultLocation['latitude'], 4)
            && $longitude === round($defaultLocation['longitude'], 4);

        try {
            $forecast = $fetchHourlyForecast->handle($latitude, $longitude);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            $forecast = null;
        }

        return Inertia::render('weather', [
            'location' => [
                'name' => $isDefaultLocation ? $defaultLocation['name'] : null,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
            'forecast' => $forecast,
        ]);
    }
}
