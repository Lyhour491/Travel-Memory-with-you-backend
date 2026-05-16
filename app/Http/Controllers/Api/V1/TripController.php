<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $trips = Trip::query()
            ->ownedBy($user->id)
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Trips fetched successfully.',
            'data' => [
                'trips' => TripResource::collection($trips),
            ],
        ]);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $trip = $user->trips()->create([
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
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

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Trip fetched successfully.',
            'data' => [
                'trip' => new TripResource($trip),
            ],
        ]);
    }

    public function update(UpdateTripRequest $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($id);

        $trip->update([
            'title' => $request->has('title') ? $request->string('title')->toString() : $trip->title,
            'description' => $request->has('description') ? $request->input('description') : $trip->description,
            'start_date' => $request->has('start_date') ? $request->input('start_date') : $trip->start_date,
            'end_date' => $request->has('end_date') ? $request->input('end_date') : $trip->end_date,
            'status' => $request->has('status') ? $request->input('status') : $trip->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trip updated successfully.',
            'data' => [
                'trip' => new TripResource($trip->fresh()),
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $trip = Trip::query()
            ->ownedBy($user->id)
            ->findOrFail($id);

        $trip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Trip deleted successfully.',
            'data' => null,
        ]);
    }
}