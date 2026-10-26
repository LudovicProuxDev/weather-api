<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->where('location', ['name' => 'La Rochelle', 'latitude' => 46.1631, 'longitude' => -1.1522])
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
            ->where('location', ['name' => null, 'latitude' => 48.8566, 'longitude' => 2.3522])
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
        ];
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

        for ($attempt = 0; $attempt < 30; $attempt++) {
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
