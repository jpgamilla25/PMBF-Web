<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; margin: 0; padding: 30px; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 3px solid #1e40af; padding-bottom: 15px; }
        .header h1 { color: #1e40af; font-size: 20px; margin: 0; }
        .header p { color: #6b7280; margin: 5px 0 0; font-size: 11px; }
        .loan-id { text-align: center; font-size: 14px; color: #6b7280; margin-bottom: 20px; }
        .section { margin-bottom: 20px; }
        .section-title { background: #1e40af; color: #fff; padding: 6px 12px; font-size: 12px; font-weight: bold; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 5px 8px; vertical-align: top; }
        .info-table td:first-child { color: #6b7280; width: 40%; }
        .info-table td:last-child { font-weight: bold; }
        .data-table { border: 1px solid #e5e7eb; }
        .data-table th { background: #f3f4f6; padding: 6px 8px; text-align: left; font-size: 11px; border-bottom: 1px solid #e5e7eb; }
        .data-table td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
        .data-table tr:last-child td { border-bottom: none; }
        .amount-box { text-align: center; background: #eff6ff; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .amount-box .label { color: #6b7280; font-size: 11px; }
        .amount-box .value { font-size: 24px; font-weight: bold; color: #1e40af; }
        .summary-grid { display: table; width: 100%; }
        .summary-cell { display: table-cell; width: 33.33%; text-align: center; padding: 10px; }
        .summary-cell .val { font-size: 16px; font-weight: bold; }
        .summary-cell .lbl { font-size: 10px; color: #6b7280; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .status-released { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-disapproved { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #e0e7ff; color: #3730a3; }
        .status-default { background: #f3f4f6; color: #374151; }
        .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
        .approval-row td { padding: 4px 8px; }
        .approved { color: #059669; }
        .disapproved { color: #dc2626; }
        /* Approval signature block */
        .sig-grid { display: table; width: 100%; border-collapse: collapse; margin-top: 8px; }
        .sig-cell { display: table-cell; width: 25%; padding: 10px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .sig-level { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 4px; }
        .sig-name { font-size: 11px; font-weight: bold; color: #111827; margin-bottom: 2px; min-height: 16px; }
        .sig-date { font-size: 9px; color: #6b7280; margin-bottom: 6px; min-height: 12px; }
        .sig-status-approved { display: inline-block; background: #d1fae5; color: #065f46; font-size: 9px; font-weight: bold; padding: 2px 7px; border-radius: 8px; }
        .sig-status-disapproved { display: inline-block; background: #fee2e2; color: #991b1b; font-size: 9px; font-weight: bold; padding: 2px 7px; border-radius: 8px; }
        .sig-status-pending { display: inline-block; background: #f3f4f6; color: #6b7280; font-size: 9px; font-weight: bold; padding: 2px 7px; border-radius: 8px; }
        .sig-line { border-top: 1px solid #d1d5db; margin-top: 20px; margin-bottom: 4px; }
        .sig-label { font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF Financial Management System</h1>
        <p>PhilRice Mutual Benefit Fund</p>
    </div>

    <div class="loan-id">Ref. No. {{ $loan->reference_no }} &mdash; {{ $loan->loan_type }}</div>

    <div class="amount-box">
        <div class="label">Loan Amount</div>
        <div class="value">&#x20B1;{{ number_format($loan->amount, 2) }}</div>
    </div>

    <!-- Applicant Info -->
    <div class="section">
        <div class="section-title">Applicant Information</div>
        <table class="info-table">
            <tr>
                <td>Full Name</td>
                <td>{{ $loan->user->last_name }}, {{ $loan->user->first_name }} {{ $loan->user->middle_name }}</td>
            </tr>
            <tr>
                <td>Employee ID</td>
                <td>{{ $loan->user->employee_id }}</td>
            </tr>
            <tr>
                <td>Employment Type</td>
                <td>{{ $loan->user->employment_type }}</td>
            </tr>
            <tr>
                <td>Department</td>
                <td>{{ $loan->user->department }}</td>
            </tr>
            @if($loan->coMaker)
            <tr>
                <td>Co-Maker</td>
                <td>{{ $loan->coMaker->last_name }}, {{ $loan->coMaker->first_name }} ({{ $loan->coMaker->employee_id }})</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Loan Details -->
    <div class="section">
        <div class="section-title">Loan Details</div>
        <table class="info-table">
            <tr>
                <td>Loan Type</td>
                <td>{{ $loan->loan_type }}</td>
            </tr>
            <tr>
                <td>Amount</td>
                <td>&#x20B1;{{ number_format($loan->amount, 2) }}</td>
            </tr>
            <tr>
                <td>Interest Rate</td>
                <td>{{ $loan->interest_rate }}% / month</td>
            </tr>
            <tr>
                <td>Term</td>
                <td>{{ $loan->term_months }} months</td>
            </tr>
            <tr>
                <td>Monthly Amortization</td>
                <td>&#x20B1;{{ number_format($loan->monthly_amortization, 2) }}</td>
            </tr>
            <tr>
                <td>Total Payable</td>
                <td>&#x20B1;{{ number_format($totalPayable, 2) }}</td>
            </tr>
            <tr>
                <td>Purpose</td>
                <td>{{ $loan->purpose ?? '-' }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td>
                    @php
                        $statusClass = match($loan->status) {
                            'released' => 'status-released',
                            'pending', 'receiver_approved', 'committee_approved' => 'status-pending',
                            'disapproved' => 'status-disapproved',
                            'completed' => 'status-completed',
                            default => 'status-default',
                        };
                    @endphp
                    <span class="status {{ $statusClass }}">{{ str_replace('_', ' ', $loan->status) }}</span>
                </td>
            </tr>
            <tr>
                <td>Date Applied</td>
                <td>{{ $loan->applied_at?->format('M d, Y') ?? '-' }}</td>
            </tr>
            @if($loan->released_at)
            <tr>
                <td>Date Released</td>
                <td>{{ $loan->released_at->format('M d, Y') }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Payment Summary -->
    <div class="section">
        <div class="section-title">Payment Summary</div>
        <div class="summary-grid">
            <div class="summary-cell">
                <div class="val" style="color: #1e40af;">&#x20B1;{{ number_format($totalPayable, 2) }}</div>
                <div class="lbl">Total Payable</div>
            </div>
            <div class="summary-cell">
                <div class="val" style="color: #059669;">&#x20B1;{{ number_format($totalPaid, 2) }}</div>
                <div class="lbl">Total Paid</div>
            </div>
            <div class="summary-cell">
                <div class="val" style="color: #dc2626;">&#x20B1;{{ number_format($remaining, 2) }}</div>
                <div class="lbl">Remaining</div>
            </div>
        </div>
    </div>

    <!-- Approval Signature Block -->
    <div class="section">
        <div class="section-title">Approval</div>
        @php
            $approvalMap = $loan->approvals->keyBy('level');
            $levels = [
                'co_maker'      => 'Co-Maker',
                'admin'         => 'Admin',
                'receiver'      => 'Receiver',
                'loan_committee'=> 'Loan Committee',
                'chairperson'   => 'Chairperson',
                'release'       => 'Released By',
            ];
            // Only show levels that are in workflow (appeared in approvals or are standard)
            $workflowLevels = ['receiver', 'loan_committee', 'chairperson', 'release'];
            if ($approvalMap->has('co_maker')) array_unshift($workflowLevels, 'co_maker');
            if ($approvalMap->has('admin'))    array_splice($workflowLevels, 1, 0, ['admin']);
        @endphp

        <div class="sig-grid">
            @foreach($workflowLevels as $lvl)
            @php
                $a = $approvalMap->get($lvl);
                $statusClass = $a ? ($a->status === 'approved' ? 'sig-status-approved' : 'sig-status-disapproved') : 'sig-status-pending';
                $statusText  = $a ? ucfirst($a->status) : 'Pending';
            @endphp
            <div class="sig-cell">
                <div class="sig-level">{{ $levels[$lvl] }}</div>
                <div class="sig-name">{{ $a?->approver?->full_name ?? '' }}</div>
                <div class="sig-date">{{ $a?->acted_at?->format('M d, Y') ?? '' }}</div>
                <span class="{{ $statusClass }}">{{ $statusText }}</span>
                @if($a?->remarks)
                <div style="font-size:9px;color:#6b7280;margin-top:4px;font-style:italic;">"{{ $a->remarks }}"</div>
                @endif
                <div class="sig-line"></div>
                <div class="sig-label">Signature over Printed Name</div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Payment History -->
    @if($loan->payments->count())
    <div class="section">
        <div class="section-title">Payment History</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>OR Number</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loan->payments as $i => $payment)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $payment->payment_date->format('M d, Y') }}</td>
                    <td>&#x20B1;{{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->or_number ?? '-' }}</td>
                    <td>{{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Generated on {{ now()->format('M d, Y h:i A') }} &mdash; PMBF Financial Management System
    </div>
</body>
</html>
