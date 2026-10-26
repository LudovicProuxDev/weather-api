<?php

namespace App\Http\Controllers;

use App\Actions\Weather\FetchHourlyForecast;
use App\Actions\Weather\GeocodeCities;
use App\Http\Requests\ShowWeatherForecastRequest;
use App\Models\SavedLocation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WeatherForecastController extends Controller
{
    /**
     * Show the city search and the hourly forecast for the selected location.
     */
    public function __invoke(
        ShowWeatherForecastRequest $request,
        FetchHourlyForecast $fetchHourlyForecast,
        GeocodeCities $geocodeCities,
    ): Response {
        $weather = null;

        $loadWeather = function () use (&$weather, $request, $fetchHourlyForecast, $geocodeCities): array {
            return $weather ??= $this->loadWeather($request, $fetchHourlyForecast, $geocodeCities);
        };

        return Inertia::render('weather', [
            'location' => fn (): ?array => $loadWeather()['location'],
            'forecast' => fn (): ?array => $loadWeather()['forecast'],
            'search' => $request->search(),
            'cities' => fn (): ?array => $this->searchCities($request->search(), $geocodeCities),
            'savedLocations' => fn (): array => $request->user()->savedLocations()
                ->orderBy('name')
                ->get()
                ->map(fn (SavedLocation $savedLocation): array => [
                    'id' => $savedLocation->id,
                    'cityId' => $savedLocation->city_id,
                    'name' => $savedLocation->name,
                    'region' => $savedLocation->region,
                    'department' => $savedLocation->department,
                    'country' => $savedLocation->country,
                ])
                ->all(),
        ]);
    }

    /**
     * Resolve the requested location and fetch its forecast, tolerating Open-Meteo outages.
     *
     * @return array{location: array<string, mixed>|null, forecast: array<string, mixed>|null}
     */
    protected function loadWeather(
        ShowWeatherForecastRequest $request,
        FetchHourlyForecast $fetchHourlyForecast,
        GeocodeCities $geocodeCities,
    ): array {
        $location = null;
        $forecast = null;

        try {
            $location = $this->resolveLocation($request, $geocodeCities);
            $forecast = $fetchHourlyForecast->handle($location['latitude'], $location['longitude']);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
        }

        return ['location' => $location, 'forecast' => $forecast];
    }

    /**
     * Resolve the location from the selected city, explicit coordinates, the user's first saved location, or the default city.
     *
     * @return array{id: int|null, name: string|null, region: string|null, department: string|null, country: string|null, latitude: float, longitude: float}
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws ValidationException
     */
    protected function resolveLocation(ShowWeatherForecastRequest $request, GeocodeCities $geocodeCities): array
    {
        $cityId = $request->cityId();

        if ($cityId !== null) {
            $savedLocation = $request->user()->savedLocations()->where('city_id', $cityId)->first();

            if ($savedLocation !== null) {
                return $this->toLocation($savedLocation);
            }

            return $geocodeCities->find($cityId)
                ?? throw ValidationException::withMessages(['city' => 'Cette ville est introuvable.']);
        }

        if ($request->hasCoordinates()) {
            return [
                'id' => null,
                'name' => null,
                'region' => null,
                'department' => null,
                'country' => null,
                'latitude' => $request->latitude(),
                'longitude' => $request->longitude(),
            ];
        }

        $firstSavedLocation = $request->user()->savedLocations()->orderBy('name')->first();

        if ($firstSavedLocation !== null) {
            return $this->toLocation($firstSavedLocation);
        }

        return config('services.open_meteo.default_location');
    }

    /**
     * Convert a saved location to the location shape used by the page.
     *
     * @return array{id: int, name: string, region: string|null, department: string|null, country: string|null, latitude: float, longitude: float}
     */
    protected function toLocation(SavedLocation $savedLocation): array
    {
        return [
            'id' => $savedLocation->city_id,
            'name' => $savedLocation->name,
            'region' => $savedLocation->region,
            'department' => $savedLocation->department,
            'country' => $savedLocation->country,
            'latitude' => $savedLocation->latitude,
            'longitude' => $savedLocation->longitude,
        ];
    }

    /**
     * Search cities matching the given name, or null when there is no search or the API is unavailable.
     *
     * @return list<array<string, mixed>>|null
     */
    protected function searchCities(?string $search, GeocodeCities $geocodeCities): ?array
    {
        if ($search === null) {
            return null;
        }

        try {
            return $geocodeCities->search($search);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return null;
        }
    }
}
