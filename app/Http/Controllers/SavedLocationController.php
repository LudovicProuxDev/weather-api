<?php

namespace App\Http\Controllers;

use App\Actions\Weather\GeocodeCities;
use App\Http\Requests\StoreSavedLocationRequest;
use App\Models\SavedLocation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SavedLocationController extends Controller
{
    /**
     * Save a city to the user's locations.
     *
     * The city details come from Open-Meteo, never from the request, so users cannot store forged data.
     */
    public function store(StoreSavedLocationRequest $request, GeocodeCities $geocodeCities): RedirectResponse
    {
        try {
            $city = $geocodeCities->find($request->integer('city_id'));
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'city_id' => 'La recherche de villes est indisponible pour le moment. Réessayez dans quelques minutes.',
            ]);
        }

        if ($city === null) {
            throw ValidationException::withMessages(['city_id' => 'Cette ville est introuvable.']);
        }

        $request->user()->savedLocations()->createOrFirst(
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

        return to_route('weather', ['city' => $city['id']]);
    }

    /**
     * Remove a location from the user's saved locations.
     */
    public function destroy(SavedLocation $savedLocation): RedirectResponse
    {
        Gate::authorize('delete', $savedLocation);

        $savedLocation->delete();

        return back();
    }
}
