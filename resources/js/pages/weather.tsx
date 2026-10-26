import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    Droplets,
    MapPin,
    Search,
    Snowflake,
    Star,
    StarOff,
    Thermometer,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/SavedLocationController';
import AlertError from '@/components/alert-error';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
    describeLocation,
    formatDay,
    formatValue,
    numberFormatter,
    temperatureClass,
} from '@/lib/weather';
import { weather } from '@/routes';
import type {
    City,
    ForecastDay,
    ForecastUnits,
    HourlyForecast,
    SavedLocation,
    WeatherLocation,
} from '@/types';

function currentLocationQuery(
    location: WeatherLocation | null,
): Record<string, number> {
    if (location?.id) {
        return { city: location.id };
    }

    if (location) {
        return { latitude: location.latitude, longitude: location.longitude };
    }

    return {};
}

function CitySearch({
    search,
    cities,
    location,
    error,
}: {
    search: string | null;
    cities: City[] | null;
    location: WeatherLocation | null;
    error?: string;
}) {
    const [query, setQuery] = useState(search ?? '');

    useEffect(() => {
        const term = query.trim();
        const searchable = term.length >= 2 ? term : '';

        if (searchable === (search ?? '')) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                weather.url(),
                {
                    ...currentLocationQuery(location),
                    ...(searchable ? { search: searchable } : {}),
                },
                {
                    only: ['search', 'cities'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [query, search, location]);

    return (
        <div className="grid max-w-xl gap-2">
            <Label htmlFor="city-search">Rechercher une ville</Label>
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id="city-search"
                    type="search"
                    autoComplete="off"
                    placeholder="Marseille, Lyon, Brest…"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    maxLength={100}
                    className="pl-9"
                />
            </div>
            <InputError message={error} />

            {search !== null && cities === null && (
                <p className="text-sm text-muted-foreground">
                    La recherche de villes est indisponible pour le moment.
                </p>
            )}

            {search !== null && cities?.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Aucune ville trouvée pour « {search} ».
                </p>
            )}

            {cities !== null && cities.length > 0 && (
                <ul className="divide-y overflow-hidden rounded-md border">
                    {cities.map((city) => (
                        <li key={city.id}>
                            <Link
                                href={weather({ query: { city: city.id } })}
                                className="flex items-start gap-3 px-3 py-2 hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                            >
                                <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <span className="grid">
                                    <span className="font-medium">
                                        {city.name}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {describeLocation(city)}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function SavedLocationList({
    savedLocations,
    currentCityId,
}: {
    savedLocations: SavedLocation[];
    currentCityId: number | null;
}) {
    return (
        <section className="grid gap-2">
            <h3 className="text-sm font-medium">Mes localisations</h3>

            {savedLocations.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Aucune localisation enregistrée. Recherchez une ville puis
                    cliquez sur « Enregistrer cette ville ».
                </p>
            ) : (
                <ul className="flex flex-wrap gap-2">
                    {savedLocations.map((savedLocation) => (
                        <li
                            key={savedLocation.id}
                            className={cn(
                                'flex items-center rounded-full border text-sm',
                                savedLocation.cityId === currentCityId &&
                                    'border-primary bg-accent',
                            )}
                        >
                            <Link
                                href={weather({
                                    query: { city: savedLocation.cityId },
                                })}
                                title={describeLocation(savedLocation)}
                                className="flex items-center gap-1.5 py-1 pr-1 pl-3"
                            >
                                <MapPin className="size-3.5 text-muted-foreground" />
                                {savedLocation.name}
                            </Link>
                            <Form
                                {...destroy.form(savedLocation.id)}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        aria-label={`Retirer ${savedLocation.name} de mes localisations`}
                                        className="mr-1 rounded-full p-1 text-muted-foreground hover:bg-background hover:text-foreground disabled:opacity-50"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                            </Form>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function SaveLocationButton({
    cityId,
    savedLocation,
    error,
}: {
    cityId: number;
    savedLocation: SavedLocation | undefined;
    error?: string;
}) {
    if (savedLocation) {
        return (
            <Form
                {...destroy.form(savedLocation.id)}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        disabled={processing}
                    >
                        <StarOff />
                        Retirer de mes localisations
                    </Button>
                )}
            </Form>
        );
    }

    return (
        <Form
            {...store.form()}
            options={{ preserveScroll: true }}
            className="grid justify-items-start gap-1"
        >
            {({ processing }) => (
                <>
                    <input type="hidden" name="city_id" value={cityId} />
                    <Button type="submit" size="sm" disabled={processing}>
                        <Star />
                        Enregistrer cette ville
                    </Button>
                    <InputError message={error} />
                </>
            )}
        </Form>
    );
}

function ForecastDayCard({
    day,
    units,
    defaultOpen,
}: {
    day: ForecastDay;
    units: ForecastUnits;
    defaultOpen: boolean;
}) {
    return (
        <Card className="weather-card gap-0 py-0">
            <details open={defaultOpen} className="group">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 [&::-webkit-details-marker]:hidden">
                    <div className="space-y-1">
                        <div className="font-semibold capitalize">
                            {formatDay(day.date)}
                        </div>
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span className="flex items-center gap-1">
                                <Thermometer className="size-3.5" />
                                <span
                                    className={cn(
                                        'font-medium',
                                        temperatureClass(day.min),
                                    )}
                                >
                                    {formatValue(day.min, units.temperature)}
                                </span>
                                /
                                <span
                                    className={cn(
                                        'font-medium',
                                        temperatureClass(day.max),
                                    )}
                                >
                                    {formatValue(day.max, units.temperature)}
                                </span>
                            </span>
                            <span className="flex items-center gap-1">
                                <Droplets className="size-3.5 text-sky-500" />
                                {formatValue(
                                    day.precipitation,
                                    units.precipitation,
                                )}
                            </span>
                            {day.snowfall > 0 && (
                                <span className="flex items-center gap-1">
                                    <Snowflake className="size-3.5 text-sky-400" />
                                    {formatValue(day.snowfall, units.snowfall)}
                                </span>
                            )}
                        </div>
                    </div>
                    <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                </summary>

                <div className="overflow-x-auto px-6 pb-4">
                    <table className="w-full text-sm whitespace-nowrap tabular-nums">
                        <thead className="text-left text-xs text-muted-foreground">
                            <tr className="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                <th className="py-2 pr-4 font-medium">Heure</th>
                                <th className="py-2 pr-4 font-medium">
                                    Température
                                </th>
                                <th className="py-2 pr-4 font-medium">
                                    Ressentie
                                </th>
                                <th className="py-2 pr-4 font-medium">
                                    Humidité
                                </th>
                                <th className="py-2 pr-4 font-medium">
                                    Point de rosée
                                </th>
                                <th className="py-2 pr-4 font-medium">
                                    Précipitations
                                </th>
                                <th className="py-2 pr-4 font-medium">Pluie</th>
                                <th className="py-2 font-medium">Neige</th>
                            </tr>
                        </thead>
                        <tbody>
                            {day.hours.map((hour) => (
                                <tr
                                    key={hour.time}
                                    className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/60"
                                >
                                    <td className="py-1.5 pr-4 text-muted-foreground">
                                        {hour.time}
                                    </td>
                                    <td
                                        className={cn(
                                            'py-1.5 pr-4 font-medium',
                                            temperatureClass(hour.temperature),
                                        )}
                                    >
                                        {formatValue(
                                            hour.temperature,
                                            units.temperature,
                                        )}
                                    </td>
                                    <td className="py-1.5 pr-4">
                                        {formatValue(
                                            hour.apparentTemperature,
                                            units.temperature,
                                        )}
                                    </td>
                                    <td className="py-1.5 pr-4">
                                        {formatValue(
                                            hour.humidity,
                                            units.humidity,
                                        )}
                                    </td>
                                    <td className="py-1.5 pr-4">
                                        {formatValue(
                                            hour.dewPoint,
                                            units.temperature,
                                        )}
                                    </td>
                                    <td className="py-1.5 pr-4">
                                        {formatValue(
                                            hour.precipitation,
                                            units.precipitation,
                                        )}
                                    </td>
                                    <td className="py-1.5 pr-4">
                                        {formatValue(
                                            hour.rain,
                                            units.precipitation,
                                        )}
                                    </td>
                                    <td className="py-1.5">
                                        {formatValue(
                                            hour.snowfall,
                                            units.snowfall,
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </Card>
    );
}

export default function Weather({
    location,
    forecast,
    search,
    cities,
    savedLocations,
    errors,
}: {
    location: WeatherLocation | null;
    forecast: HourlyForecast | null;
    search: string | null;
    cities: City[] | null;
    savedLocations: SavedLocation[];
    errors: Partial<Record<'search' | 'city' | 'city_id', string>>;
}) {
    const currentCityId = location?.id ?? null;
    const currentSavedLocation = savedLocations.find(
        (savedLocation) => savedLocation.cityId === currentCityId,
    );

    const place =
        location === null
            ? null
            : (location.name ??
              `${numberFormatter.format(location.latitude)}, ${numberFormatter.format(location.longitude)}`);

    return (
        <>
            <Head title={place ? `Météo à ${place}` : 'Météo'} />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <CitySearch
                    search={search}
                    cities={cities}
                    location={location}
                    error={errors.search ?? errors.city}
                />

                <SavedLocationList
                    savedLocations={savedLocations}
                    currentCityId={currentCityId}
                />

                {place !== null && location !== null && (
                    <section className="weather-hero flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5">
                        <div className="flex items-center gap-4">
                            <AppLogoIcon className="sun-spin size-10 shrink-0 text-amber-500" />
                            <div className="space-y-0.5">
                                <h2 className="text-xl font-semibold tracking-tight">
                                    Météo à {place}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {[
                                        describeLocation(location),
                                        'Prévisions heure par heure — modèle Météo-France',
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </div>
                        </div>
                        {currentCityId !== null && (
                            <SaveLocationButton
                                cityId={currentCityId}
                                savedLocation={currentSavedLocation}
                                error={errors.city_id}
                            />
                        )}
                    </section>
                )}

                {forecast === null ? (
                    <AlertError
                        title="Prévisions indisponibles"
                        errors={[
                            'Le service Open-Meteo ne répond pas pour le moment. Réessayez dans quelques minutes.',
                        ]}
                    />
                ) : (
                    <div className="grid gap-4">
                        {forecast.days.map((day, index) => (
                            <ForecastDayCard
                                key={day.date}
                                day={day}
                                units={forecast.units}
                                defaultOpen={index === 0}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Weather.layout = {
    breadcrumbs: [
        {
            title: 'Météo',
            href: weather(),
        },
    ],
};
