<?php

namespace App\Actions\Weather;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class FetchHourlyForecast
{
    /**
     * Fetch the hourly temperature forecast from Open-Meteo, grouped by day.
     *
     * @return array{timezone: string, unit: string, days: list<array{date: string, min: float, max: float, hours: list<array{time: string, temperature: float}>}>}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function handle(float $latitude, float $longitude): array
    {
        $config = config('services.open_meteo');

        $cacheKey = sprintf(
            'open-meteo:%s:%d:%.4f:%.4f',
            $config['model'],
            $config['forecast_days'],
            $latitude,
            $longitude,
        );

        return Cache::remember(
            $cacheKey,
            $config['cache_ttl'],
            fn (): array => $this->groupByDay($this->request($config, $latitude, $longitude)),
        );
    }

    /**
     * Call the Open-Meteo forecast endpoint.
     *
     * @param  array{base_url: string, model: string, forecast_days: int}  $config
     * @return array{timezone: string, hourly_units: array<string, string>, hourly: array{time: list<string>, temperature_2m: list<float|null>}}
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    protected function request(array $config, float $latitude, float $longitude): array
    {
        return Http::baseUrl($config['base_url'])
            ->connectTimeout(3)
            ->timeout(5)
            ->retry([200, 500], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429)))
            ->get('forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'hourly' => 'temperature_2m',
                'models' => $config['model'],
                'forecast_days' => $config['forecast_days'],
                'timezone' => 'auto',
            ])
            ->throw()
            ->json();
    }

    /**
     * Group the hourly temperatures by local day, skipping hours without data.
     *
     * @param  array{timezone: string, hourly_units: array<string, string>, hourly: array{time: list<string>, temperature_2m: list<float|null>}}  $payload
     * @return array{timezone: string, unit: string, days: list<array{date: string, min: float, max: float, hours: list<array{time: string, temperature: float}>}>}
     */
    protected function groupByDay(array $payload): array
    {
        $temperatures = $payload['hourly']['temperature_2m'];
        $hoursByDate = [];

        foreach ($payload['hourly']['time'] as $index => $time) {
            $temperature = $temperatures[$index] ?? null;

            if ($temperature === null) {
                continue;
            }

            $hoursByDate[substr($time, 0, 10)][] = [
                'time' => substr($time, 11, 5),
                'temperature' => (float) $temperature,
            ];
        }

        $days = [];

        foreach ($hoursByDate as $date => $hours) {
            $dayTemperatures = array_column($hours, 'temperature');

            $days[] = [
                'date' => (string) $date,
                'min' => min($dayTemperatures),
                'max' => max($dayTemperatures),
                'hours' => $hours,
            ];
        }

        return [
            'timezone' => $payload['timezone'],
            'unit' => $payload['hourly_units']['temperature_2m'] ?? '°C',
            'days' => $days,
        ];
    }
}
