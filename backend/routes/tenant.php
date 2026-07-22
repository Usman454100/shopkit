<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\StoreAdmin\CustomerController as StoreAdminCustomerController;
use App\Http\Controllers\Api\StoreAdmin\DashboardController as StoreAdminDashboardController;
use App\Http\Controllers\Api\StoreAdmin\OrderController as StoreAdminOrderController;
use App\Http\Controllers\SpaController;
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

// Store Admin console SPA (see docs/02-ARCHITECTURE.md §4 and Milestone 5).
// Per-store subdomain, not central — this is the tenant-scoped counterpart
// to the /superadmin/{any?} route in routes/web.php. No auth/store.sync
// needed just to serve the static shell; the SPA itself calls authenticated
// API endpoints once loaded.
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->get('/admin/{any?}', [SpaController::class, 'adminApp'])->where('any', '.*');

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

    // Order management & dashboard (see docs/07-ROADMAP.md Milestone 4). Under
    // /admin to avoid colliding with the customer-facing /api/orders routes
    // below — Laravel's route collection keys by method+URI regardless of
    // domain, so reusing the same URI would silently overwrite one or the other.
    Route::prefix('admin')->group(function () {
        Route::get('dashboard', [StoreAdminDashboardController::class, 'index']);
        Route::get('orders', [StoreAdminOrderController::class, 'index']);
        Route::get('orders/{order}', [StoreAdminOrderController::class, 'show']);
        Route::patch('orders/{order}/status', [StoreAdminOrderController::class, 'updateStatus']);
        Route::get('customers', [StoreAdminCustomerController::class, 'index']);
        Route::get('customers/{customer}', [StoreAdminCustomerController::class, 'show']);
    });
});

// Customer catalog browsing — anonymous, per the Milestone 3 decision to allow
// browsing before login (auth is only required at checkout). See docs/01-PRD.md §7.2.
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'store.sync',
    'store.active',
])->prefix('api')->group(function () {
    Route::get('catalog/products', [CatalogController::class, 'index']);
    Route::get('catalog/products/{product}', [CatalogController::class, 'show']);
});

// Customer checkout & order history — authenticated. Deliberately no
// store.member here: unlike staff, a customer's token is valid on any
// approved store's subdomain (see docs/03-DATABASE-SCHEMA.md's users.store_id note).
Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'store.sync',
    'store.active',
    'auth:sanctum',
    'role:customer',
    SubstituteBindings::class,
])->prefix('api')->group(function () {
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
});
