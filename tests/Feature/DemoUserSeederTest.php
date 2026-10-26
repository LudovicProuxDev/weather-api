<?php

namespace Tests\Feature;

use App\Actions\Weather\GeocodeCities;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DemoUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected string $credentialsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'demo-users-');
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsFile);

        parent::tearDown();
    }

    public function test_creates_verified_users_with_hashed_passwords_and_saved_cities(): void
    {
        Http::preventStrayRequests();
        Http::fake(['geocoding-api.open-meteo.com/v1/get*' => Http::response([
            'id' => 2988507,
            'name' => 'Paris',
            'latitude' => 48.85341,
            'longitude' => 2.3488,
            'country' => 'France',
            'admin1' => 'Île-de-France',
            'admin2' => 'Département de Paris',
        ])]);
        file_put_contents($this->credentialsFile, <<<'ENV'
            DEMO_USERS_COUNT=1
            DEMO_USER_1_FIRSTNAME="Léa"
            DEMO_USER_1_LASTNAME="Martin"
            DEMO_USER_1_EMAIL="lea.martin@weather-api.test"
            DEMO_USER_1_PASSWORD="Secret-Pass-2026!"
            DEMO_USER_1_CITIES="2988507"
            ENV);

        (new DemoUserSeeder($this->credentialsFile))->run(app(GeocodeCities::class));

        $user = User::where('email', 'lea.martin@weather-api.test')->sole();
        $this->assertSame('Léa Martin', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame('Secret-Pass-2026!', $user->password);
        $this->assertTrue(Hash::check('Secret-Pass-2026!', $user->password));
        $this->assertDatabaseHas('saved_locations', [
            'user_id' => $user->id,
            'city_id' => 2988507,
            'name' => 'Paris',
            'department' => 'Département de Paris',
        ]);
    }

    public function test_running_twice_updates_the_existing_accounts(): void
    {
        Http::preventStrayRequests();
        $this->writeAccount('First-Pass-2026!');
        (new DemoUserSeeder($this->credentialsFile))->run(app(GeocodeCities::class));
        $this->writeAccount('Second-Pass-2026!');

        (new DemoUserSeeder($this->credentialsFile))->run(app(GeocodeCities::class));

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(Hash::check('Second-Pass-2026!', User::sole()->password));
    }

    public function test_refuses_to_run_in_production(): void
    {
        $this->writeAccount('Secret-Pass-2026!');
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectExceptionObject(new RuntimeException('Les comptes de démonstration ne doivent jamais être créés en production.'));

        try {
            (new DemoUserSeeder($this->credentialsFile))->run(app(GeocodeCities::class));
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_rejects_an_account_without_password(): void
    {
        file_put_contents($this->credentialsFile, <<<'ENV'
            DEMO_USERS_COUNT=1
            DEMO_USER_1_FIRSTNAME="Léa"
            DEMO_USER_1_LASTNAME="Martin"
            DEMO_USER_1_EMAIL="lea.martin@weather-api.test"
            ENV);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DEMO_USER_1_PASSWORD est manquant');

        (new DemoUserSeeder($this->credentialsFile))->run(app(GeocodeCities::class));
    }

    public function test_fails_when_the_credentials_file_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fichier des comptes de démonstration introuvable');

        (new DemoUserSeeder('/nonexistent/.env.demo-users'))->run(app(GeocodeCities::class));
    }

    /**
     * Write a single demo account without saved cities to the credentials file.
     */
    protected function writeAccount(string $password): void
    {
        file_put_contents($this->credentialsFile, <<<ENV
            DEMO_USERS_COUNT=1
            DEMO_USER_1_FIRSTNAME="Léa"
            DEMO_USER_1_LASTNAME="Martin"
            DEMO_USER_1_EMAIL="lea.martin@weather-api.test"
            DEMO_USER_1_PASSWORD="{$password}"
            ENV);
    }
}
