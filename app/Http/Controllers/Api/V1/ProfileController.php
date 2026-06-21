<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load('profile');

        return response()->json([
            'success' => true,
            'message' => 'Profile fetched successfully.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->has('name')) {
            $user->update(['name' => $request->string('name')->toString()]);
        }

        $profile = $user->profile()->firstOrCreate([]);
        $profileData = $request->only(['bio', 'location', 'home_city', 'home_country']);

        if ($request->hasFile('avatar')) {
            if ($profile->avatar) {
                Storage::disk('public')->delete($profile->avatar);
            }

            $profileData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $profile->update($profileData);
        $user->load('profile');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
