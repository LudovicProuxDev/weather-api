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
            'search' => ['nullable', 'string', 'min:2', 'max:100'],
            'city' => ['nullable', 'integer', 'min:1', 'prohibits:latitude,longitude'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Get the searched city name, if any.
     */
    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->squish()->toString() : null;
    }

    /**
     * Get the selected Open-Meteo city identifier, if any.
     */
    public function cityId(): ?int
    {
        return $this->filled('city') ? $this->integer('city') : null;
    }

    /**
     * Determine if explicit coordinates were requested.
     */
    public function hasCoordinates(): bool
    {
        return $this->filled(['latitude', 'longitude']);
    }

    /**
     * Get the requested latitude, rounded to about 11 meters.
     */
    public function latitude(): float
    {
        return round($this->float('latitude'), 4);
    }

    /**
     * Get the requested longitude, rounded to about 11 meters.
     */
    public function longitude(): float
    {
        return round($this->float('longitude'), 4);
    }
}
