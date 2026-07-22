<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ExemptionController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\MemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public (no auth) ──────────────────────────────────────

    // Mobile app assets (public)
    Route::get('app/assets', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\AppAsset::active()->orderBy('sort_order');
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }
        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'label' => $a->label,
                'url' => $a->url,
            ]),
        ]);
    });

    // Mobile app settings (public — needed before login)
    Route::get('app/settings', function () {
        $keys = \App\Models\Configuration::whereIn('group', [
            'mobile_app_general', 'mobile_app_features', 'mobile_app_security',
        ])->get();

        $settings = [];
        foreach ($keys as $config) {
            $value = $config->value;
            if ($config->type === 'boolean') $value = (bool) (int) $value;
            elseif ($config->type === 'number') $value = (int) $value;
            $settings[$config->key] = $value;
        }

        return response()->json(['success' => true, 'data' => $settings]);
    });

    // Loan PDF (token via query param for new-tab access)
    Route::get('loans/{loan}/pdf', [\App\Http\Controllers\Api\LoanPdfController::class, 'generate']);
    Route::get('loans/{loan}/breakdown/pdf', [\App\Http\Controllers\Api\LoanPdfController::class, 'breakdown']);
    Route::get('loans/{loan}/agreement/pdf', [\App\Http\Controllers\Api\LoanPdfController::class, 'agreement']);

    // Member payment statement PDF (token via query param for new-tab access)
    Route::get('member/statement/pdf', [\App\Http\Controllers\Api\MemberStatementController::class, 'pdf']);

    // Report PDFs (token via query param for new-tab access)
    Route::get('reports/loans/pdf',    [\App\Http\Controllers\Api\ReportController::class, 'loansPdf']);
    Route::get('reports/payments/pdf', [\App\Http\Controllers\Api\ReportController::class, 'paymentsPdf']);
    Route::get('reports/members/pdf',  [\App\Http\Controllers\Api\ReportController::class, 'membersPdf']);
    Route::get('reports/shares/pdf',   [\App\Http\Controllers\Api\ReportController::class, 'sharesPdf']);

    // Registration: ID → OTP → Done
    // Throttled: employee IDs are guessable, so OTP sends and code guesses
    // are rate limited per IP.
    Route::middleware('throttle:otp-send')->group(function () {
        Route::post('register/lookup', [AuthController::class, 'lookup']);
        Route::post('register/resend-otp', [AuthController::class, 'resendOtp']);
    });
    Route::middleware('throttle:otp-verify')->group(function () {
        Route::post('register/complete', [AuthController::class, 'completeRegistration']);
    });

    // Login via Email OTP: ID → OTP → Token
    Route::post('login/request-otp', [AuthController::class, 'loginRequestOtp'])
        ->middleware('throttle:otp-send');
    Route::post('login/verify-otp', [AuthController::class, 'loginVerifyOtp'])
        ->middleware('throttle:otp-verify');

    // Login via PIN (device-scoped, requires a trusted device)
    Route::post('login/pin-status', [AuthController::class, 'pinStatus'])
        ->middleware('throttle:pin-status');
    Route::post('login/pin', [AuthController::class, 'loginWithPin'])
        ->middleware('throttle:pin-attempt');
    Route::post('pin/reset/request', [AuthController::class, 'pinResetRequest'])
        ->middleware('throttle:otp-send');
    Route::post('pin/reset/confirm', [AuthController::class, 'pinResetConfirm'])
        ->middleware('throttle:otp-verify');

    // Login via QR Code (web generates, web polls)
    Route::post('login/qr-generate', [AuthController::class, 'qrGenerate']);
    Route::get('login/qr-status/{sessionToken}', [AuthController::class, 'qrStatus']);

    // ── Chatbot (works both authenticated and guest) ────────
    Route::post('chatbot/message', [ChatbotController::class, 'message']);
    Route::get('chatbot/suggestions', [ChatbotController::class, 'suggestions']);

    // Chatbot with auth context (optional — returns personalized data)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('chatbot/message/auth', [ChatbotController::class, 'message']);
        Route::get('chatbot/suggestions/auth', [ChatbotController::class, 'suggestions']);
    });

    // ── Authenticated ─────────────────────────────────────────

    Route::middleware('auth:sanctum')->group(function () {

        // Auth & Session
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('me/sync-hris', [AuthController::class, 'syncFromHris'])
            ->middleware('throttle:6,1');
        Route::get('trusted-devices', [AuthController::class, 'trustedDevices']);
        Route::post('revoke-trust', [AuthController::class, 'revokeTrust']);

        // PIN management
        Route::post('pin', [AuthController::class, 'setPin']);
        Route::delete('pin', [AuthController::class, 'removePin']);

        // QR approve (mobile app calls this while authenticated)
        Route::post('login/qr-approve', [AuthController::class, 'qrApprove']);

        // Command palette search (Ctrl+K)
        Route::get('search', \App\Http\Controllers\Api\SearchController::class)
            ->middleware('throttle:60,1');

        // Notifications
        Route::get('notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

        // Shares (member)
        Route::get('shares/my', [\App\Http\Controllers\Api\ShareController::class, 'myShares']);
        Route::post('shares/request-update', [\App\Http\Controllers\Api\ShareController::class, 'requestUpdate']);

        // Co-maker: view and respond to pending consent requests
        Route::get('co-maker/pending', [LoanController::class, 'pendingCoMaker']);
        Route::post('co-maker/{loan}/respond', [LoanController::class, 'respondCoMaker']);

        // Loans (all authenticated users)
        Route::prefix('loans')->group(function () {
            Route::get('/', [LoanController::class, 'index']);
            Route::post('/', [LoanController::class, 'store']);
            Route::get('types', [LoanController::class, 'types']);
            Route::post('check-eligibility', [LoanController::class, 'checkEligibility']);
            Route::get('co-makers', [LoanController::class, 'coMakers']);
            Route::get('stats', [LoanController::class, 'stats']);
            Route::post('verify-otp', [LoanController::class, 'verifyOtp']);
            Route::post('verify-pin', [LoanController::class, 'verifyPin'])
                ->middleware('throttle:pin-attempt');
            Route::post('resend-otp', [LoanController::class, 'resendOtp']);
            Route::get('{loan}', [LoanController::class, 'show']);
            Route::get('{loan}/schedule', [LoanController::class, 'schedule']);
            Route::post('{loan}/cancel', [LoanController::class, 'cancel']);
        });

        // Member
        Route::prefix('member')->group(function () {
            Route::get('profile', [MemberController::class, 'profile']);
            Route::get('dependents', [MemberController::class, 'dependents']);
            Route::post('dependents', [MemberController::class, 'storeDependent']);
            Route::delete('dependents/{dependent}', [MemberController::class, 'destroyDependent']);
            Route::get('claims', [MemberController::class, 'claims']);
            Route::post('claims', [MemberController::class, 'storeClaim']);
            Route::get('benefits', [MemberController::class, 'benefits']);
            // Payment statement (JSON preview; PDF lives in the public group above)
            Route::get('statement', [\App\Http\Controllers\Api\MemberStatementController::class, 'data']);
        });

        // Exemption requests (all authenticated users)
        Route::prefix('exemptions')->group(function () {
            Route::get('my', [ExemptionController::class, 'myRequests']);
            Route::post('/', [ExemptionController::class, 'store']);
        });

        // Approvals (staff + admin)
        Route::middleware('role:receiver,loan_committee,chairperson,admin')
            ->prefix('approvals')
            ->group(function () {
                Route::get('/', [ApprovalController::class, 'index']);
                Route::post('bulk-approve', [ApprovalController::class, 'bulkApprove']);
                Route::post('bulk-disapprove', [ApprovalController::class, 'bulkDisapprove']);
                Route::post('bulk-release', [ApprovalController::class, 'bulkRelease']);
                Route::get('{loan}', [ApprovalController::class, 'show']);
                Route::post('{loan}/approve', [ApprovalController::class, 'approve']);
                Route::post('{loan}/disapprove', [ApprovalController::class, 'disapprove']);
                Route::post('{loan}/release', [ApprovalController::class, 'release']);
            });

        // Admin
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [AdminController::class, 'dashboard']);
            Route::get('members', [AdminController::class, 'members']);
            Route::get('members/{user}', [AdminController::class, 'showMember']);
            Route::get('loans', [AdminController::class, 'loans']);
            Route::get('payments', [AdminController::class, 'payments']);
            Route::post('payments', [AdminController::class, 'storePayment']);
            Route::get('configurations', [AdminController::class, 'configurations']);
            Route::post('configurations', [AdminController::class, 'storeConfiguration']);
            Route::post('beginning-balance', [AdminController::class, 'setBeginningBalance']);
            // Reports (JSON + CSV)
            Route::get('reports/loans',             [\App\Http\Controllers\Api\ReportController::class, 'loans']);
            Route::get('reports/loans/csv',         [\App\Http\Controllers\Api\ReportController::class, 'loansCsv']);
            Route::get('reports/payments',          [\App\Http\Controllers\Api\ReportController::class, 'payments']);
            Route::get('reports/payments/csv',      [\App\Http\Controllers\Api\ReportController::class, 'paymentsCsv']);
            Route::get('reports/members',           [\App\Http\Controllers\Api\ReportController::class, 'members']);
            Route::get('reports/members/csv',       [\App\Http\Controllers\Api\ReportController::class, 'membersCsv']);
            Route::get('reports/shares',            [\App\Http\Controllers\Api\ReportController::class, 'shares']);
            Route::get('reports/shares/csv',        [\App\Http\Controllers\Api\ReportController::class, 'sharesCsv']);
            Route::get('reports/ledger',            [\App\Http\Controllers\Api\ReportController::class, 'ledger']);
            // Legacy summary report
            Route::get('reports', [AdminController::class, 'reports']);
            Route::get('exemptions', [ExemptionController::class, 'index']);
            Route::post('exemptions/{exemptionRequest}/approve', [ExemptionController::class, 'approve']);
            Route::post('exemptions/{exemptionRequest}/reject', [ExemptionController::class, 'reject']);

            // App Assets (logos, etc.)
            Route::post('app-assets/upload', function (\Illuminate\Http\Request $request) {
                $request->validate([
                    'file' => 'required|image|max:5120',
                    'type' => 'required|string|in:logo,splash,icon,banner',
                    'label' => 'nullable|string|max:100',
                ]);

                $file = $request->file('file');
                $path = $file->store('app-assets', 'public');

                $asset = \App\Models\AppAsset::create([
                    'type' => $request->type,
                    'label' => $request->label ?? ucfirst($request->type),
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'sort_order' => \App\Models\AppAsset::where('type', $request->type)->count(),
                ]);

                return response()->json([
                    'success' => true,
                    'data' => ['id' => $asset->id, 'type' => $asset->type, 'label' => $asset->label, 'url' => $asset->url],
                    'message' => 'Asset uploaded.',
                ], 201);
            });

            Route::delete('app-assets/{appAsset}', function (\App\Models\AppAsset $appAsset) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($appAsset->file_path);
                $appAsset->delete();
                return response()->json(['success' => true, 'message' => 'Asset deleted.']);
            });

            Route::get('app-assets', function (\Illuminate\Http\Request $request) {
                $query = \App\Models\AppAsset::orderBy('type')->orderBy('sort_order');
                if ($request->filled('type')) $query->ofType($request->type);
                return response()->json([
                    'success' => true,
                    'data' => $query->get()->map(fn ($a) => [
                        'id' => $a->id, 'type' => $a->type, 'label' => $a->label,
                        'file_name' => $a->file_name, 'url' => $a->url,
                        'file_size' => $a->file_size, 'is_active' => $a->is_active,
                    ]),
                ]);
            });

            // Shares (admin)
            Route::get('shares', [\App\Http\Controllers\Api\ShareController::class, 'index']);
            Route::post('shares', [\App\Http\Controllers\Api\ShareController::class, 'store']);
            Route::post('shares/bulk', [\App\Http\Controllers\Api\ShareController::class, 'bulkStore']);
            Route::post('shares/sync-from-fmis', [\App\Http\Controllers\Api\ShareController::class, 'syncFromFmis']);
            Route::get('shares/analytics', [\App\Http\Controllers\Api\ShareController::class, 'analytics']);
            Route::get('shares/members/{user}', [\App\Http\Controllers\Api\ShareController::class, 'memberShares']);

            // Loan Payments (FMIS payroll deductions)
            Route::get('loan-payments', [\App\Http\Controllers\Api\LoanPaymentController::class, 'index']);
            Route::get('loan-payments/analytics', [\App\Http\Controllers\Api\LoanPaymentController::class, 'analytics']);
            Route::get('loan-payments/members/{user}', [\App\Http\Controllers\Api\LoanPaymentController::class, 'memberPayments']);
            Route::post('loan-payments/sync-from-fmis', [\App\Http\Controllers\Api\LoanPaymentController::class, 'syncFromFmis']);
            Route::get('loan-payments/by-month', [\App\Http\Controllers\Api\LoanPaymentController::class, 'byMonth']);
            Route::get('shares/pending-requests', [\App\Http\Controllers\Api\ShareController::class, 'pendingRequests']);
            Route::post('shares/requests/{shareUpdateRequest}/approve', [\App\Http\Controllers\Api\ShareController::class, 'approveRequest']);
            Route::post('shares/requests/{shareUpdateRequest}/reject', [\App\Http\Controllers\Api\ShareController::class, 'rejectRequest']);

            // Scheduler monitor
            Route::get('schedule',          [\App\Http\Controllers\Api\AdminScheduleController::class, 'index']);
            Route::post('schedule/run',     [\App\Http\Controllers\Api\AdminScheduleController::class, 'run']);

            // User Type Management
            Route::get('activity-logs',       [AdminController::class, 'activityLogs']);
            Route::get('user-types',          [AdminController::class, 'userTypes']);
            Route::post('user-types/assign', [AdminController::class, 'assignRole']);

            // Import
            Route::post('import/loans', [ImportController::class, 'importLoans']);
            Route::post('import/benefits', [ImportController::class, 'importBenefits']);
            Route::post('import/payments', [ImportController::class, 'importPayments']);
            Route::get('import/template/loans', [ImportController::class, 'downloadLoanTemplate']);
            Route::get('import/template/benefits', [ImportController::class, 'downloadBenefitTemplate']);
            Route::get('import/template/payments', [ImportController::class, 'downloadPaymentTemplate']);
        });
    });
});
