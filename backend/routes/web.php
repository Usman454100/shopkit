<?php

// Central-domain routes (e.g. the public store registration form) land here
// starting Milestone 1 (see docs/07-ROADMAP.md). Note: any route defined here
// with the same method+URI as one in routes/tenant.php will be silently
// overwritten by the tenant route, since Laravel's RouteCollection keys
// routes by method+URI regardless of which file registered them.

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// Super Admin console SPA (see docs/02-ARCHITECTURE.md §4 and Milestone 5).
// Real static assets under /superadmin/assets/* are served directly by
// Apache (public/.htaccess bypasses PHP for existing files); this only
// catches client-side routes that need the index.html shell instead.
Route::get('/superadmin/{any?}', [SpaController::class, 'superAdminApp'])
    ->where('any', '.*');
