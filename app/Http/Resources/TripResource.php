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
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'cover_image' => $this->cover_photo ? asset('storage/'.$this->cover_photo) : null,
            'category' => $this->category,
            'is_favorite' => (bool) $this->is_favorite,
            'status' => $this->status,
            'movements' => MemoryResource::collection($this->whenLoaded('memories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
