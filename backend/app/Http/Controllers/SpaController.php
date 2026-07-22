<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serves the built admin-web/superadmin-web index.html for any client-side
 * route that doesn't correspond to a real static file. Real asset files
 * (JS/CSS under public/admin/assets, public/superadmin/assets) are served
 * directly by Apache — see public/.htaccess's !-f/!-d rewrite conditions —
 * so this only ever runs for the SPA's own routes (docs/02-ARCHITECTURE.md §4).
 */
class SpaController extends Controller
{
    public function adminApp(): SymfonyResponse
    {
        return $this->serveIndex(public_path('admin/index.html'));
    }

    public function superAdminApp(): SymfonyResponse
    {
        return $this->serveIndex(public_path('superadmin/index.html'));
    }

    private function serveIndex(string $path): SymfonyResponse
    {
        if (! file_exists($path)) {
            abort(404, 'Not built yet — run the frontend build and copy step.');
        }

        return new Response(file_get_contents($path), 200, ['Content-Type' => 'text/html']);
    }
}
