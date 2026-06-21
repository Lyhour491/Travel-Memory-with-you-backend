<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemoryResource extends JsonResource
{
    /**
     * Transform the resource into an array<string, mixed>.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'title' => $this->title,
            'note' => $this->note,
            'place' => $this->place ?? $this->location_name,
            'date_time' => $this->date_time?->toISOString() ?? $this->memory_date?->toISOString(),
            'location_name' => $this->location_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'is_favorite' => (bool) $this->is_favorite,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'photos' => MemoryPhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
