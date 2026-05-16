<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array<string, mixed>.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profile' => [
                'avatar' => $this->profile?->avatar ? asset('storage/' . $this->profile->avatar) : null,
                'bio' => $this->profile?->bio,
                'home_city' => $this->profile?->home_city,
                'home_country' => $this->profile?->home_country,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}