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
        $location = $this->place ?? $this->location_name ?? $this->address;
        $date = $this->date_time ?? $this->memory_date;
        $firstPhoto = $this->relationLoaded('photos') ? $this->photos->first() : null;

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'status' => $this->status,
            'is_draft' => $this->status === 'draft',
            'title' => $this->title,
            'description' => $this->note,
            'location' => $location,
            'note' => $this->note,
            'place' => $location,
            'date' => $date?->format('Y-m-d'),
            'date_time' => $date?->toISOString(),
            'location_name' => $this->location_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'image_url' => $firstPhoto?->photo_path ? asset('storage/'.$firstPhoto->photo_path) : null,
            'is_favorite' => (bool) $this->is_favorite,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'photos' => MemoryPhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
