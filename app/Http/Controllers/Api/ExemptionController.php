<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanExemptionRequest;
use App\Services\ExemptionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExemptionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExemptionService $exemptionService
    ) {}

    /**
     * Create a new exemption request (member endpoint).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:below_minimum_pay,exceed_max_amount,extend_term',
            'loan_type' => 'required|string|max:100',
            'requested_value' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:2000',
            'otp' => 'nullable|string|size:6',
        ]);

        // If OTP is required by config, verify it
        if (\App\Models\Configuration::getBool('loan_otp_required', true)) {
            if (empty($validated['otp'])) {
                // Send OTP and return — frontend will show OTP step
                app(\App\Services\OtpService::class)->generate($request->user()->email, 'loan_application');
                return $this->success(['otp_required' => true], 'OTP sent to your email.');
            }

            $verified = app(\App\Services\OtpService::class)->verify(
                $request->user()->email, $validated['otp'], 'loan_application'
            );
            if (!$verified) {
                return $this->error('Invalid or expired OTP.', 422);
            }
        }

        // Auto-fill current_value and required_value from FMIS + config
        $user = $request->user();
        $fmis = app(\App\Services\FmisService::class);
        $loanService = app(\App\Services\LoanService::class);

        if ($validated['type'] === 'below_minimum_pay') {
            $payCheck = $fmis->meetsMinimumPay($user->employee_id, $user->employment_type);
            $validated['current_value'] = $payCheck['actual_amount'];
            $validated['required_value'] = $payCheck['required_amount'];
        } elseif ($validated['type'] === 'exceed_max_amount') {
            $types = $loanService->getAvailableLoanTypes($user);
            $typeInfo = $types[$validated['loan_type']] ?? null;
            $validated['current_value'] = $typeInfo['max_amount'] ?? 0;
            $validated['required_value'] = $typeInfo['max_amount'] ?? 0;
        } else {
            $validated['current_value'] = 0;
            $validated['required_value'] = 0;
        }

        $exemptionRequest = $this->exemptionService->createRequest(
            $user,
            $validated
        );

        return $this->success(
            $exemptionRequest->load('user'),
            'Exemption request submitted successfully.',
            201
        );
    }

    /**
     * List current user's exemption requests (member endpoint).
     */
    public function myRequests(Request $request): JsonResponse
    {
        $exemptions = LoanExemptionRequest::where('user_id', $request->user()->id)
            ->with('reviewer:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->get();

        return $this->success($exemptions);
    }

    /**
     * List exemption requests for review (admin endpoint).
     *
     * Defaults to pending — that's the queue an admin acts on — but supports
     * approved/rejected/all so past decisions can be looked up.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,all',
            'type' => 'nullable|in:below_minimum_pay,exceed_max_amount,extend_term',
            'search' => 'nullable|string|max:100',
        ]);

        $status = $request->input('status', 'pending');

        $query = LoanExemptionRequest::with([
            'user:id,employee_id,first_name,last_name,employment_type,department,email',
            'reviewer:id,first_name,last_name',
        ]);

        // A reviewer only sees the exemption types their role is responsible
        // for — below-min-pay for the committee, amount/term for the admin.
        $reviewableTypes = ExemptionService::typesForRole($request->user()->role);
        $query->whereIn('type', $reviewableTypes ?: ['__none__']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', fn ($u) => $u
                ->where('employee_id', 'LIKE', "%{$search}%")
                ->orWhere('first_name', 'LIKE', "%{$search}%")
                ->orWhere('last_name', 'LIKE', "%{$search}%"));
        }

        return $this->success([
            'requests' => $query->orderByDesc('created_at')->get(),
            'counts' => [
                'pending' => LoanExemptionRequest::whereIn('type', $reviewableTypes ?: ['__none__'])->where('status', 'pending')->count(),
                'approved' => LoanExemptionRequest::whereIn('type', $reviewableTypes ?: ['__none__'])->where('status', 'approved')->count(),
                'rejected' => LoanExemptionRequest::whereIn('type', $reviewableTypes ?: ['__none__'])->where('status', 'rejected')->count(),
            ],
        ]);
    }

    /**
     * Approve an exemption request (admin endpoint).
     */
    public function approve(Request $request, LoanExemptionRequest $exemptionRequest): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:2000',
        ]);

        if ($denied = $this->denyIfNotReviewer($request, $exemptionRequest)) {
            return $denied;
        }

        if ($exemptionRequest->status !== 'pending') {
            return $this->error('This exemption request has already been processed.', 422);
        }

        $this->exemptionService->approve(
            $exemptionRequest,
            $request->user(),
            $validated['remarks'] ?? null
        );

        return $this->success(
            $exemptionRequest->fresh()->load(['user', 'reviewer']),
            'Exemption request approved successfully.'
        );
    }

    /**
     * Reject an exemption request (admin endpoint).
     */
    public function reject(Request $request, LoanExemptionRequest $exemptionRequest): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'required|string|max:2000',
        ]);

        if ($denied = $this->denyIfNotReviewer($request, $exemptionRequest)) {
            return $denied;
        }

        if ($exemptionRequest->status !== 'pending') {
            return $this->error('This exemption request has already been processed.', 422);
        }

        $this->exemptionService->reject(
            $exemptionRequest,
            $request->user(),
            $validated['remarks']
        );

        return $this->success(
            $exemptionRequest->fresh()->load(['user', 'reviewer']),
            'Exemption request rejected.'
        );
    }

    /**
     * 403 unless this reviewer's role owns this exemption type — so an admin
     * can't act on a committee request and vice versa.
     */
    private function denyIfNotReviewer(Request $request, LoanExemptionRequest $exemptionRequest): ?JsonResponse
    {
        $allowed = ExemptionService::typesForRole($request->user()->role);

        if (!in_array($exemptionRequest->type, $allowed, true)) {
            return $this->error('This request is reviewed by a different role.', 403);
        }

        return null;
    }
}
