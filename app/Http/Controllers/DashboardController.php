<?php

namespace App\Http\Controllers;

use App\Actions\Weather\FetchHourlyForecast;
use App\Models\SavedLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the home page with today's weather for each saved location.
     *
     * Forecasts are deferred so the page renders immediately, even with many locations.
     */
    public function __invoke(Request $request, FetchHourlyForecast $fetchHourlyForecast): Response
    {
        $savedLocations = $request->user()->savedLocations()->orderBy('name')->get();

        return Inertia::render('dashboard', [
            'savedLocations' => $savedLocations
                ->map(fn (SavedLocation $savedLocation): array => [
                    'id' => $savedLocation->id,
                    'cityId' => $savedLocation->city_id,
                    'name' => $savedLocation->name,
                    'region' => $savedLocation->region,
                    'department' => $savedLocation->department,
                    'country' => $savedLocation->country,
                ])
                ->all(),
            'todayForecasts' => Inertia::defer(
                fn (): array => $this->todayForecasts($savedLocations, $fetchHourlyForecast),
            ),
        ]);
    }

    /**
     * Summarize today's forecast for each saved location, keyed by saved location id (ids start at 1, so it serializes as a JSON object).
     *
     * @param  Collection<int, SavedLocation>  $savedLocations
     * @return array<int, array{current: float|null, min: float, max: float, precipitation: float, snowfall: float, units: array{temperature: string, humidity: string, precipitation: string, snowfall: string}}|null>
     */
    protected function todayForecasts(Collection $savedLocations, FetchHourlyForecast $fetchHourlyForecast): array
    {
        $summaries = [];

        foreach ($savedLocations as $savedLocation) {
            try {
                $forecast = $fetchHourlyForecast->handle($savedLocation->latitude, $savedLocation->longitude);
            } catch (ConnectionException|RequestException $exception) {
                report($exception);
                $summaries[$savedLocation->id] = null;

                continue;
            }

            $today = $forecast['days'][0] ?? null;

            if ($today === null) {
                $summaries[$savedLocation->id] = null;

                continue;
            }

            $currentHour = Carbon::now($forecast['timezone'])->format('H:00');
            $currentTemperature = collect($today['hours'])->firstWhere('time', $currentHour)['temperature'] ?? null;

            $summaries[$savedLocation->id] = [
                'current' => $currentTemperature,
                'min' => $today['min'],
                'max' => $today['max'],
                'precipitation' => $today['precipitation'],
                'snowfall' => $today['snowfall'],
                'units' => $forecast['units'],
            ];
        }

        return $summaries;
    }
}
