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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TripController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $trips = Trip::query()
            ->ownedBy($user->id)
            ->published()
            ->withCount('memories')
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

    public function drafts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $drafts = Trip::query()
            ->ownedBy($user->id)
            ->drafts()
            ->withCount('memories')
            ->latest('updated_at')
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Trip drafts fetched successfully.',
            'data' => [
                'trips' => TripResource::collection($drafts),
            ],
        ]);
    }

    public function storeDraft(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate($this->tripDraftRules());

        try {
            $trip = $user->trips()->create($this->tripDraftData($request));
        } catch (\Throwable $exception) {
            Log::error('Trip draft creation failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Trip draft could not be saved. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Trip draft saved successfully.',
            'data' => [
                'draft' => new TripResource($trip),
                'trip' => new TripResource($trip),
            ],
        ], 201);
    }

    public function updateDraft(Request $request, int $trip): JsonResponse
    {
        $trip = $this->tripDraftForUser($request, $trip);

        $request->validate($this->tripDraftRules(false));

        $trip->update($this->tripDraftData($request, $trip));

        return response()->json([
            'success' => true,
            'message' => 'Trip draft updated successfully.',
            'data' => [
                'trip' => new TripResource($trip->fresh()),
            ],
        ]);
    }

    public function destroyDraft(Request $request, int $trip): JsonResponse
    {
        $trip = $this->tripDraftForUser($request, $trip)->load('memories.photos');
        $coverPhoto = $trip->cover_photo;
        $photoPaths = $trip->memories
            ->flatMap(fn ($memory) => $memory->photos->pluck('photo_path'))
            ->filter()
            ->values()
            ->all();

        $trip->forceDelete();

        if ($coverPhoto) {
            Storage::disk('public')->delete($coverPhoto);
        }

        Storage::disk('public')->delete($photoPaths);

        return response()->json([
            'success' => true,
            'message' => 'Trip draft deleted successfully.',
            'data' => null,
        ]);
    }

    public function publishDraft(Request $request, int $trip): JsonResponse
    {
        $trip = $this->tripDraftForUser($request, $trip);

        $request->validate($this->tripDraftRules(false));

        if ($request->all() !== []) {
            $trip->update($this->tripDraftData($request, $trip));
            $trip = $trip->fresh();
        }

        if (! $trip->title) {
            throw ValidationException::withMessages([
                'title' => ['The title field is required.'],
            ]);
        }

        $trip->update(['status' => 'planned']);

        return response()->json([
            'success' => true,
            'message' => 'Trip draft published successfully.',
            'data' => [
                'trip' => new TripResource($trip->fresh()),
            ],
        ]);
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
            ->published()
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
            ->published()
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
            ->published()
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
            ->published()
            ->findOrFail($trip);

        $trip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Trip deleted successfully.',
            'data' => null,
        ]);
    }

    private function tripDraftForUser(Request $request, int $trip): Trip
    {
        /** @var User $user */
        $user = $request->user();

        return Trip::query()
            ->ownedBy($user->id)
            ->drafts()
            ->findOrFail($trip);
    }

    private function tripDraftRules(bool $creating = true): array
    {
        $presence = $creating ? ['nullable'] : ['sometimes', 'nullable'];

        return [
            'title' => [...$presence, 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_favorite' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    private function tripDraftData(Request $request, ?Trip $trip = null): array
    {
        $data = [
            'title' => $this->tripDraftTitle($request, $trip),
            'description' => $request->has('description') ? $request->input('description') : $trip?->description,
            'location' => $request->has('location') ? $request->input('location') : $trip?->location,
            'category' => $request->has('category') ? $request->input('category') : $trip?->category,
            'is_favorite' => $request->has('is_favorite') ? $request->boolean('is_favorite') : (bool) ($trip?->is_favorite ?? false),
            'start_date' => $request->has('start_date') ? $request->input('start_date') : $trip?->start_date,
            'end_date' => $request->has('end_date') ? $request->input('end_date') : $trip?->end_date,
            'status' => 'draft',
        ];

        if ($request->hasFile('cover_image')) {
            if ($trip?->cover_photo) {
                Storage::disk('public')->delete($trip->cover_photo);
            }

            $data['cover_photo'] = $request->file('cover_image')->store('trip_covers', 'public');
        }

        return $data;
    }

    private function tripDraftTitle(Request $request, ?Trip $trip = null): string
    {
        if (! $request->has('title')) {
            return $trip?->title ?: 'Untitled Trip';
        }

        $title = trim((string) $request->input('title'));

        return $title !== '' ? $title : 'Untitled Trip';
    }
}
