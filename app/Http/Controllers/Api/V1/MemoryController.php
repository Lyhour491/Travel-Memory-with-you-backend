<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Memory\StoreMemoryRequest;
use App\Http\Requests\Memory\UpdateMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Models\Memory;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function index(Request $request, int $tripId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($tripId);

        $memories = Memory::query()
            ->where('trip_id', $trip->id)
            ->ownedBy($user->id)
            ->with('photos')
            ->orderByDesc('memory_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Memories fetched successfully.',
            'data' => [
                'trip_id' => $trip->id,
                'memories' => MemoryResource::collection($memories),
            ],
        ]);
    }

    public function store(StoreMemoryRequest $request, int $tripId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($tripId);

        $memory = $trip->memories()->create([
            'user_id' => $user->id,
            'title' => $request->string('title')->toString(),
            'note' => $request->input('note'),
            'memory_date' => $request->input('memory_date'),
            'location_name' => $request->input('location_name'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'address' => $request->input('address'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Memory created successfully.',
            'data' => [
                'memory' => new MemoryResource($memory->load('photos')),
            ],
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->with('photos')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Memory fetched successfully.',
            'data' => [
                'memory' => new MemoryResource($memory->load('photos')),
            ],
        ]);
    }

    public function update(UpdateMemoryRequest $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->with('photos')
            ->findOrFail($id);

        $memory->update([
            'title' => $request->has('title') ? $request->string('title')->toString() : $memory->title,
            'note' => $request->has('note') ? $request->input('note') : $memory->note,
            'memory_date' => $request->has('memory_date') ? $request->input('memory_date') : $memory->memory_date,
            'location_name' => $request->has('location_name') ? $request->input('location_name') : $memory->location_name,
            'latitude' => $request->has('latitude') ? $request->input('latitude') : $memory->latitude,
            'longitude' => $request->has('longitude') ? $request->input('longitude') : $memory->longitude,
            'address' => $request->has('address') ? $request->input('address') : $memory->address,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Memory updated successfully.',
            'data' => [
                'memory' => new MemoryResource($memory->fresh()->load('photos')),
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->findOrFail($id);

        $memory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Memory deleted successfully.',
            'data' => null,
        ]);
    }
}