<?php

declare(strict_types=1);

use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });
});

// Store Admin API — catalog & inventory (see docs/07-ROADMAP.md Milestone 2).
// No 'web' group: these are stateless bearer-token calls, not session/CSRF-based.
// That also means SubstituteBindings isn't pulled in automatically (it normally
// rides along inside 'web'/'api'), so it's listed explicitly here — without it,
// {product}/{inventory} route params never resolve to real Eloquent models at
// all, they silently become blank unsaved instances. It must run after
// store.sync (so route-model binding is scoped to the right tenant) and after
// auth/store.member (so it isn't resolving on behalf of an unauthorized request).
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'store.sync',
    'auth:sanctum',
    'store.member',
    'role:org_owner,store_admin,super_admin',
    SubstituteBindings::class,
])->prefix('api')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::post('products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update']);
    Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy']);
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::patch('inventory/{inventory}', [InventoryController::class, 'update']);
});
