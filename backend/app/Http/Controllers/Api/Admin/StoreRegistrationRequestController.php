<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Store;
use App\Models\StoreRegistrationRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\StoreApprovalInvite;
use App\Notifications\StoreRegistrationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreRegistrationRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $requests = StoreRegistrationRequest::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25);

        return response()->json($requests);
    }

    public function show(StoreRegistrationRequest $storeRegistrationRequest): JsonResponse
    {
        return response()->json(['data' => $storeRegistrationRequest]);
    }

    /**
     * Approve: create the owner, organization, store (tenant), domain, and a
     * trialing Basic subscription in one transaction, then invite the owner.
     * See docs/01-PRD.md §7.1 step 3, docs/07-ROADMAP.md Milestone 1.
     */
    public function approve(Request $request, StoreRegistrationRequest $storeRegistrationRequest): JsonResponse
    {
        $this->ensurePending($storeRegistrationRequest);

        $result = DB::transaction(function () use ($request, $storeRegistrationRequest) {
            $inviteToken = Str::random(64);

            $owner = User::create([
                'name' => $storeRegistrationRequest->owner_name,
                'email' => $storeRegistrationRequest->owner_email,
                'phone' => $storeRegistrationRequest->owner_phone,
                'password' => Hash::make(Str::random(40)),
                'role' => 'org_owner',
                'invite_token' => $inviteToken,
                'invite_expires_at' => now()->addDays(7),
            ]);

            $organization = Organization::create([
                'name' => $storeRegistrationRequest->business_name,
                'owner_user_id' => $owner->id,
                'isolation_tier_default' => 'shared',
            ]);

            $slug = Store::generateUniqueSlug($storeRegistrationRequest->business_name);

            $store = Store::create([
                'organization_id' => $organization->id,
                'name' => $storeRegistrationRequest->business_name,
                'slug' => $slug,
                'category' => $storeRegistrationRequest->category,
                'address' => $storeRegistrationRequest->address,
                'status' => 'approved',
                'isolation_tier' => 'shared',
            ]);

            $store->createDomain($slug.'.'.config('tenancy.central_domain'));

            $owner->update(['store_id' => $store->id]);

            $basicPlan = SubscriptionPlan::where('name', 'Basic')->firstOrFail();

            $organization->subscriptions()->create([
                'plan_id' => $basicPlan->id,
                'status' => 'trialing',
                'started_at' => now(),
            ]);

            $storeRegistrationRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return [$store, $owner, $inviteToken];
        });

        [$store, $owner, $inviteToken] = $result;

        $owner->notify(new StoreApprovalInvite($store, $inviteToken));

        return response()->json([
            'data' => $storeRegistrationRequest->fresh(),
            'store' => $store,
        ]);
    }

    public function reject(Request $request, StoreRegistrationRequest $storeRegistrationRequest): JsonResponse
    {
        $this->ensurePending($storeRegistrationRequest);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $storeRegistrationRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Notification::route('mail', $storeRegistrationRequest->owner_email)
            ->notify(new StoreRegistrationRejected($storeRegistrationRequest->business_name, $data['reason']));

        return response()->json(['data' => $storeRegistrationRequest->fresh()]);
    }

    private function ensurePending(StoreRegistrationRequest $storeRegistrationRequest): void
    {
        if ($storeRegistrationRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'This request has already been reviewed.',
            ]);
        }
    }
}
