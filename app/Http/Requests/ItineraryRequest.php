<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ItineraryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for the traveller's itinerary.
     */
    public function rules(): array
    {
        return [
            'destination'   => 'required|string|max:255',
            'start_date'    => 'required|date|date_format:Y-m-d',
            'end_date'      => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'activities'    => 'required|array|min:1',
            'activities.*'  => 'required|string|max:500',
        ];
    }

    /**
     * Custom error messages for better clarity.
     */
    public function messages(): array
    {
        return [
            'destination.required'      => 'The destination field is required.',
            'start_date.required'       => 'The start date is required.',
            'start_date.date_format'    => 'The start date must be in Y-m-d format (e.g. 2026-07-15),',
            'end_date.after_or_equal'   => 'The end date must be after or equal to the start date.',
            'activities.required'       => 'At least one activity is required.',
            'activities.min'            => 'The itinerary must include at least one activity.',
        ];
    }

    /**
     * Return JSON errors instead of redirecting (this is an API, not a web page).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
