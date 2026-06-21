<?php

namespace App\Http\Requests\Memory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'note' => ['nullable', 'string'],
            'date_time' => ['nullable', 'date'],
            'dateTime' => ['nullable', 'date'],
            'place' => ['nullable', 'string', 'max:150'],
            'is_favorite' => ['nullable', 'boolean'],
            'memory_date' => ['nullable', 'date'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
