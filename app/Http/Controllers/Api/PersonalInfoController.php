<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Birthday and address — the personal details HRIS doesn't send, so they are
 * kept locally for every member type and are never touched by the HRIS sync.
 */
class PersonalInfoController extends Controller
{
    use ApiResponse;

    private const RULES = [
        'birth_date' => 'nullable|date|before:today|after:1900-01-01',
        'address'    => 'nullable|string|max:500',
    ];

    /** The signed-in member updates their own details. */
    public function updateSelf(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validate(self::RULES))->save();

        return $this->success(new UserResource($user->fresh()), 'Personal details saved.');
    }

    /** An admin updates any member's details. */
    public function updateMember(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(self::RULES);
        $before = $user->only(['birth_date', 'address']);

        $user->fill($data)->save();

        if ($user->wasChanged(['birth_date', 'address'])) {
            ActivityLog::record([
                'admin_id'        => $request->user()->id,
                'action'          => 'member_personal_updated',
                'subject'         => $user->employee_id,
                'description'     => $user->full_name,
                'old_value'       => 'birthday: ' . ($before['birth_date']?->toDateString() ?? '-') . ', address: ' . ($before['address'] ?? '-'),
                'new_value'       => 'birthday: ' . ($user->birth_date?->toDateString() ?? '-') . ', address: ' . ($user->address ?? '-'),
                'employment_type' => $user->employment_type,
            ]);
        }

        return $this->success(new UserResource($user->fresh()), 'Personal details saved.');
    }
}
