<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InviteController extends Controller
{
    /**
     * Redeem an owner invite: set the real password and activate the account.
     * See docs/01-PRD.md §7.1 step 3 ("owner receives credentials/invite link").
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $user = User::where('invite_token', $token)
            ->where('invite_expires_at', '>', now())
            ->first();

        if (! $user) {
            return response()->json(['message' => 'This invite link is invalid or has expired.'], 404);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'invite_token' => null,
            'invite_expires_at' => null,
        ]);

        $token = $user->createToken('invite-acceptance')->plainTextToken;

        return response()->json([
            'data' => $user,
            'token' => $token,
        ]);
    }
}
