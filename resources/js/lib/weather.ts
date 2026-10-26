import type { WeatherLocation } from '@/types';

export const dayFormatter = new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

export const numberFormatter = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 1,
});

export function formatDay(date: string): string {
    return dayFormatter.format(new Date(`${date}T00:00:00`));
}

export function formatValue(value: number | null, unit: string): string {
    return value === null ? '—' : `${numberFormatter.format(value)} ${unit}`;
}

export function temperatureClass(temperature: number): string {
    if (temperature <= 0) {
        return 'text-sky-600 dark:text-sky-300';
    }

    if (temperature < 10) {
        return 'text-cyan-600 dark:text-cyan-300';
    }

    if (temperature < 20) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (temperature < 28) {
        return 'text-orange-500 dark:text-orange-400';
    }

    return 'text-red-600 dark:text-red-400';
}

export function describeLocation(
    location: Pick<WeatherLocation, 'department' | 'region' | 'country'>,
): string {
    return [location.department, location.region, location.country]
        .filter(Boolean)
        .join(', ');
}
