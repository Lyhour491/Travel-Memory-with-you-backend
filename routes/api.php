<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\MemoryPhotoController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\UserStatsController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::match(['put', 'patch', 'post'], '/profile', [ProfileController::class, 'update']);
    Route::get('/user/stats', [UserStatsController::class, 'show']);

    Route::apiResource('trips', TripController::class);
    Route::patch('/trips/{trip}/favorite', [TripController::class, 'toggleFavorite']);
    Route::get('/trips/{trip}/movements', [MemoryController::class, 'index']);
    Route::post('/trips/{trip}/movements', [MemoryController::class, 'store']);

    Route::get('/movements', [MemoryController::class, 'index']);
    Route::post('/movements', [MemoryController::class, 'store']);
    Route::get('/movements/{memory}', [MemoryController::class, 'show']);
    Route::match(['put', 'patch'], '/movements/{memory}', [MemoryController::class, 'update']);
    Route::delete('/movements/{memory}', [MemoryController::class, 'destroy']);
    Route::patch('/movements/{memory}/favorite', [MemoryController::class, 'toggleFavorite']);

    Route::get('/movements/{memory}/photos', [MemoryPhotoController::class, 'index']);
    Route::post('/movements/{memory}/photos', [MemoryPhotoController::class, 'upload']);
    Route::delete('/photos/{photo}', [MemoryPhotoController::class, 'destroy']);
});

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::match(['put', 'patch', 'post'], '/profile', [ProfileController::class, 'update']);
        Route::get('/user/stats', [UserStatsController::class, 'show']);

        Route::prefix('trips')->group(function () {
            Route::get('/', [TripController::class, 'index']);
            Route::post('/', [TripController::class, 'store']);
            Route::get('/{trip}', [TripController::class, 'show']);
            Route::put('/{trip}', [TripController::class, 'update']);
            Route::delete('/{trip}', [TripController::class, 'destroy']);
            Route::patch('/{trip}/favorite', [TripController::class, 'toggleFavorite']);
            Route::get('/{trip}/memories', [MemoryController::class, 'index']);
            Route::post('/{trip}/memories', [MemoryController::class, 'store']);
            Route::get('/{trip}/movements', [MemoryController::class, 'index']);
            Route::post('/{trip}/movements', [MemoryController::class, 'store']);
        });

        Route::prefix('memories')->group(function () {
            Route::get('/{memory}', [MemoryController::class, 'show']);
            Route::put('/{memory}', [MemoryController::class, 'update']);
            Route::delete('/{memory}', [MemoryController::class, 'destroy']);
            Route::patch('/{memory}/favorite', [MemoryController::class, 'toggleFavorite']);
            Route::get('/{memory}/photos', [MemoryPhotoController::class, 'index']);
            Route::post('/{memory}/photos', [MemoryPhotoController::class, 'upload']);
        });

        Route::prefix('movements')->group(function () {
            Route::get('/', [MemoryController::class, 'index']);
            Route::post('/', [MemoryController::class, 'store']);
            Route::get('/{memory}', [MemoryController::class, 'show']);
            Route::put('/{memory}', [MemoryController::class, 'update']);
            Route::delete('/{memory}', [MemoryController::class, 'destroy']);
            Route::patch('/{memory}/favorite', [MemoryController::class, 'toggleFavorite']);
        });

        Route::delete('/photos/{photo}', [MemoryPhotoController::class, 'destroy']);
    });
});
