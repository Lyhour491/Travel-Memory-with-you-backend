<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'bio' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:150'],
            'home_city' => ['nullable', 'string', 'max:150'],
            'home_country' => ['nullable', 'string', 'max:150'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
