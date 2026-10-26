<?php

namespace App\Actions\Weather;

use App\Concerns\CallsOpenMeteo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-type OpenMeteoPayload array{
 *     timezone: string,
 *     hourly_units: array<string, string>,
 *     hourly: array{
 *         time: list<string>,
 *         temperature_2m: list<float|null>,
 *         apparent_temperature: list<float|null>,
 *         relative_humidity_2m: list<int|null>,
 *         dew_point_2m: list<float|null>,
 *         precipitation: list<float|null>,
 *         rain: list<float|null>,
 *         snowfall: list<float|null>,
 *     },
 * }
 * @phpstan-type ForecastHour array{
 *     time: string,
 *     temperature: float,
 *     apparentTemperature: float|null,
 *     humidity: int|null,
 *     dewPoint: float|null,
 *     precipitation: float|null,
 *     rain: float|null,
 *     snowfall: float|null,
 * }
 * @phpstan-type ForecastDay array{
 *     date: string,
 *     min: float,
 *     max: float,
 *     precipitation: float,
 *     rain: float,
 *     snowfall: float,
 *     hours: list<ForecastHour>,
 * }
 * @phpstan-type Forecast array{
 *     timezone: string,
 *     units: array{temperature: string, humidity: string, precipitation: string, snowfall: string},
 *     days: list<ForecastDay>,
 * }
 */
class FetchHourlyForecast
{
    use CallsOpenMeteo;

    /**
     * The hourly variables requested from Open-Meteo.
     *
     * @var list<string>
     */
    protected array $hourlyVariables = [
        'temperature_2m',
        'precipitation',
        'relative_humidity_2m',
        'dew_point_2m',
        'apparent_temperature',
        'rain',
        'snowfall',
    ];

    /**
     * Fetch the hourly forecast from Open-Meteo, grouped by local day.
     *
     * @return Forecast
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function handle(float $latitude, float $longitude): array
    {
        $config = config('services.open_meteo');

        $cacheKey = sprintf(
            'open-meteo:%s:%d:%s:%.4f:%.4f',
            $config['model'],
            $config['forecast_days'],
            implode(',', $this->hourlyVariables),
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
     * @return OpenMeteoPayload
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    protected function request(array $config, float $latitude, float $longitude): array
    {
        return $this->openMeteo($config['base_url'])
            ->get('forecast', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'hourly' => implode(',', $this->hourlyVariables),
                'models' => $config['model'],
                'forecast_days' => $config['forecast_days'],
                'timezone' => 'auto',
            ])
            ->throw()
            ->json();
    }

    /**
     * Group the hourly values by local day, skipping hours without a temperature.
     *
     * @param  OpenMeteoPayload  $payload
     * @return Forecast
     */
    protected function groupByDay(array $payload): array
    {
        $hourly = $payload['hourly'];
        $hoursByDate = [];

        foreach ($hourly['time'] as $index => $time) {
            $temperature = $hourly['temperature_2m'][$index] ?? null;

            if ($temperature === null) {
                continue;
            }

            $hoursByDate[substr($time, 0, 10)][] = [
                'time' => substr($time, 11, 5),
                'temperature' => (float) $temperature,
                'apparentTemperature' => $this->toFloat($hourly['apparent_temperature'][$index] ?? null),
                'humidity' => $hourly['relative_humidity_2m'][$index] ?? null,
                'dewPoint' => $this->toFloat($hourly['dew_point_2m'][$index] ?? null),
                'precipitation' => $this->toFloat($hourly['precipitation'][$index] ?? null),
                'rain' => $this->toFloat($hourly['rain'][$index] ?? null),
                'snowfall' => $this->toFloat($hourly['snowfall'][$index] ?? null),
            ];
        }

        $days = [];

        foreach ($hoursByDate as $date => $hours) {
            $temperatures = array_column($hours, 'temperature');

            $days[] = [
                'date' => (string) $date,
                'min' => min($temperatures),
                'max' => max($temperatures),
                'precipitation' => round(array_sum(array_column($hours, 'precipitation')), 1),
                'rain' => round(array_sum(array_column($hours, 'rain')), 1),
                'snowfall' => round(array_sum(array_column($hours, 'snowfall')), 1),
                'hours' => $hours,
            ];
        }

        $units = $payload['hourly_units'];

        return [
            'timezone' => $payload['timezone'],
            'units' => [
                'temperature' => $units['temperature_2m'] ?? '°C',
                'humidity' => $units['relative_humidity_2m'] ?? '%',
                'precipitation' => $units['precipitation'] ?? 'mm',
                'snowfall' => $units['snowfall'] ?? 'cm',
            ],
            'days' => $days,
        ];
    }

    /**
     * Normalize a JSON number to a float, keeping missing values as null.
     */
    protected function toFloat(int|float|null $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
