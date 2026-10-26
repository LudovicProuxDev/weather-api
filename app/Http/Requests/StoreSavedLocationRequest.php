<?php

namespace App\Http\Requests;

use App\Models\SavedLocation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSavedLocationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'city_id' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('saved_locations', 'city_id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'city_id.unique' => 'Cette ville est déjà dans vos localisations.',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user()->savedLocations()->count() >= SavedLocation::MAX_PER_USER) {
                    $validator->errors()->add(
                        'city_id',
                        sprintf('Vous pouvez enregistrer au maximum %d localisations.', SavedLocation::MAX_PER_USER),
                    );
                }
            },
        ];
    }
}
