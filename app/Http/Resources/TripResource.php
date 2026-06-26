<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    /**
     * Transform the resource into an array<string, mixed>.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location ?? '',
            'cover_image' => $this->cover_photo ? asset('storage/'.$this->cover_photo) : null,
            'category' => $this->category,
            'is_favorite' => (bool) $this->is_favorite,
            'movement_count' => $this->memories_count ?? $this->memories()->count(),
            'image_count' => $this->memories()
                ->withCount('photos')
                ->get()
                ->sum('photos_count'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
