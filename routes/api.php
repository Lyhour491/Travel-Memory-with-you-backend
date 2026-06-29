<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\DraftMemoryController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\MemoryPhotoController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\UserStatsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google', [AuthController::class, 'google']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::match(['put', 'patch', 'post'], '/profile', [ProfileController::class, 'update']);
        Route::delete('/account', [AccountController::class, 'destroy']);
        Route::get('/user/stats', [UserStatsController::class, 'show']);

        Route::prefix('drafts')->group(function () {
            Route::get('/', [DraftMemoryController::class, 'index']);
            Route::post('/', [DraftMemoryController::class, 'store']);
            Route::get('/{memory}', [DraftMemoryController::class, 'show']);
            Route::put('/{memory}', [DraftMemoryController::class, 'update']);
            Route::delete('/{memory}', [DraftMemoryController::class, 'destroy']);
            Route::post('/{memory}/publish', [DraftMemoryController::class, 'publish']);
        });

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
            Route::get('/{memory}/photos', [MemoryPhotoController::class, 'index']);
            Route::post('/{memory}/photos', [MemoryPhotoController::class, 'upload']);
        });

        Route::delete('/photos/{photo}', [MemoryPhotoController::class, 'destroy']);
    });
});
