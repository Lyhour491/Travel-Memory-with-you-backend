<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserStatsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $totalTrips = $user->trips()->count();
        $totalMemories = $user->memories()->count();
        $uniqueCountriesVisited = $user->memories()
            ->whereNotNull('address')
            ->distinct()
            ->count('address');

        return response()->json([
            'success' => true,
            'message' => 'User stats fetched successfully.',
            'data' => [
                'total_trips' => $totalTrips,
                'total_memories' => $totalMemories,
                'unique_countries_visited' => $uniqueCountriesVisited,
            ],
        ]);
    }
}
