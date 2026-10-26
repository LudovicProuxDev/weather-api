<?php

namespace App\Actions\Weather;

use App\Concerns\CallsOpenMeteo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @phpstan-type GeocodingResult array{
 *     id: int,
 *     name: string,
 *     latitude: float,
 *     longitude: float,
 *     admin1?: string,
 *     admin2?: string,
 *     country?: string,
 * }
 * @phpstan-type City array{
 *     id: int,
 *     name: string,
 *     region: string|null,
 *     department: string|null,
 *     country: string|null,
 *     latitude: float,
 *     longitude: float,
 * }
 */
class GeocodeCities
{
    use CallsOpenMeteo;

    /**
     * The maximum number of cities returned by a search.
     */
    protected int $searchLimit = 10;

    /**
     * Search cities whose name matches the given text.
     *
     * @return list<City>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function search(string $name): array
    {
        $name = Str::squish($name);

        return Cache::remember(
            'open-meteo:geocoding:search:'.md5(Str::lower($name)),
            config('services.open_meteo.geocoding_cache_ttl'),
            function () use ($name): array {
                /** @var list<GeocodingResult> $results */
                $results = $this->openMeteo(config('services.open_meteo.geocoding_url'))
                    ->get('search', [
                        'name' => $name,
                        'count' => $this->searchLimit,
                        'language' => 'fr',
                        'format' => 'json',
                    ])
                    ->throw()
                    ->json('results', []);

                return array_map($this->toCity(...), $results);
            },
        );
    }

    /**
     * Find a city by its Open-Meteo identifier, or null when it does not exist.
     *
     * @return City|null
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function find(int $id): ?array
    {
        return Cache::remember(
            'open-meteo:geocoding:city:'.$id,
            config('services.open_meteo.geocoding_cache_ttl'),
            function () use ($id): ?array {
                try {
                    /** @var GeocodingResult $result */
                    $result = $this->openMeteo(config('services.open_meteo.geocoding_url'))
                        ->get('get', [
                            'id' => $id,
                            'language' => 'fr',
                            'format' => 'json',
                        ])
                        ->throw()
                        ->json();
                } catch (RequestException $exception) {
                    if ($exception->response->badRequest()) {
                        return null;
                    }

                    throw $exception;
                }

                return $this->toCity($result);
            },
        );
    }

    /**
     * Keep the fields of a geocoding result that the application uses.
     *
     * @param  GeocodingResult  $result
     * @return City
     */
    protected function toCity(array $result): array
    {
        return [
            'id' => $result['id'],
            'name' => $result['name'],
            'region' => $result['admin1'] ?? null,
            'department' => $result['admin2'] ?? null,
            'country' => $result['country'] ?? null,
            'latitude' => (float) $result['latitude'],
            'longitude' => (float) $result['longitude'],
        ];
    }
}
