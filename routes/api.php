<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\MemoryPhotoController;

Route::prefix('v1')->group(function () {

    // =========================
    // AUTH
    // =========================
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    // =========================
    // PROTECTED
    // =========================
    Route::middleware('auth:sanctum')->group(function () {

        // PROFILE
        Route::get('/profile', [ProfileController::class, 'show']);

        // =========================
        // TRIPS
        // =========================
        Route::prefix('trips')->group(function () {

            Route::get('/', [TripController::class, 'index']);
            Route::post('/', [TripController::class, 'store']);
            Route::get('/{trip}', [TripController::class, 'show']);
            Route::put('/{trip}', [TripController::class, 'update']);
            Route::delete('/{trip}', [TripController::class, 'destroy']);

            // =========================
            // MEMORIES (NESTED)
            // =========================
            Route::get('/{trip}/memories', [MemoryController::class, 'index']);
            Route::post('/{trip}/memories', [MemoryController::class, 'store']);
        });

        // =========================
        // MEMORIES (GLOBAL ACCESS)
        // =========================
        Route::prefix('memories')->group(function () {

            Route::get('/{memory}', [MemoryController::class, 'show']);
            Route::put('/{memory}', [MemoryController::class, 'update']);
            Route::delete('/{memory}', [MemoryController::class, 'destroy']);

            // =========================
            // PHOTOS (NESTED UNDER MEMORY)
            // =========================
            Route::get('/{memory}/photos', [MemoryPhotoController::class, 'index']);
            Route::post('/{memory}/photos', [MemoryPhotoController::class, 'upload']);
        });

        // =========================
        // PHOTOS (GLOBAL)
        // =========================
        Route::delete('/photos/{photo}', [MemoryPhotoController::class, 'destroy']);

    });

});