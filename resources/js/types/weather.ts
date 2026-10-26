export type WeatherLocation = {
    name: string;
    latitude: number;
    longitude: number;
};

export type HourlyTemperature = {
    time: string;
    temperature: number;
};

export type ForecastDay = {
    date: string;
    min: number;
    max: number;
    hours: HourlyTemperature[];
};

export type HourlyForecast = {
    timezone: string;
    unit: string;
    days: ForecastDay[];
};
