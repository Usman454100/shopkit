<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreRegistrationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreRegistrationRequestController extends Controller
{
    /**
     * Public submission — see docs/01-PRD.md §7.1 step 1.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:grocery,vegetable,shoe,other'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
        ]);

        $registrationRequest = StoreRegistrationRequest::create($data + ['status' => 'pending']);

        return response()->json([
            'data' => $registrationRequest,
        ], 201);
    }
}
