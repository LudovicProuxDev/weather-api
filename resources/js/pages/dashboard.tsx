import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { Droplets, MapPin, Search, Snowflake } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import {
    describeLocation,
    formatValue,
    numberFormatter,
    temperatureClass,
} from '@/lib/weather';
import { dashboard, weather } from '@/routes';
import type { SavedLocation, TodayForecast } from '@/types';

const todayFormatter = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

function greeting(): string {
    const hour = new Date().getHours();

    return hour >= 18 || hour < 5 ? 'Bonsoir' : 'Bonjour';
}

function TodaySummary({
    forecast,
}: {
    forecast: TodayForecast | null | undefined;
}) {
    if (!forecast) {
        return (
            <p className="text-sm text-muted-foreground">
                Prévisions indisponibles pour le moment.
            </p>
        );
    }

    const { units } = forecast;

    return (
        <div className="space-y-2">
            {forecast.current !== null && (
                <p
                    className={cn(
                        'text-4xl font-semibold tabular-nums',
                        temperatureClass(forecast.current),
                    )}
                >
                    {numberFormatter.format(forecast.current)}°
                </p>
            )}
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                <span>
                    <span
                        className={cn(
                            'font-medium',
                            temperatureClass(forecast.min),
                        )}
                    >
                        {formatValue(forecast.min, units.temperature)}
                    </span>
                    {' / '}
                    <span
                        className={cn(
                            'font-medium',
                            temperatureClass(forecast.max),
                        )}
                    >
                        {formatValue(forecast.max, units.temperature)}
                    </span>
                </span>
                <span className="flex items-center gap-1">
                    <Droplets className="size-3.5 text-sky-500" />
                    {formatValue(forecast.precipitation, units.precipitation)}
                </span>
                {forecast.snowfall > 0 && (
                    <span className="flex items-center gap-1">
                        <Snowflake className="size-3.5 text-sky-400" />
                        {formatValue(forecast.snowfall, units.snowfall)}
                    </span>
                )}
            </div>
        </div>
    );
}

function SummarySkeleton() {
    return (
        <div className="space-y-2">
            <Skeleton className="h-10 w-20" />
            <Skeleton className="h-4 w-40" />
        </div>
    );
}

export default function Dashboard({
    savedLocations,
    todayForecasts,
}: {
    savedLocations: SavedLocation[];
    todayForecasts?: Record<number, TodayForecast | null>;
}) {
    const { auth } = usePage().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <>
            <Head title="Accueil" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <section className="weather-hero flex flex-wrap items-center justify-between gap-4 rounded-xl border p-6">
                    <div className="flex items-center gap-4">
                        <AppLogoIcon className="sun-spin size-12 shrink-0 text-amber-500" />
                        <div className="space-y-1">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {greeting()} {firstName} !
                            </h1>
                            <p className="text-muted-foreground first-letter:uppercase">
                                {todayFormatter.format(new Date())} · la météo
                                du jour dans vos villes
                            </p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={weather()}>
                            <Search />
                            Rechercher une ville
                        </Link>
                    </Button>
                </section>

                {savedLocations.length === 0 ? (
                    <section className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                        <MapPin className="size-8 text-primary" />
                        <h2 className="text-lg font-medium">
                            Aucune ville enregistrée
                        </h2>
                        <p className="max-w-md text-sm text-muted-foreground">
                            Recherchez une ville sur la page Météo puis cliquez
                            sur « Enregistrer cette ville » : elle apparaîtra
                            ici avec la météo du jour.
                        </p>
                        <Button asChild variant="outline">
                            <Link href={weather()}>Aller à la page Météo</Link>
                        </Button>
                    </section>
                ) : (
                    <section className="space-y-3">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            Mes localisations
                        </h2>
                        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {savedLocations.map((savedLocation) => (
                                <li key={savedLocation.id}>
                                    <Link
                                        href={weather({
                                            query: {
                                                city: savedLocation.cityId,
                                            },
                                        })}
                                        className="weather-card flex h-full flex-col gap-4 rounded-xl border bg-card p-5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <div>
                                            <p className="flex items-center gap-1.5 font-semibold">
                                                <MapPin className="size-4 text-primary" />
                                                {savedLocation.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {describeLocation(
                                                    savedLocation,
                                                )}
                                            </p>
                                        </div>
                                        <Deferred
                                            data="todayForecasts"
                                            fallback={<SummarySkeleton />}
                                        >
                                            <TodaySummary
                                                forecast={
                                                    todayForecasts?.[
                                                        savedLocation.id
                                                    ]
                                                }
                                            />
                                        </Deferred>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Accueil',
            href: dashboard(),
        },
    ],
};
