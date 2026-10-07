<?php

use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\ConversationController;
use App\Http\Controllers\Api\Admin\OperatorController;
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

// Protected Admin & Operator Routes
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // Conversations & HITL Management
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::post('/conversations/{id}/reply', [ConversationController::class, 'reply']);
    Route::patch('/conversations/{id}/status', [ConversationController::class, 'updateStatus']);
    Route::post('/conversations/{id}/assign', [ConversationController::class, 'assign']);

    // Operator Management
    Route::get('/operators', [OperatorController::class, 'index']);
    Route::patch('/operators/{id}/status', [OperatorController::class, 'updateStatus']);

    // Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
});
