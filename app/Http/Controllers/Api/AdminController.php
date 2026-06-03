<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\ConfigurationResource;
use App\Http\Resources\LoanResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Configuration;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ShareCapital;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use ApiResponse;

    /**
     * List all members with search and filter.
     */
    public function members(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('employee_id', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->input('employment_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        $members = $query->latest()->paginate($request->input('per_page', 15));

        return $this->paginated($members, UserResource::class);
    }

    /**
     * Show a specific member's details.
     */
    public function showMember(User $user): JsonResponse
    {
        $user->loadCount(['loans', 'dependents', 'claims', 'benefits']);

        return $this->success(
            new UserResource($user),
            'Member details retrieved.'
        );
    }

    /**
     * List all loans with filters.
     */
    public function loans(Request $request): JsonResponse
    {
        $query = Loan::with(['user', 'coMaker', 'approvals.approver'])
            ->withCount('payments');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('loan_type')) {
            $query->where('loan_type', $request->input('loan_type'));
        }

        if ($request->filled('employment_type')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('employment_type', $request->input('employment_type'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('employee_id', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        $loans = $query->latest()->paginate($request->input('per_page', 15));

        return $this->paginated($loans, LoanResource::class);
    }

    /**
     * List all payments.
     */
    public function payments(Request $request): JsonResponse
    {
        $query = Payment::with(['loan.user', 'recorder']);

        if ($request->filled('employment_type')) {
            $query->whereHas('loan.user', function ($q) use ($request) {
                $q->where('employment_type', $request->input('employment_type'));
            });
        }

        if ($request->filled('loan_id')) {
            $query->where('loan_id', $request->input('loan_id'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('from')) {
            $query->where('payment_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('payment_date', '<=', $request->input('to'));
        }

        if ($request->filled('or_number')) {
            $query->where('or_number', 'like', '%' . $request->input('or_number') . '%');
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->whereHas('loan.user', function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('employee_id', 'like', "%{$s}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$s}%"]);
            });
        }

        $payments = $query->latest('payment_date')->paginate($request->input('per_page', 15));

        return $this->paginated($payments, PaymentResource::class);
    }

    /**
     * Record a new payment.
     */
    public function storePayment(StorePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $loan = Loan::findOrFail($validated['loan_id']);

        if (!in_array($loan->status, ['released', 'chairperson_approved'])) {
            return $this->error('Payments can only be recorded for released loans.', 422);
        }

        // Handle receipt image upload (required for cash payments)
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
        }

        $payment = Payment::create(array_merge($validated, [
            'recorded_by' => $request->user()->id,
            'receipt_path' => $receiptPath,
        ]));

        // Check if loan is fully paid (payment already saved, so sum includes it)
        $totalPaid = $loan->payments()->sum('amount');
        $totalPayable = $loan->total_payable;

        if ($totalPaid >= $totalPayable) {
            $loan->update(['status' => 'completed']);
        }

        $payment->load(['loan', 'recorder']);

        return $this->success(
            new PaymentResource($payment),
            'Payment recorded successfully.',
            201
        );
    }

    /**
     * List all configurations.
     */
    public function configurations(Request $request): JsonResponse
    {
        $query = Configuration::query();

        if ($request->filled('group')) {
            $query->where('group', $request->input('group'));
        }

        $configs = $query->orderBy('group')->orderBy('key')->get();

        return $this->success(
            ConfigurationResource::collection($configs),
            'Configurations retrieved.'
        );
    }

    /**
     * Bulk update configurations.
     */
    public function storeConfiguration(Request $request): JsonResponse
    {
        $request->validate([
            'configurations' => 'required|array',
        ]);

        $adminId        = $request->user()->id;
        $employmentType = $request->input('employment_type') ?: null;
        $updated        = 0;

        foreach ($request->input('configurations') as $key => $value) {
            $config = Configuration::where('key', $key)->first();
            if (!$config) {
                continue;
            }

            $oldValue = $config->value;

            // Clean and format based on type
            $value = match ($config->type) {
                'decimal' => number_format((float) $value, 2, '.', ''),
                'number'  => (string) (int) $value,
                'boolean' => in_array($value, ['1', 'true', true, 1], true) ? '1' : '0',
                'text'    => self::cleanCommaSeparated((string) $value),
                default   => (string) $value,
            };

            if ($oldValue === $value) {
                continue; // skip unchanged
            }

            $config->update(['value' => $value]);

            ActivityLog::record([
                'admin_id'        => $adminId,
                'action'          => 'config_updated',
                'subject'         => $key,
                'description'     => $config->description,
                'old_value'       => $oldValue,
                'new_value'       => $value,
                'employment_type' => $employmentType,
                'meta'            => ['group' => $config->group, 'type' => $config->type],
            ]);

            $updated++;
        }

        return $this->success(
            ['updated' => $updated],
            "{$updated} configuration(s) saved successfully."
        );
    }

    /**
     * Set beginning balance for a year.
     */
    public function setBeginningBalance(Request $request): JsonResponse
    {
        $request->validate([
            'year'   => 'required|integer|min:2020|max:2099',
            'amount' => 'required|numeric|min:0',
        ]);

        $key    = 'beginning_balance_' . $request->input('year');
        $old    = Configuration::where('key', $key)->value('value');

        Configuration::updateOrCreate(
            ['key' => $key],
            [
                'value'       => $request->input('amount'),
                'type'        => 'decimal',
                'group'       => 'financial',
                'description' => 'Beginning balance for ' . $request->input('year'),
            ]
        );

        ActivityLog::record([
            'admin_id'    => $request->user()->id,
            'action'      => 'beginning_balance_updated',
            'subject'     => $key,
            'description' => 'Beginning balance for ' . $request->input('year'),
            'old_value'   => $old,
            'new_value'   => $request->input('amount'),
            'meta'        => ['year' => $request->input('year')],
        ]);

        return $this->success(null, 'Beginning balance updated.');
    }

    /**
     * List admin activity logs.
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $query = ActivityLog::with('admin:id,first_name,last_name,employee_id')
            ->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('employment_type') && $request->input('employment_type') !== 'all') {
            $query->where('employment_type', $request->input('employment_type'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return $this->success($logs, 'Activity logs retrieved.');
    }

    /**
     * Generate reports data.
     */
    public function reports(Request $request): JsonResponse
    {
        $from = $request->input('from');
        $to = $request->input('to');

        $loanQuery = Loan::query();
        $paymentQuery = Payment::query();
        $memberQuery = User::query();

        // Filter by employment type (admin context)
        $empType = $request->input('employment_type');
        if ($empType) {
            $loanQuery->whereHas('user', fn ($q) => $q->where('employment_type', $empType));
            $paymentQuery->whereHas('loan.user', fn ($q) => $q->where('employment_type', $empType));
            $memberQuery->where('employment_type', $empType);
        }

        if ($from) {
            $loanQuery->where('created_at', '>=', $from);
            $paymentQuery->where('payment_date', '>=', $from);
        }

        if ($to) {
            $loanQuery->where('created_at', '<=', $to);
            $paymentQuery->where('payment_date', '<=', $to);
        }

        $data = [
            'loans' => [
                'total_applications' => (clone $loanQuery)->count(),
                'total_amount' => (clone $loanQuery)->sum('amount'),
                'approved' => (clone $loanQuery)->whereIn('status', [
                    'chairperson_approved', 'released', 'fully_paid',
                ])->count(),
                'pending' => (clone $loanQuery)->whereIn('status', [
                    'pending', 'receiver_approved', 'committee_approved',
                ])->count(),
                'disapproved' => (clone $loanQuery)->where('status', 'disapproved')->count(),
                'by_type' => (clone $loanQuery)->selectRaw('loan_type, COUNT(*) as count, SUM(amount) as total')
                    ->groupBy('loan_type')
                    ->get(),
            ],
            'payments' => [
                'total_collected' => (clone $paymentQuery)->sum('amount'),
                'total_transactions' => (clone $paymentQuery)->count(),
                'by_method' => (clone $paymentQuery)->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                    ->groupBy('payment_method')
                    ->get(),
            ],
            'members' => [
                'total' => (clone $memberQuery)->count(),
                'active' => (clone $memberQuery)->where('status', 'active')->count(),
                'by_type' => User::selectRaw('employment_type, COUNT(*) as count')
                    ->groupBy('employment_type')
                    ->get(),
            ],
        ];

        return $this->success($data, 'Reports generated successfully.');
    }

    /**
     * Clean comma-separated values: trim whitespace, remove empty entries, remove trailing comma.
     */
    private static function cleanCommaSeparated(string $value): string
    {
        return collect(explode(',', $value))
            ->map(fn ($v) => trim($v))
            ->filter(fn ($v) => $v !== '')
            ->implode(',');
    }

    /**
     * Get admin dashboard overview data.
     * Accepts optional ?employment_type= filter to match admin context.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $empType = $request->input('employment_type');
        $year = $request->input('year', now()->year);

        // ── Member query (context-aware) ──
        $memberQuery = User::query();
        if ($empType && $empType !== 'all') {
            $memberQuery->where('employment_type', $empType);
        }

        $byType = User::selectRaw('employment_type, COUNT(*) as count')
            ->groupBy('employment_type')
            ->pluck('count', 'employment_type');

        // ── Loan query (context-aware) ──
        $loanQuery = Loan::query();
        if ($empType && $empType !== 'all') {
            $loanQuery->whereHas('user', fn ($q) => $q->where('employment_type', $empType));
        }

        // Active loans = released or chairperson_approved
        $activeLoans = (clone $loanQuery)->whereIn('status', ['released', 'chairperson_approved']);
        $totalActiveAmount = (clone $activeLoans)->sum('amount');
        $totalActiveCount = (clone $activeLoans)->count();

        // Total loans ever
        $totalLoansCount = (clone $loanQuery)->count();
        $totalLoansAmount = (clone $loanQuery)->sum('amount');

        // Pending
        $pendingCount = (clone $loanQuery)->whereIn('status', [
            'co_maker_pending', 'admin_pending', 'pending', 'receiver_approved', 'committee_approved',
        ])->count();

        // Payments (context-aware)
        $paymentQuery = Payment::query();
        if ($empType && $empType !== 'all') {
            $paymentQuery->whereHas('loan.user', fn ($q) => $q->where('employment_type', $empType));
        }

        $totalCollected = (clone $paymentQuery)->whereYear('payment_date', $year)->sum('amount');
        $totalCollectedThisMonth = (clone $paymentQuery)->where('payment_date', '>=', now()->startOfMonth())->sum('amount');

        // ── Financial Summary ──
        $beginningBalance = (float) Configuration::getValue('beginning_balance_' . $year, '0');

        // Collectibles = total amortization due on active loans minus total paid
        $collectibles = 0;
        $activeLoansForCalc = (clone $loanQuery)->whereIn('status', ['released', 'chairperson_approved'])->get();
        foreach ($activeLoansForCalc as $loan) {
            $totalPayable = $loan->total_payable;
            $totalPaid = $loan->payments()->sum('amount');
            $collectibles += max(0, $totalPayable - $totalPaid);
        }

        $cashOnHand = $beginningBalance + $totalCollected;

        // ── Monthly collections for chart (current year) ──
        $monthlyCollections = Payment::selectRaw('MONTH(payment_date) as month, SUM(amount) as total')
            ->whereYear('payment_date', $year);
        if ($empType && $empType !== 'all') {
            $monthlyCollections->whereHas('loan.user', fn ($q) => $q->where('employment_type', $empType));
        }
        $monthlyCollections = $monthlyCollections->groupBy('month')->pluck('total', 'month');

        // ── Loans by type for chart ──
        $loansByType = (clone $loanQuery)->selectRaw('loan_type, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('loan_type')
            ->get();

        // ── Loans by status for chart ──
        $loansByStatus = (clone $loanQuery)->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $data = [
            'financial' => [
                'beginning_balance' => $beginningBalance,
                'cash_on_hand' => $cashOnHand,
                'collectibles' => round($collectibles, 2),
                'total_collected_this_year' => $totalCollected,
                'total_collected_this_month' => $totalCollectedThisMonth,
                'year' => (int) $year,
            ],
            'members' => [
                'total' => (clone $memberQuery)->count(),
                'active' => (clone $memberQuery)->where('status', 'active')->count(),
                'new_this_month' => (clone $memberQuery)->where('created_at', '>=', now()->startOfMonth())->count(),
                'by_type' => $byType,
            ],
            'loans' => [
                'total_count' => $totalLoansCount,
                'total_amount' => $totalLoansAmount,
                'active_count' => $totalActiveCount,
                'active_amount' => $totalActiveAmount,
                'pending' => $pendingCount,
                'by_type' => $loansByType,
                'by_status' => $loansByStatus,
                'recent' => LoanResource::collection(
                    Loan::with('user')
                        ->when($empType && $empType !== 'all', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('employment_type', $empType)))
                        ->latest()->take(5)->get()
                ),
            ],
            'payments' => [
                'total_this_month' => $totalCollectedThisMonth,
                'count_this_month' => (clone $paymentQuery)->where('payment_date', '>=', now()->startOfMonth())->count(),
            ],
            'charts' => [
                'monthly_collections' => $monthlyCollections,
                'loans_by_type' => $loansByType,
            ],
            'shares' => [
                'total_this_year' => ShareCapital::where('year', $year)
                    ->when($empType && $empType !== 'all', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('employment_type', $empType)))
                    ->sum('amount'),
                'members_with_shares' => ShareCapital::where('year', $year)
                    ->when($empType && $empType !== 'all', fn ($q) => $q->whereHas('user', fn ($q2) => $q2->where('employment_type', $empType)))
                    ->distinct('user_id')->count('user_id'),
            ],
        ];

        return $this->success($data, 'Dashboard data retrieved.');
    }

    /**
     * Search registered PMBF users and show their current role.
     * HRIS-only employees (not yet registered) are not browsable here — admins
     * can still assign roles to them by entering the full employee_id (the
     * assignRole endpoint will look them up via the HRIS API).
     */
    public function userTypes(Request $request): JsonResponse
    {
        $search = trim($request->input('search', ''));

        $query = User::query()->where('status', 'active');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$search}%"])
                  ->orWhereRaw("CONCAT(last_name, ', ', first_name) like ?", ["%{$search}%"]);
            });
        }

        $users = $query->orderBy('last_name')->orderBy('first_name')->get();

        $data = $users->map(function ($user) {
            return [
                'employee_id'     => $user->employee_id,
                'first_name'      => $user->first_name,
                'last_name'       => $user->last_name,
                'middle_name'     => $user->middle_name,
                'full_name'       => trim("{$user->last_name}, {$user->first_name} " . ($user->middle_name ?? '')),
                'position'        => $user->position,
                'department'      => $user->department,
                'employment_type' => $user->employment_type,
                'is_registered'   => true,
                'user_id'         => $user->id,
                'role'            => $user->role,
                'email'           => $user->email,
            ];
        });

        return $this->success($data, 'User types retrieved.');
    }

    /**
     * Assign a role to a user. Creates a basic user record if not yet registered.
     */
    public function assignRole(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string',
            'role'        => 'required|string|in:member,receiver,loan_committee,chairperson,admin',
        ]);

        $employeeId = $request->input('employee_id');
        $role       = $request->input('role');

        // Find or create the pmbf user from HRIS data
        $user = User::where('employee_id', $employeeId)->first();

        if (!$user) {
            // Pull HRIS employee data via the HRIS API
            $emp = app(\App\Services\HrisService::class)->findByEmployeeId($employeeId);
            if (!$emp) {
                return $this->error('Employee not found in HRIS.', 404);
            }

            // Create a basic user record (they can complete registration via OTP later)
            $user = User::create([
                'employee_id'     => $emp->employee_id,
                'first_name'      => $emp->first_name,
                'middle_name'     => $emp->middle_name,
                'last_name'       => $emp->last_name,
                'suffix'          => $emp->suffix,
                'email'           => $emp->email,
                'mobile'          => $emp->mobile,
                'employment_type' => $emp->employment_type,
                'position'        => $emp->position,
                'department'      => $emp->department,
                'base_pay'        => $emp->base_pay,
                'take_home_pay'   => $emp->take_home_pay,
                'contract_start'  => $emp->contract_start,
                'contract_end'    => $emp->contract_end,
                'role'            => $role,
                'status'          => 'active',
                'password'        => bcrypt(\Illuminate\Support\Str::random(32)),
            ]);
        } else {
            $oldRole = $user->role;
            $user->update(['role' => $role]);

            ActivityLog::record([
                'admin_id'        => $request->user()->id,
                'action'          => 'role_assigned',
                'subject'         => $user->employee_id,
                'description'     => $user->full_name,
                'old_value'       => $oldRole,
                'new_value'       => $role,
                'employment_type' => $user->employment_type,
            ]);
        }

        if ($user->wasRecentlyCreated) {
            ActivityLog::record([
                'admin_id'        => $request->user()->id,
                'action'          => 'role_assigned',
                'subject'         => $user->employee_id,
                'description'     => $user->full_name,
                'old_value'       => null,
                'new_value'       => $role,
                'employment_type' => $user->employment_type,
            ]);
        }

        return $this->success([
            'employee_id' => $user->employee_id,
            'full_name'   => $user->full_name,
            'role'        => $user->role,
            'is_new'      => $user->wasRecentlyCreated,
        ], 'Role assigned successfully.');
    }
}
