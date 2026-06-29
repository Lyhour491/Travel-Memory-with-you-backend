<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load([
            'profile',
            'trips',
            'memories.photos',
        ]);

        $paths = $this->publicDiskPathsFor($user);

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        Storage::disk('public')->delete($paths);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }

    private function publicDiskPathsFor(User $user): array
    {
        $paths = [];

        if ($user->profile?->avatar) {
            $paths[] = $user->profile->avatar;
        }

        foreach ($user->trips as $trip) {
            if ($trip->cover_photo) {
                $paths[] = $trip->cover_photo;
            }
        }

        foreach ($user->memories as $memory) {
            foreach ($memory->photos as $photo) {
                $paths[] = $photo->photo_path;
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }
}
