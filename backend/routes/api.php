<?php

use App\Http\Controllers\Api\Admin\StoreRegistrationRequestController as AdminStoreRegistrationRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\StoreDirectoryController;
use App\Http\Controllers\Api\StoreRegistrationRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Illuminate\Http\Request $request) {
    return $request->user();
});

// Public — store onboarding (see docs/01-PRD.md §7.1, docs/07-ROADMAP.md Milestone 1).
Route::middleware('throttle:5,60')->group(function () {
    Route::post('/store-registration-requests', [StoreRegistrationRequestController::class, 'store']);
    Route::post('/invite/{token}', [InviteController::class, 'accept']);
});

// Public — login/register (register needed for customers, who self-serve unlike
// org_owner/store_admin — see docs/01-PRD.md §7.2, docs/07-ROADMAP.md Milestone 3).
Route::middleware('throttle:10,60')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Public — store directory for the join-store onboarding flow (QR/link/search).
Route::middleware('throttle:30,60')->group(function () {
    Route::get('/stores/lookup', [StoreDirectoryController::class, 'lookup']);
    Route::get('/stores/search', [StoreDirectoryController::class, 'search']);
});

// Authenticated customer — join a store (convenience record, not an authz gate).
Route::middleware(['auth:sanctum', 'role:customer'])->post('/stores/{store}/join', [StoreDirectoryController::class, 'join']);

// Super Admin — store registration approval queue.
Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('admin')->group(function () {
    Route::get('/store-registration-requests', [AdminStoreRegistrationRequestController::class, 'index']);
    Route::get('/store-registration-requests/{storeRegistrationRequest}', [AdminStoreRegistrationRequestController::class, 'show']);
    Route::post('/store-registration-requests/{storeRegistrationRequest}/approve', [AdminStoreRegistrationRequestController::class, 'approve']);
    Route::post('/store-registration-requests/{storeRegistrationRequest}/reject', [AdminStoreRegistrationRequestController::class, 'reject']);
});
