<?php

use Illuminate\Support\Facades\Route;

// Feature endpoints land here starting Milestone 1 (see docs/07-ROADMAP.md).
Route::middleware('auth:sanctum')->get('/user', function (Illuminate\Http\Request $request) {
    return $request->user();
});
