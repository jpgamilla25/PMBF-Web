<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClaimRequest;
use App\Http\Requests\StoreDependentRequest;
use App\Http\Resources\BenefitResource;
use App\Http\Resources\ClaimResource;
use App\Http\Resources\DependentResource;
use App\Http\Resources\UserResource;
use App\Models\Dependent;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    use ApiResponse;

    /**
     * Dependents, claims and benefits are member benefits reserved for Permanent
     * employees. Returns a 403 response for anyone else, or null to proceed.
     */
    private function denyIfNotPermanent(Request $request): ?JsonResponse
    {
        if ($request->user()->employment_type !== 'Permanent') {
            return $this->error('This feature is only available to Permanent employees.', 403);
        }

        return null;
    }

    /**
     * Get the authenticated user's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadCount(['loans', 'dependents']);

        $data = (new UserResource($user))->toArray($request);
        $data['total_loans'] = $user->loans()->count();
        $data['active_loans'] = $user->loans()->whereIn('status', ['approved', 'released'])->count();
        $data['dependents_count'] = $user->dependents()->count();

        return $this->success($data, 'Profile retrieved.');
    }

    /**
     * List the authenticated user's dependents.
     */
    public function dependents(Request $request): JsonResponse
    {
        if ($denied = $this->denyIfNotPermanent($request)) return $denied;

        $dependents = $request->user()
            ->dependents()
            ->withCount('attachments')
            ->latest()
            ->get();

        return $this->success(
            DependentResource::collection($dependents),
            'Dependents retrieved successfully.'
        );
    }

    /**
     * Store a new dependent.
     */
    public function storeDependent(StoreDependentRequest $request): JsonResponse
    {
        if ($denied = $this->denyIfNotPermanent($request)) return $denied;

        $dependent = $request->user()->dependents()->create($request->validated());

        return $this->success(
            new DependentResource($dependent),
            'Dependent added successfully.',
            201
        );
    }

    /**
     * Delete a dependent.
     */
    public function destroyDependent(Request $request, Dependent $dependent): JsonResponse
    {
        if ($denied = $this->denyIfNotPermanent($request)) return $denied;

        // Ensure the dependent belongs to the current user
        if ($dependent->user_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        $dependent->delete();

        return $this->success(null, 'Dependent removed successfully.');
    }

    /**
     * List the authenticated user's claims.
     */
    public function claims(Request $request): JsonResponse
    {
        if ($denied = $this->denyIfNotPermanent($request)) return $denied;

        $claims = $request->user()
            ->claims()
            ->with('dependent')
            ->withCount('attachments')
            ->latest()
            ->paginate($request->input('per_page', 15));

        return $this->paginated($claims, ClaimResource::class);
    }

    /**
     * Store a new claim.
     */
    public function storeClaim(StoreClaimRequest $request): JsonResponse
    {
        if ($denied = $this->denyIfNotPermanent($request)) return $denied;

        $validated = $request->validated();

        // If dependent_id is provided, ensure it belongs to the user
        if (!empty($validated['dependent_id'])) {
            $owns = $request->user()
                ->dependents()
                ->where('id', $validated['dependent_id'])
                ->exists();

            if (!$owns) {
                return $this->error('The specified dependent does not belong to you.', 422);
            }
        }

        $claim = $request->user()->claims()->create(array_merge($validated, [
            'status' => 'pending',
        ]));

        $claim->load('dependent');

        return $this->success(
            new ClaimResource($claim),
            'Claim submitted successfully.',
            201
        );
    }

    /**
     * List the authenticated user's benefits.
     */
    public function benefits(Request $request): JsonResponse
    {
        if ($denied = $this->denyIfNotPermanent($request)) return $denied;

        $benefits = $request->user()
            ->benefits()
            ->latest()
            ->get();

        return $this->success(
            BenefitResource::collection($benefits),
            'Benefits retrieved successfully.'
        );
    }
}
