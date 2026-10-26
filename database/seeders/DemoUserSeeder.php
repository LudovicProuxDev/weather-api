<?php

namespace Database\Seeders;

use App\Actions\Weather\GeocodeCities;
use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Create the demo accounts described in the git-ignored .env.demo-users file.
 *
 * Credentials never live in the repository: the file holds the names, logins
 * and passwords, and this seeder only reads it.
 */
class DemoUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The file that describes the demo accounts.
     */
    protected string $credentialsFile;

    /**
     * Create a new seeder instance.
     */
    public function __construct(?string $credentialsFile = null)
    {
        $this->credentialsFile = $credentialsFile ?? base_path('.env.demo-users');
    }

    /**
     * Create or update the demo users and their saved locations.
     */
    public function run(GeocodeCities $geocodeCities): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Les comptes de démonstration ne doivent jamais être créés en production.');
        }

        if (! is_file($this->credentialsFile)) {
            throw new RuntimeException("Fichier des comptes de démonstration introuvable : {$this->credentialsFile}");
        }

        $values = Dotenv::parse((string) file_get_contents($this->credentialsFile));
        $count = (int) ($values['DEMO_USERS_COUNT'] ?? 0);

        for ($index = 1; $index <= $count; $index++) {
            $account = $this->account($values, $index);

            $user = User::updateOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => $account['password']],
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            foreach ($account['cities'] as $cityId) {
                $city = $geocodeCities->find($cityId);

                if ($city === null) {
                    $this->command?->warn("Ville {$cityId} introuvable pour {$account['email']}, ignorée.");

                    continue;
                }

                $user->savedLocations()->updateOrCreate(
                    ['city_id' => $city['id']],
                    [
                        'name' => $city['name'],
                        'region' => $city['region'],
                        'department' => $city['department'],
                        'country' => $city['country'],
                        'latitude' => $city['latitude'],
                        'longitude' => $city['longitude'],
                    ],
                );
            }

            $this->command?->info("Compte prêt : {$account['name']} <{$account['email']}>");
        }
    }

    /**
     * Read and validate one demo account from the credentials file.
     *
     * @param  array<string, string|null>  $values
     * @return array{name: string, email: string, password: string, cities: list<int>}
     */
    protected function account(array $values, int $index): array
    {
        $prefix = "DEMO_USER_{$index}_";

        foreach (['FIRSTNAME', 'LASTNAME', 'EMAIL', 'PASSWORD'] as $field) {
            if (($values[$prefix.$field] ?? '') === '') {
                throw new RuntimeException("{$prefix}{$field} est manquant dans {$this->credentialsFile}.");
            }
        }

        $email = (string) $values[$prefix.'EMAIL'];

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException("{$prefix}EMAIL n'est pas une adresse e-mail valide.");
        }

        $cities = array_filter(
            array_map('trim', explode(',', (string) ($values[$prefix.'CITIES'] ?? ''))),
            fn (string $cityId): bool => ctype_digit($cityId),
        );

        return [
            'name' => $values[$prefix.'FIRSTNAME'].' '.$values[$prefix.'LASTNAME'],
            'email' => $email,
            'password' => (string) $values[$prefix.'PASSWORD'],
            'cities' => array_values(array_map('intval', $cities)),
        ];
    }
}
