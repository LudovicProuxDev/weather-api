<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeatherForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('weather'));

        $response->assertRedirect(route('login'));
    }

    public function test_renders_the_hourly_forecast_grouped_by_day(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload())]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('weather'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('weather')
            ->where('location', [
                'id' => 3006787,
                'name' => 'La Rochelle',
                'region' => 'Nouvelle-Aquitaine',
                'department' => 'Charente-Maritime',
                'country' => 'France',
                'latitude' => 46.1631,
                'longitude' => -1.1522,
            ])
            ->where('search', null)
            ->where('cities', null)
            ->where('forecast.timezone', 'Europe/Paris')
            ->where('forecast.units', ['temperature' => '°C', 'humidity' => '%', 'precipitation' => 'mm', 'snowfall' => 'cm'])
            ->where('forecast.days', [
                [
                    'date' => '2026-10-26',
                    'min' => 12.5,
                    'max' => 18.4,
                    'precipitation' => 1.6,
                    'rain' => 1.6,
                    'snowfall' => 0,
                    'hours' => [
                        [
                            'time' => '00:00',
                            'temperature' => 12.5,
                            'apparentTemperature' => 11.2,
                            'humidity' => 88,
                            'dewPoint' => 10.6,
                            'precipitation' => 0.4,
                            'rain' => 0.4,
                            'snowfall' => 0,
                        ],
                        [
                            'time' => '13:00',
                            'temperature' => 18.4,
                            'apparentTemperature' => 17.9,
                            'humidity' => 61,
                            'dewPoint' => null,
                            'precipitation' => 1.2,
                            'rain' => 1.2,
                            'snowfall' => 0,
                        ],
                    ],
                ],
                [
                    'date' => '2026-10-27',
                    'min' => -0.5,
                    'max' => -0.5,
                    'precipitation' => 0.7,
                    'rain' => 0,
                    'snowfall' => 0.5,
                    'hours' => [
                        [
                            'time' => '00:00',
                            'temperature' => -0.5,
                            'apparentTemperature' => -3.1,
                            'humidity' => 95,
                            'dewPoint' => -1.2,
                            'precipitation' => 0.7,
                            'rain' => 0,
                            'snowfall' => 0.5,
                        ],
                    ],
                ],
            ])
        );
    }

    public function test_requests_the_meteo_france_variables_for_the_default_location(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload())]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('weather'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.open-meteo.com/v1/forecast?')
            && $request['latitude'] == 46.1631
            && $request['longitude'] == -1.1522
            && $request['hourly'] === 'temperature_2m,precipitation,relative_humidity_2m,dew_point_2m,apparent_temperature,rain,snowfall'
            && $request['models'] === 'meteofrance_seamless'
            && $request['forecast_days'] == 4
            && $request['timezone'] === 'auto');
    }

    public function test_requests_the_forecast_for_the_given_coordinates(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload())]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('weather', ['latitude' => '48.856613', 'longitude' => '2.352222']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('location', [
                'id' => null,
                'name' => null,
                'region' => null,
                'department' => null,
                'country' => null,
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ])
        );

        Http::assertSent(fn (Request $request): bool => $request['latitude'] == 48.8566
            && $request['longitude'] == 2.3522);
    }

    /**
     * @param  array<string, string>  $query
     */
    #[DataProvider('invalidCoordinates')]
    public function test_rejects_invalid_coordinates(array $query, string $invalidField): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('weather'))
            ->get(route('weather', $query));

        $response->assertRedirect(route('weather'));
        $response->assertSessionHasErrors($invalidField);

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidCoordinates(): array
    {
        return [
            'latitude above 90' => [['latitude' => '90.5', 'longitude' => '0'], 'latitude'],
            'latitude below -90' => [['latitude' => '-91', 'longitude' => '0'], 'latitude'],
            'longitude above 180' => [['latitude' => '0', 'longitude' => '180.1'], 'longitude'],
            'non numeric latitude' => [['latitude' => 'abc', 'longitude' => '0'], 'latitude'],
            'latitude without longitude' => [['latitude' => '46.1'], 'longitude'],
            'longitude without latitude' => [['longitude' => '-1.1'], 'latitude'],
            'search shorter than 2 characters' => [['search' => 'M'], 'search'],
            'search longer than 100 characters' => [['search' => str_repeat('a', 101)], 'search'],
            'non numeric city' => [['city' => 'marseille'], 'city'],
            'city with coordinates' => [['city' => '2995469', 'latitude' => '43.3', 'longitude' => '5.4'], 'city'],
        ];
    }

    public function test_lists_the_cities_matching_the_search(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload()),
            'geocoding-api.open-meteo.com/v1/search*' => Http::response(['results' => [$this->marseilleResult()]]),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('weather', ['search' => '  Marseille ']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('search', 'Marseille')
            ->where('cities', [$this->marseilleCity()])
        );

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://geocoding-api.open-meteo.com/v1/search?')
            && $request['name'] === 'Marseille'
            && $request['count'] == 10
            && $request['language'] === 'fr');
    }

    public function test_returns_no_cities_when_the_search_matches_nothing(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload()),
            'geocoding-api.open-meteo.com/v1/search*' => Http::response(['generationtime_ms' => 0.1]),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('weather', ['search' => 'zzqxw']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('cities', []));
    }

    public function test_keeps_the_forecast_when_the_city_search_is_unavailable(): void
    {
        Exceptions::fake();
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake([
            'api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload()),
            'geocoding-api.open-meteo.com/v1/search*' => Http::failedConnection(),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('weather', ['search' => 'Marseille']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('search', 'Marseille')
            ->where('cities', null)
            ->where('location.name', 'La Rochelle')
            ->has('forecast.days', 2)
        );

        Exceptions::assertReported(ConnectionException::class);
    }

    public function test_shows_the_forecast_of_the_selected_city(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'geocoding-api.open-meteo.com/v1/get*' => Http::response($this->marseilleResult()),
            'api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload()),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('weather', ['city' => 2995469]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('location', $this->marseilleCity())
            ->has('forecast.days', 2)
        );

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://geocoding-api.open-meteo.com/v1/get?')
            && $request['id'] == 2995469);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.open-meteo.com/v1/forecast?')
            && $request['latitude'] == 43.29695
            && $request['longitude'] == 5.38107);
    }

    public function test_rejects_an_unknown_city(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'geocoding-api.open-meteo.com/v1/get*' => Http::response(['error' => true, 'reason' => 'Location ID not found.'], 400),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('weather'))
            ->get(route('weather', ['city' => 999999999]));

        $response->assertRedirect(route('weather'));
        $response->assertSessionHasErrors(['city' => 'Cette ville est introuvable.']);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/v1/forecast'));
    }

    public function test_caches_the_forecast_between_requests_for_the_same_coordinates(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload())]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('weather'));
        $this->actingAs($user)->get(route('weather'));
        $this->actingAs($user)->get(route('weather', ['latitude' => '48.8566', 'longitude' => '2.3522']));

        Http::assertSentCount(2);
    }

    public function test_returns_429_after_too_many_forecast_requests(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload())]);
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->actingAs($user)->get(route('weather'))->assertOk();
        }

        $response = $this->actingAs($user)->get(route('weather'));

        $response->assertTooManyRequests();
    }

    public function test_renders_the_page_without_forecast_when_open_meteo_is_unavailable(): void
    {
        Exceptions::fake();
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response(status: 503)]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('weather'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('weather')
            ->where('forecast', null)
        );

        Exceptions::assertReported(RequestException::class);
        Http::assertSentCount(3);
    }

    /**
     * Build the Open-Meteo geocoding result for Marseille.
     *
     * @return array<string, mixed>
     */
    protected function marseilleResult(): array
    {
        return [
            'id' => 2995469,
            'name' => 'Marseille',
            'latitude' => 43.29695,
            'longitude' => 5.38107,
            'elevation' => 28.0,
            'feature_code' => 'PPLA',
            'country_code' => 'FR',
            'timezone' => 'Europe/Paris',
            'population' => 877215,
            'postcodes' => ['13001', '13002'],
            'country' => 'France',
            'admin1' => "Région Provence-Alpes-Côte d'Azur",
            'admin2' => 'Bouches-du-Rhône',
        ];
    }

    /**
     * Get the city the application exposes for the Marseille geocoding result.
     *
     * @return array<string, mixed>
     */
    protected function marseilleCity(): array
    {
        return [
            'id' => 2995469,
            'name' => 'Marseille',
            'region' => "Région Provence-Alpes-Côte d'Azur",
            'department' => 'Bouches-du-Rhône',
            'country' => 'France',
            'latitude' => 43.29695,
            'longitude' => 5.38107,
        ];
    }

    /**
     * Build an Open-Meteo forecast payload spanning two days, including an hour without temperature.
     *
     * @return array<string, mixed>
     */
    protected function openMeteoPayload(): array
    {
        return [
            'latitude' => 46.16,
            'longitude' => -1.15,
            'timezone' => 'Europe/Paris',
            'hourly_units' => [
                'time' => 'iso8601',
                'temperature_2m' => '°C',
                'precipitation' => 'mm',
                'relative_humidity_2m' => '%',
                'dew_point_2m' => '°C',
                'apparent_temperature' => '°C',
                'rain' => 'mm',
                'snowfall' => 'cm',
            ],
            'hourly' => [
                'time' => ['2026-10-26T00:00', '2026-10-26T13:00', '2026-10-27T00:00', '2026-10-27T14:00'],
                'temperature_2m' => [12.5, 18.4, -0.5, null],
                'precipitation' => [0.4, 1.2, 0.7, 0.3],
                'relative_humidity_2m' => [88, 61, 95, 90],
                'dew_point_2m' => [10.6, null, -1.2, -2.0],
                'apparent_temperature' => [11.2, 17.9, -3.1, -4.0],
                'rain' => [0.4, 1.2, 0.0, 0.0],
                'snowfall' => [0.0, 0.0, 0.5, 0.2],
            ],
        ];
    }
}
