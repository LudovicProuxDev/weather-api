export type City = {
    id: number;
    name: string;
    region: string | null;
    department: string | null;
    country: string | null;
    latitude: number;
    longitude: number;
};

export type WeatherLocation = Omit<City, 'id' | 'name'> & {
    id: number | null;
    name: string | null;
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

export type SavedLocation = {
    id: number;
    cityId: number;
    name: string;
    region: string | null;
    department: string | null;
    country: string | null;
};

export type TodayForecast = {
    current: number | null;
    min: number;
    max: number;
    precipitation: number;
    snowfall: number;
    units: ForecastUnits;
};
