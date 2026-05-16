<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemoryPhotoResource;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoryPhotoController extends Controller
{
    public function index(Request $request, int $memoryId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->with('photos')
            ->findOrFail($memoryId);

        return response()->json([
            'success' => true,
            'message' => 'Photos fetched successfully.',
            'data' => [
                'memory_id' => $memory->id,
                'photos' => MemoryPhotoResource::collection($memory->photos),
            ],
        ]);
    }

    public function upload(Request $request, int $memoryId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $memory = Memory::query()
            ->ownedBy($user->id)
            ->withCount('photos')
            ->findOrFail($memoryId);

        $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'image', 'max:5120'],
        ]);

        $uploadedPhotos = [];
        $baseOrder = (int) $memory->photos_count;

        foreach ($request->file('photos', []) as $index => $file) {
            $path = $file->store('memory_photos', 'public');

            $uploadedPhotos[] = MemoryPhoto::query()->create([
                'memory_id' => $memory->id,
                'photo_path' => $path,
                'photo_order' => $baseOrder + $index,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Photos uploaded successfully.',
            'data' => [
                'photos' => MemoryPhotoResource::collection(collect($uploadedPhotos)),
            ],
        ], 201);
    }

    public function destroy(Request $request, int $photoId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $photo = MemoryPhoto::query()
            ->whereHas('memory', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->findOrFail($photoId);

        Storage::disk('public')->delete($photo->photo_path);

        $photo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Photo deleted successfully.',
            'data' => null,
        ]);
    }
}
