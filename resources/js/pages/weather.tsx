import { Form, Head } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { weather } from '@/routes';
import type {
    ForecastDay,
    ForecastUnits,
    HourlyForecast,
    WeatherLocation,
} from '@/types';

const dayFormatter = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const numberFormatter = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 1,
});

function formatDay(date: string): string {
    return dayFormatter.format(new Date(`${date}T00:00:00`));
}

function formatValue(value: number | null, unit: string): string {
    return value === null ? '—' : `${numberFormatter.format(value)} ${unit}`;
}

function LocationForm({ location }: { location: WeatherLocation }) {
    return (
        <Form
            {...weather.form()}
            options={{ preserveScroll: true }}
            className="flex flex-wrap items-end gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="latitude">Latitude</Label>
                        <Input
                            id="latitude"
                            name="latitude"
                            type="number"
                            step="any"
                            min={-90}
                            max={90}
                            required
                            defaultValue={location.latitude}
                            className="w-40"
                        />
                        <InputError message={errors.latitude} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="longitude">Longitude</Label>
                        <Input
                            id="longitude"
                            name="longitude"
                            type="number"
                            step="any"
                            min={-180}
                            max={180}
                            required
                            defaultValue={location.longitude}
                            className="w-40"
                        />
                        <InputError message={errors.longitude} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        Afficher les prévisions
                    </Button>
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
        <Card className="gap-0 py-0">
            <details open={defaultOpen} className="group">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 [&::-webkit-details-marker]:hidden">
                    <div className="space-y-1">
                        <div className="font-semibold capitalize">
                            {formatDay(day.date)}
                        </div>
                        <div className="text-sm text-muted-foreground">
                            Min {formatValue(day.min, units.temperature)} · Max{' '}
                            {formatValue(day.max, units.temperature)} ·
                            Précipitations{' '}
                            {formatValue(
                                day.precipitation,
                                units.precipitation,
                            )}
                            {day.snowfall > 0 &&
                                ` · Neige ${formatValue(day.snowfall, units.snowfall)}`}
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
                                    <td className="py-1.5 pr-4 font-medium">
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
}: {
    location: WeatherLocation;
    forecast: HourlyForecast | null;
}) {
    const place =
        location.name ??
        `${numberFormatter.format(location.latitude)}, ${numberFormatter.format(location.longitude)}`;

    return (
        <>
            <Head title="Météo" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={`Météo à ${place}`}
                    description="Prévisions heure par heure — modèle Météo-France"
                />

                <LocationForm location={location} />

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
