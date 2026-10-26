import { Head } from '@inertiajs/react';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { weather } from '@/routes';
import type { ForecastDay, HourlyForecast, WeatherLocation } from '@/types';

const dayFormatter = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const temperatureFormatter = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 1,
});

function formatDay(date: string): string {
    return dayFormatter.format(new Date(`${date}T00:00:00`));
}

function ForecastDayCard({ day, unit }: { day: ForecastDay; unit: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="capitalize">
                    {formatDay(day.date)}
                </CardTitle>
                <CardDescription>
                    Min {temperatureFormatter.format(day.min)}
                    {unit} · Max {temperatureFormatter.format(day.max)}
                    {unit}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-8">
                    {day.hours.map((hour) => (
                        <li
                            key={hour.time}
                            className="flex flex-col items-center rounded-md border border-sidebar-border/70 px-2 py-1.5 text-sm dark:border-sidebar-border"
                        >
                            <span className="text-xs text-muted-foreground">
                                {hour.time}
                            </span>
                            <span className="font-medium tabular-nums">
                                {temperatureFormatter.format(hour.temperature)}°
                            </span>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

export default function Weather({
    location,
    forecast,
}: {
    location: WeatherLocation;
    forecast: HourlyForecast | null;
}) {
    return (
        <>
            <Head title="Météo" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={`Météo à ${location.name}`}
                    description="Températures heure par heure — modèle Météo-France"
                />

                {forecast === null ? (
                    <AlertError
                        title="Prévisions indisponibles"
                        errors={[
                            'Le service Open-Meteo ne répond pas pour le moment. Réessayez dans quelques minutes.',
                        ]}
                    />
                ) : (
                    <div className="grid gap-4">
                        {forecast.days.map((day) => (
                            <ForecastDayCard
                                key={day.date}
                                day={day}
                                unit={forecast.unit}
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
