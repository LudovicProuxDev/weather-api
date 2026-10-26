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
            ->where('location.name', 'La Rochelle')
            ->where('forecast.timezone', 'Europe/Paris')
            ->where('forecast.unit', '°C')
            ->where('forecast.days', [
                [
                    'date' => '2026-10-26',
                    'min' => 12.5,
                    'max' => 18.4,
                    'hours' => [
                        ['time' => '00:00', 'temperature' => 12.5],
                        ['time' => '13:00', 'temperature' => 18.4],
                    ],
                ],
                [
                    'date' => '2026-10-27',
                    'min' => 9.1,
                    'max' => 9.1,
                    'hours' => [
                        ['time' => '00:00', 'temperature' => 9.1],
                    ],
                ],
            ])
        );
    }

    public function test_requests_the_meteo_france_model_for_the_default_location(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload())]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('weather'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.open-meteo.com/v1/forecast?')
            && $request['latitude'] == 46.1631
            && $request['longitude'] == -1.1522
            && $request['hourly'] === 'temperature_2m'
            && $request['models'] === 'meteofrance_seamless'
            && $request['forecast_days'] == 4
            && $request['timezone'] === 'auto');
    }

    public function test_caches_the_forecast_between_requests(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload())]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('weather'));
        $this->actingAs($user)->get(route('weather'));

        Http::assertSentCount(1);
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
     * Build an Open-Meteo forecast payload spanning two days, including an hour without data.
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
            ],
            'hourly' => [
                'time' => ['2026-10-26T00:00', '2026-10-26T13:00', '2026-10-27T00:00', '2026-10-27T14:00'],
                'temperature_2m' => [12.5, 18.4, 9.1, null],
            ],
        ];
    }
}
