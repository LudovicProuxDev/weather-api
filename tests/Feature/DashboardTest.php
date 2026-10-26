<?php

namespace Tests\Feature;

use App\Models\SavedLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_lists_only_the_users_saved_locations_by_name(): void
    {
        $user = User::factory()->create();
        $nantes = SavedLocation::factory()->for($user)->create(['name' => 'Nantes']);
        $brest = SavedLocation::factory()->for($user)->create(['name' => 'Brest']);
        SavedLocation::factory()->create(['name' => 'Lyon']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('savedLocations', 2)
            ->where('savedLocations.0.id', $brest->id)
            ->where('savedLocations.0.cityId', $brest->city_id)
            ->where('savedLocations.1.id', $nantes->id)
            ->missing('todayForecasts')
        );
    }

    public function test_loads_todays_forecast_for_each_saved_location_as_a_deferred_prop(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::response($this->openMeteoPayload())]);
        $this->travelTo(Carbon::parse('2026-10-26 13:20:00', 'Europe/Paris'));
        $user = User::factory()->create();
        $brest = SavedLocation::factory()->for($user)->create(['name' => 'Brest']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where("todayForecasts.{$brest->id}", [
                    'current' => 18.4,
                    'min' => 12.5,
                    'max' => 18.4,
                    'precipitation' => 1.6,
                    'snowfall' => 0,
                    'units' => ['temperature' => '°C', 'humidity' => '%', 'precipitation' => 'mm', 'snowfall' => 'cm'],
                ])
            )
        );
    }

    public function test_shows_no_forecast_for_a_location_when_open_meteo_is_unavailable(): void
    {
        Exceptions::fake();
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake(['api.open-meteo.com/v1/forecast*' => Http::failedConnection()]);
        $user = User::factory()->create();
        $brest = SavedLocation::factory()->for($user)->create(['name' => 'Brest']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where("todayForecasts.{$brest->id}", null)
            )
        );

        Exceptions::assertReported(ConnectionException::class);
    }

    /**
     * Build an Open-Meteo forecast payload for today, in the Europe/Paris timezone.
     *
     * @return array<string, mixed>
     */
    protected function openMeteoPayload(): array
    {
        return [
            'timezone' => 'Europe/Paris',
            'hourly_units' => [
                'temperature_2m' => '°C',
                'precipitation' => 'mm',
                'relative_humidity_2m' => '%',
                'snowfall' => 'cm',
            ],
            'hourly' => [
                'time' => ['2026-10-26T00:00', '2026-10-26T13:00'],
                'temperature_2m' => [12.5, 18.4],
                'precipitation' => [0.4, 1.2],
                'relative_humidity_2m' => [88, 61],
                'dew_point_2m' => [10.6, 11.0],
                'apparent_temperature' => [11.2, 17.9],
                'rain' => [0.4, 1.2],
                'snowfall' => [0.0, 0.0],
            ],
        ];
    }
}
