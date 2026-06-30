<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memory\StoreMemoryRequest;
use App\Http\Requests\Memory\UpdateMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tripId = $request->route('trip') ?: $request->integer('trip_id') ?: null;

        $memories = Memory::query()
            ->ownedBy($user->id)
            ->with('photos')
            ->published()
            ->when($tripId, function ($query) use ($user, $tripId) {
                Trip::query()->ownedBy($user->id)->findOrFail($tripId);

                $query->where('trip_id', $tripId);
            })
            ->when($request->has('favorite'), function ($query) use ($request) {
                $query->where('is_favorite', $request->boolean('favorite'));
            })
            ->orderByDesc('memory_date')
            ->orderByDesc('date_time')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Movements fetched successfully.',
            'data' => [
                'trip_id' => $tripId,
                'movements' => MemoryResource::collection($memories),
            ],
        ]);
    }

    public function store(StoreMemoryRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tripId = $request->route('trip') ?: $request->integer('trip_id');

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($tripId);

        $memory = $trip->memories()->create([
            'user_id' => $user->id,
            'title' => $request->string('title')->toString(),
            'note' => $request->input('note'),
            'date_time' => $request->input('date_time', $request->input('dateTime')),
            'place' => $request->input('place'),
            'memory_date' => $request->input('memory_date'),
            'location_name' => $request->input('location_name', $request->input('place')),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'address' => $request->input('address'),
            'is_favorite' => $request->boolean('is_favorite'),
            'status' => 'published',
        ]);

        foreach ($this->uploadedMemoryImages($request) as $index => $file) {
            MemoryPhoto::query()->create([
                'memory_id' => $memory->id,
                'photo_path' => $file->store('memory_photos', 'public'),
                'photo_order' => $index,
            ]);
        }

        $memoryResource = new MemoryResource($memory->load('photos'));

        return response()->json([
            'success' => true,
            'message' => 'Movement created successfully.',
            'data' => [
                'memory' => $memoryResource,
                'movement' => $memoryResource,
            ],
        ], 201);
    }

    public function show(Request $request, int $memory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->with('photos')
            ->findOrFail($memory);

        $memoryResource = new MemoryResource($memory->load('photos'));

        return response()->json([
            'success' => true,
            'message' => 'Movement fetched successfully.',
            'data' => [
                'memory' => $memoryResource,
                'movement' => $memoryResource,
            ],
        ]);
    }

    public function update(UpdateMemoryRequest $request, int $memory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->with('photos')
            ->findOrFail($memory);

        $memory->update([
            'title' => $request->has('title') ? $request->string('title')->toString() : $memory->title,
            'note' => $request->has('note') ? $request->input('note') : $memory->note,
            'date_time' => ($request->has('date_time') || $request->has('dateTime')) ? $request->input('date_time', $request->input('dateTime')) : $memory->date_time,
            'place' => $request->has('place') ? $request->input('place') : $memory->place,
            'memory_date' => $request->has('memory_date') ? $request->input('memory_date') : $memory->memory_date,
            'location_name' => $request->has('location_name') ? $request->input('location_name') : $memory->location_name,
            'latitude' => $request->has('latitude') ? $request->input('latitude') : $memory->latitude,
            'longitude' => $request->has('longitude') ? $request->input('longitude') : $memory->longitude,
            'address' => $request->has('address') ? $request->input('address') : $memory->address,
            'is_favorite' => $request->has('is_favorite') ? $request->boolean('is_favorite') : $memory->is_favorite,
        ]);

        $memoryResource = new MemoryResource($memory->fresh()->load('photos'));

        return response()->json([
            'success' => true,
            'message' => 'Movement updated successfully.',
            'data' => [
                'memory' => $memoryResource,
                'movement' => $memoryResource,
            ],
        ]);
    }

    public function destroy(Request $request, int $memory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->with('photos')
            ->findOrFail($memory);

        foreach ($memory->photos as $photo) {
            Storage::disk('public')->delete($photo->photo_path);
        }

        $memory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Movement deleted successfully.',
            'data' => null,
        ]);
    }

    public function toggleFavorite(Request $request, int $memory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->with('photos')
            ->findOrFail($memory);

        $memory->update(['is_favorite' => ! $memory->is_favorite]);

        $memoryResource = new MemoryResource($memory->fresh()->load('photos'));

        return response()->json([
            'success' => true,
            'message' => 'Movement favorite status updated successfully.',
            'data' => [
                'memory' => $memoryResource,
                'movement' => $memoryResource,
            ],
        ]);
    }

    private function uploadedMemoryImages(Request $request): array
    {
        return array_values(array_merge(
            $request->file('images', []),
            $request->file('photos', []),
        ));
    }
}
