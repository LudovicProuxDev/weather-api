<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowWeatherForecastRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Get the requested latitude, rounded to about 11 meters, or the default location's.
     */
    public function latitude(): float
    {
        return round($this->float('latitude', config('services.open_meteo.default_location.latitude')), 4);
    }

    /**
     * Get the requested longitude, rounded to about 11 meters, or the default location's.
     */
    public function longitude(): float
    {
        return round($this->float('longitude', config('services.open_meteo.default_location.longitude')), 4);
    }
}
