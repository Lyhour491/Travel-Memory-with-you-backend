<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemoryPhotoResource;
use App\Http\Resources\MemoryResource;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DraftMemoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $drafts = Memory::query()
            ->ownedBy($user->id)
            ->drafts()
            ->with('photos')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Drafts fetched successfully.',
            'data' => [
                'drafts' => MemoryResource::collection($drafts),
                'movements' => MemoryResource::collection($drafts),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate($this->draftRules());

        if (array_key_exists('trip_id', $data) && $data['trip_id']) {
            Trip::query()->ownedBy($user->id)->findOrFail($data['trip_id']);
        }

        try {
            $draft = Memory::query()->create($this->memoryData($request, $user, 'draft'));

            foreach ($this->uploadedMemoryImages($request) as $index => $file) {
                MemoryPhoto::query()->create([
                    'memory_id' => $draft->id,
                    'photo_path' => $file->store('memory_photos', 'public'),
                    'photo_order' => $index,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Draft memory creation failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Draft could not be saved. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft saved successfully.',
            'data' => $this->resourceAliases($draft->load('photos')),
        ], 201);
    }

    public function show(Request $request, int $memory): JsonResponse
    {
        $draft = $this->draftForUser($request, $memory);

        return response()->json([
            'success' => true,
            'message' => 'Draft fetched successfully.',
            'data' => $this->resourceAliases($draft),
        ]);
    }

    public function update(Request $request, int $memory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $draft = $this->draftForUser($request, $memory);

        $data = $request->validate($this->draftRules(false));

        if (array_key_exists('trip_id', $data) && $data['trip_id']) {
            Trip::query()->ownedBy($user->id)->findOrFail($data['trip_id']);
        }

        $draft->update($this->memoryData($request, $user, 'draft', $draft));

        return response()->json([
            'success' => true,
            'message' => 'Draft updated successfully.',
            'data' => $this->resourceAliases($draft->fresh()->load('photos')),
        ]);
    }

    public function destroy(Request $request, int $memory): JsonResponse
    {
        $draft = $this->draftForUser($request, $memory);
        $paths = $draft->photos->pluck('photo_path')->filter()->values()->all();

        DB::transaction(function () use ($draft): void {
            $draft->photos()->delete();
            $draft->forceDelete();
        });

        Storage::disk('public')->delete($paths);

        return response()->json([
            'success' => true,
            'message' => 'Draft deleted successfully.',
            'data' => null,
        ]);
    }

    public function uploadPhotos(Request $request, int $memory): JsonResponse
    {
        $draft = $this->draftForUser($request, $memory);

        $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'image', 'max:5120'],
        ]);

        $uploadedPhotos = [];
        $baseOrder = (int) $draft->photos()->count();

        foreach ($request->file('photos', []) as $index => $file) {
            $uploadedPhotos[] = MemoryPhoto::query()->create([
                'memory_id' => $draft->id,
                'photo_path' => $file->store('memory_photos', 'public'),
                'photo_order' => $baseOrder + $index,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft photos uploaded successfully.',
            'data' => [
                'draft_id' => $draft->id,
                'memory_id' => $draft->id,
                'photos' => MemoryPhotoResource::collection(collect($uploadedPhotos)),
            ],
        ], 201);
    }

    public function publish(Request $request, int $memory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $draft = $this->draftForUser($request, $memory);

        $data = $request->validate($this->draftRules(false));

        if ($data !== []) {
            if (array_key_exists('trip_id', $data) && $data['trip_id']) {
                Trip::query()->ownedBy($user->id)->findOrFail($data['trip_id']);
            }

            $draft->update($this->memoryData($request, $user, 'draft', $draft));
            $draft = $draft->fresh()->load('photos');
        }

        $tripId = $draft->trip_id;

        $messages = [];

        if (! $draft->title) {
            $messages['title'] = ['The title field is required.'];
        }

        if (! $tripId) {
            $messages['trip_id'] = ['The trip id field is required.'];
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        Trip::query()->ownedBy($user->id)->findOrFail($tripId);

        $draft->update(['status' => 'published']);

        return response()->json([
            'success' => true,
            'message' => 'Draft published successfully.',
            'data' => $this->resourceAliases($draft->fresh()->load('photos')),
        ]);
    }

    private function resourceAliases(Memory $memory): array
    {
        return [
            'draft' => new MemoryResource($memory),
            'movement' => new MemoryResource($memory),
            'memory' => new MemoryResource($memory),
        ];
    }

    private function draftForUser(Request $request, int $memory): Memory
    {
        /** @var User $user */
        $user = $request->user();

        return Memory::query()
            ->ownedBy($user->id)
            ->drafts()
            ->with('photos')
            ->findOrFail($memory);
    }

    private function draftRules(bool $creating = true): array
    {
        $presence = $creating ? ['nullable'] : ['sometimes', 'nullable'];

        return [
            'title' => [...$presence, 'string', 'max:150'],
            'note' => ['nullable', 'string'],
            'trip_id' => [...$presence, 'integer', 'exists:trips,id'],
            'date_time' => ['nullable', 'date'],
            'dateTime' => ['nullable', 'date'],
            'place' => ['nullable', 'string', 'max:150'],
            'is_favorite' => ['nullable', 'boolean'],
            'memory_date' => ['nullable', 'date'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ];
    }

    private function memoryData(Request $request, User $user, string $status, ?Memory $memory = null): array
    {
        return [
            'trip_id' => $request->has('trip_id') ? $request->input('trip_id') : $memory?->trip_id,
            'user_id' => $user->id,
            'title' => $request->has('title') ? $request->input('title') : $memory?->title,
            'note' => $request->has('note') ? $request->input('note') : $memory?->note,
            'date_time' => ($request->has('date_time') || $request->has('dateTime')) ? $request->input('date_time', $request->input('dateTime')) : $memory?->date_time,
            'place' => $request->has('place') ? $request->input('place') : $memory?->place,
            'memory_date' => $request->has('memory_date') ? $request->input('memory_date') : $memory?->memory_date,
            'location_name' => $request->has('location_name') ? $request->input('location_name', $request->input('place')) : $memory?->location_name,
            'latitude' => $request->has('latitude') ? $request->input('latitude') : $memory?->latitude,
            'longitude' => $request->has('longitude') ? $request->input('longitude') : $memory?->longitude,
            'address' => $request->has('address') ? $request->input('address') : $memory?->address,
            'is_favorite' => $request->has('is_favorite') ? $request->boolean('is_favorite') : (bool) ($memory?->is_favorite ?? false),
            'status' => $status,
        ];
    }

    private function uploadedMemoryImages(Request $request): array
    {
        return array_values(array_merge(
            $request->file('images', []),
            $request->file('photos', []),
        ));
    }
}
