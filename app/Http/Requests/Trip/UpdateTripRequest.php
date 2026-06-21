<?php

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_favorite' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'required', Rule::in(['planned', 'ongoing', 'completed'])],
        ];
    }
}
