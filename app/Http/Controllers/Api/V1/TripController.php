<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class TripController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $trips = Trip::query()
            ->ownedBy($user->id)
            ->withCount('memories')
            ->latest('id')
            ->get();

        return TripResource::collection($trips);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $trip = $user->trips()->create([
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
            'location' => $request->input('location'),
            'cover_photo' => $request->hasFile('cover_image') ? $request->file('cover_image')->store('trip_covers', 'public') : null,
            'category' => $request->input('category'),
            'is_favorite' => $request->boolean('is_favorite'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'status' => $request->input('status', 'planned'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trip created successfully.',
            'data' => [
                'trip' => new TripResource($trip),
            ],
        ], 201);
    }

    public function show(Request $request, int $trip): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->with('memories.photos')
            ->findOrFail($trip);

        return response()->json([
            'success' => true,
            'message' => 'Trip fetched successfully.',
            'data' => [
                'trip' => new TripResource($trip),
            ],
        ]);
    }

    public function update(UpdateTripRequest $request, int $trip): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($trip);

        $data = [
            'title' => $request->has('title') ? $request->string('title')->toString() : $trip->title,
            'description' => $request->has('description') ? $request->input('description') : $trip->description,
            'location' => $request->has('location') ? $request->input('location') : $trip->location,
            'category' => $request->has('category') ? $request->input('category') : $trip->category,
            'is_favorite' => $request->has('is_favorite') ? $request->boolean('is_favorite') : $trip->is_favorite,
            'start_date' => $request->has('start_date') ? $request->input('start_date') : $trip->start_date,
            'end_date' => $request->has('end_date') ? $request->input('end_date') : $trip->end_date,
            'status' => $request->has('status') ? $request->input('status') : $trip->status,
        ];

        if ($request->hasFile('cover_image')) {
            if ($trip->cover_photo) {
                Storage::disk('public')->delete($trip->cover_photo);
            }

            $data['cover_photo'] = $request->file('cover_image')->store('trip_covers', 'public');
        }

        $trip->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Trip updated successfully.',
            'data' => [
                'trip' => new TripResource($trip->fresh()),
            ],
        ]);
    }

    public function toggleFavorite(Request $request, int $trip): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($trip);

        $trip->update(['is_favorite' => ! $trip->is_favorite]);

        return response()->json([
            'success' => true,
            'message' => 'Trip favorite status updated successfully.',
            'data' => [
                'trip' => new TripResource($trip->fresh()),
            ],
        ]);
    }

    public function destroy(Request $request, int $trip): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($trip);

        $trip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Trip deleted successfully.',
            'data' => null,
        ]);
    }
}
