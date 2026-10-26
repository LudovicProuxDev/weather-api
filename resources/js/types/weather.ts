export type WeatherLocation = {
    name: string | null;
    latitude: number;
    longitude: number;
};

export type ForecastHour = {
    time: string;
    temperature: number;
    apparentTemperature: number | null;
    humidity: number | null;
    dewPoint: number | null;
    precipitation: number | null;
    rain: number | null;
    snowfall: number | null;
};

export type ForecastDay = {
    date: string;
    min: number;
    max: number;
    precipitation: number;
    rain: number;
    snowfall: number;
    hours: ForecastHour[];
};

export type ForecastUnits = {
    temperature: string;
    humidity: string;
    precipitation: string;
    snowfall: string;
};

export type HourlyForecast = {
    timezone: string;
    units: ForecastUnits;
    days: ForecastDay[];
};
