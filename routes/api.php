<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\PublicServiceController;
use App\Http\Controllers\Api\UmkmController;
use Illuminate\Support\Facades\Route;

// Global Search (Homepage Search Bar)
Route::get('/search', [GlobalSearchController::class, 'search']);

// Public Services Routes (Guest / Citizen Access)
Route::prefix('public-services')->group(function () {
    Route::get('/', [PublicServiceController::class, 'index']);
    Route::get('/categories', [PublicServiceController::class, 'categories']);
    Route::get('/{slug}', [PublicServiceController::class, 'show']);
});

// UMKM Potensi Desa Routes (Guest / Citizen Access)
Route::prefix('umkms')->group(function () {
    Route::get('/', [UmkmController::class, 'index']);
    Route::get('/categories', [UmkmController::class, 'categories']);
    Route::get('/{id}', [UmkmController::class, 'show']);
});

// Operator Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected Auth Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
