<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #333; margin: 0; padding: 30px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #1e40af; padding-bottom: 15px; }
        .header h1 { color: #1e40af; font-size: 20px; margin: 0; }
        .header p { color: #6b7280; margin: 5px 0 0; font-size: 11px; }
        .doc-title { text-align: center; font-size: 15px; font-weight: bold; color: #111827; margin-bottom: 4px; }
        .loan-id { text-align: center; font-size: 12px; color: #6b7280; margin-bottom: 18px; }
        .section { margin-bottom: 18px; }
        .section-title { background: #1e40af; color: #fff; padding: 6px 12px; font-size: 12px; font-weight: bold; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 4px 8px; vertical-align: top; }
        .info-table td:first-child { color: #6b7280; width: 22%; }
        .info-table td.val { font-weight: bold; }
        .sched { border: 1px solid #e5e7eb; }
        .sched th { background: #1e40af; color: #fff; padding: 6px 8px; font-size: 11px; text-align: right; }
        .sched th:first-child, .sched th:nth-child(2) { text-align: left; }
        .sched td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; font-size: 11px; text-align: right; }
        .sched td:first-child, .sched td:nth-child(2) { text-align: left; }
        .sched tbody tr:nth-child(even) { background: #f9fafb; }
        .sched tfoot td { font-weight: bold; border-top: 2px solid #1e40af; background: #eff6ff; padding: 7px 8px; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .pill-paid { background: #d1fae5; color: #065f46; }
        .pill-partial { background: #dbeafe; color: #1e40af; }
        .pill-overdue { background: #fee2e2; color: #991b1b; }
        .pill-upcoming { background: #fef3c7; color: #92400e; }
        .pill-scheduled { background: #f3f4f6; color: #6b7280; }
        .summary-grid { display: table; width: 100%; margin-top: 6px; }
        .summary-cell { display: table-cell; width: 33.33%; text-align: center; padding: 12px; }
        .summary-cell .val { font-size: 18px; font-weight: bold; }
        .summary-cell .lbl { font-size: 10px; color: #6b7280; }
        .footer { text-align: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF Financial Management System</h1>
        <p>PhilRice Mutual Benefit Fund</p>
    </div>

    <div class="doc-title">Payment Breakdown</div>
    <div class="loan-id">Ref. No. {{ $loan->reference_no }} &mdash; {{ $loan->loan_type }}</div>

    <!-- Borrower / Loan Info -->
    <div class="section">
        <table class="info-table">
            <tr>
                <td>Borrower</td>
                <td class="val">{{ $loan->user->last_name }}, {{ $loan->user->first_name }} ({{ $loan->user->employee_id }})</td>
                <td>Amount</td>
                <td class="val">&#x20B1;{{ number_format($loan->amount, 2) }}</td>
            </tr>
            <tr>
                <td>Interest Rate</td>
                @php
                    $rateMo = rtrim(rtrim(number_format((float) $loan->interest_rate, 3, '.', ''), '0'), '.');
                    $rateYr = rtrim(rtrim(number_format((float) $loan->interest_rate * 12, 2, '.', ''), '0'), '.');
                @endphp
                <td class="val">{{ $rateMo }}% / month ({{ $rateYr }}% / year)</td>
                <td>Term</td>
                <td class="val">{{ $loan->term_months }} months</td>
            </tr>
            <tr>
                <td>Monthly</td>
                <td class="val">&#x20B1;{{ number_format($loan->monthly_amortization, 2) }}</td>
                <td>Date Released</td>
                <td class="val">{{ $loan->released_at?->format('M d, Y') ?? 'Not yet released' }}</td>
            </tr>
        </table>
    </div>

    <!-- Amortization Schedule -->
    <div class="section">
        <div class="section-title">Amortization Schedule</div>
        <table class="sched">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Due Date</th>
                    <th>Amortization</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Balance</th>
                    <th style="text-align:center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($schedule as $row)
                <tr>
                    <td>{{ $row['period'] }}</td>
                    <td>{{ $row['due_date']?->format('M d, Y') ?? '-' }}</td>
                    <td>&#x20B1;{{ number_format($row['total_due'], 2) }}</td>
                    <td>&#x20B1;{{ number_format($row['principal'], 2) }}</td>
                    <td>&#x20B1;{{ number_format($row['interest'], 2) }}</td>
                    <td>&#x20B1;{{ number_format($row['balance'], 2) }}</td>
                    <td style="text-align:center;">
                        @php
                            $statusLabel = ['paid' => 'Paid', 'partial' => 'Partial', 'overdue' => 'Overdue', 'upcoming' => 'Upcoming', 'scheduled' => 'Scheduled'][$row['status']];
                        @endphp
                        <span class="pill pill-{{ $row['status'] }}">{{ $statusLabel }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">TOTAL</td>
                    <td>&#x20B1;{{ number_format($totalPayable, 2) }}</td>
                    <td>&#x20B1;{{ number_format($loan->amount, 2) }}</td>
                    <td>&#x20B1;{{ number_format($totalPayable - $loan->amount, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Totals -->
    <div class="section">
        <div class="section-title">Summary</div>
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

    <div class="footer">
        Generated on {{ now()->format('M d, Y h:i A') }} &mdash; PMBF Financial Management System
    </div>
</body>
</html>
