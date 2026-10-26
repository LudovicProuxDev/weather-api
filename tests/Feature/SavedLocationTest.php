<?php

namespace Tests\Feature;

use App\Models\SavedLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class SavedLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_save_a_location(): void
    {
        $response = $this->post(route('saved-locations.store'), ['city_id' => 2995469]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('saved_locations', 0);
    }

    public function test_saves_the_city_details_returned_by_open_meteo(): void
    {
        Http::preventStrayRequests();
        Http::fake(['geocoding-api.open-meteo.com/v1/get*' => Http::response($this->marseilleResult())]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('saved-locations.store'), [
            'city_id' => 2995469,
            'name' => 'Forged name',
            'latitude' => 0,
        ]);

        $response->assertRedirect(route('weather', ['city' => 2995469]));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('saved_locations', 1);
        $this->assertDatabaseHas('saved_locations', [
            'user_id' => $user->id,
            'city_id' => 2995469,
            'name' => 'Marseille',
            'region' => "Région Provence-Alpes-Côte d'Azur",
            'department' => 'Bouches-du-Rhône',
            'country' => 'France',
            'latitude' => 43.29695,
            'longitude' => 5.38107,
        ]);

        Http::assertSent(fn (Request $request): bool => $request['id'] == 2995469);
    }

    public function test_requires_a_city(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('saved-locations.store'));

        $response->assertSessionHasErrors(['city_id' => 'Le champ ville est obligatoire.']);
        $this->assertDatabaseCount('saved_locations', 0);
    }

    public function test_rejects_a_non_numeric_city(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('saved-locations.store'), ['city_id' => 'marseille']);

        $response->assertSessionHasErrors('city_id');
        $this->assertDatabaseCount('saved_locations', 0);
    }

    public function test_rejects_a_city_the_user_already_saved(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        SavedLocation::factory()->for($user)->create(['city_id' => 2995469]);

        $response = $this->actingAs($user)->post(route('saved-locations.store'), ['city_id' => 2995469]);

        $response->assertSessionHasErrors(['city_id' => 'Cette ville est déjà dans vos localisations.']);
        $this->assertDatabaseCount('saved_locations', 1);
    }

    public function test_allows_saving_a_city_another_user_already_saved(): void
    {
        Http::preventStrayRequests();
        Http::fake(['geocoding-api.open-meteo.com/v1/get*' => Http::response($this->marseilleResult())]);
        SavedLocation::factory()->create(['city_id' => 2995469]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('saved-locations.store'), ['city_id' => 2995469]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('saved_locations', ['user_id' => $user->id, 'city_id' => 2995469]);
    }

    public function test_rejects_a_location_beyond_the_per_user_limit(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        SavedLocation::factory()->for($user)->count(SavedLocation::MAX_PER_USER)->create();

        $response = $this->actingAs($user)->post(route('saved-locations.store'), ['city_id' => 2995469]);

        $response->assertSessionHasErrors(['city_id' => 'Vous pouvez enregistrer au maximum 20 localisations.']);
        $this->assertDatabaseCount('saved_locations', SavedLocation::MAX_PER_USER);
    }

    public function test_rejects_a_city_unknown_to_open_meteo(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'geocoding-api.open-meteo.com/v1/get*' => Http::response(['error' => true, 'reason' => 'Location ID not found.'], 400),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('saved-locations.store'), ['city_id' => 999999999]);

        $response->assertSessionHasErrors(['city_id' => 'Cette ville est introuvable.']);
        $this->assertDatabaseCount('saved_locations', 0);
    }

    public function test_does_not_save_when_open_meteo_is_unavailable(): void
    {
        Exceptions::fake();
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake(['geocoding-api.open-meteo.com/v1/get*' => Http::failedConnection()]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('saved-locations.store'), ['city_id' => 2995469]);

        $response->assertSessionHasErrors([
            'city_id' => 'La recherche de villes est indisponible pour le moment. Réessayez dans quelques minutes.',
        ]);
        $this->assertDatabaseCount('saved_locations', 0);

        Exceptions::assertReported(ConnectionException::class);
    }

    public function test_users_can_remove_their_location(): void
    {
        $user = User::factory()->create();
        $savedLocation = SavedLocation::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->from(route('weather'))
            ->delete(route('saved-locations.destroy', $savedLocation));

        $response->assertRedirect(route('weather'));
        $this->assertModelMissing($savedLocation);
    }

    public function test_users_cannot_remove_another_users_location(): void
    {
        $savedLocation = SavedLocation::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->delete(route('saved-locations.destroy', $savedLocation));

        $response->assertNotFound();
        $this->assertModelExists($savedLocation);
    }

    public function test_guests_cannot_remove_a_location(): void
    {
        $savedLocation = SavedLocation::factory()->create();

        $response = $this->delete(route('saved-locations.destroy', $savedLocation));

        $response->assertRedirect(route('login'));
        $this->assertModelExists($savedLocation);
    }

    public function test_deleting_a_user_deletes_their_locations(): void
    {
        $user = User::factory()->create();
        $savedLocation = SavedLocation::factory()->for($user)->create();

        $user->delete();

        $this->assertModelMissing($savedLocation);
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
            'country_code' => 'FR',
            'country' => 'France',
            'admin1' => "Région Provence-Alpes-Côte d'Azur",
            'admin2' => 'Bouches-du-Rhône',
        ];
    }
}
