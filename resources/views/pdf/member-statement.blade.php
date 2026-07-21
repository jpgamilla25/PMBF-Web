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
        .doc-sub { text-align: center; font-size: 11px; color: #6b7280; margin-bottom: 18px; }
        .section { margin-bottom: 18px; }
        .section-title { background: #1e40af; color: #fff; padding: 6px 12px; font-size: 12px; font-weight: bold; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 4px 8px; vertical-align: top; }
        .info-table td.lbl { color: #6b7280; width: 18%; }
        .info-table td.val { font-weight: bold; width: 32%; }
        .loan-head { background: #eff6ff; border: 1px solid #dbeafe; padding: 6px 10px; margin-bottom: 0; }
        .loan-head .ref { font-size: 12px; font-weight: bold; color: #1e40af; }
        .loan-head .meta { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .sched { border: 1px solid #e5e7eb; margin-bottom: 14px; }
        .sched th { background: #1e40af; color: #fff; padding: 6px 8px; font-size: 10px; text-align: right; }
        .sched th.l { text-align: left; }
        .sched td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; text-align: right; }
        .sched td.l { text-align: left; }
        .sched tbody tr:nth-child(even) { background: #f9fafb; }
        .sched tfoot td { font-weight: bold; border-top: 2px solid #1e40af; background: #eff6ff; padding: 6px 8px; }
        .empty { padding: 8px; font-size: 10px; color: #9ca3af; font-style: italic; border: 1px solid #e5e7eb; border-top: none; margin-bottom: 14px; }
        .summary-grid { display: table; width: 100%; margin-top: 6px; }
        .summary-cell { display: table-cell; width: 25%; text-align: center; padding: 12px 6px; }
        .summary-cell .val { font-size: 15px; font-weight: bold; }
        .summary-cell .lbl { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .note { font-size: 9px; color: #6b7280; font-style: italic; margin-top: 6px; }
        .footer { text-align: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF Financial Management System</h1>
        <p>PhilRice Mutual Benefit Fund</p>
    </div>

    <div class="doc-title">Statement of Payments</div>
    <div class="doc-sub">
        @if($s['filters']['from'] || $s['filters']['to'])
            Covering {{ $s['filters']['from'] ? \Illuminate\Support\Carbon::parse($s['filters']['from'])->format('M d, Y') : 'the beginning' }}
            to {{ $s['filters']['to'] ? \Illuminate\Support\Carbon::parse($s['filters']['to'])->format('M d, Y') : 'present' }}
        @else
            Covering all recorded payments
        @endif
        @if($s['filters']['loan_id']) &mdash; single loan only @endif
    </div>

    <!-- Member -->
    <div class="section">
        <div class="section-title">Member Information</div>
        <table class="info-table">
            <tr>
                <td class="lbl">Name</td>
                <td class="val">{{ $s['member']['full_name'] }}</td>
                <td class="lbl">Employee ID</td>
                <td class="val">{{ $s['member']['employee_id'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">Employment Type</td>
                <td class="val">{{ $s['member']['employment_type'] ?? '-' }}</td>
                <td class="lbl">Department</td>
                <td class="val">{{ $s['member']['department'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="lbl">Position</td>
                <td class="val">{{ $s['member']['position'] ?? '-' }}</td>
                <td class="lbl">Email</td>
                <td class="val">{{ $s['member']['email'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- Per-loan payment history -->
    <div class="section">
        <div class="section-title">Payment History by Loan</div>

        @forelse($s['loans'] as $loan)
            <div class="loan-head">
                <div class="ref">{{ $loan['reference_no'] }} &mdash; {{ ucwords(str_replace('_', ' ', $loan['loan_type'])) }}</div>
                <div class="meta">
                    Principal &#x20B1;{{ number_format($loan['principal'], 2) }}
                    &bull; Interest &#x20B1;{{ number_format($loan['interest'], 2) }}
                    &bull; Total Payable &#x20B1;{{ number_format($loan['total_payable'], 2) }}
                    &bull; {{ $loan['term_months'] }} mos @ {{ $loan['interest_rate'] }}%/mo
                    &bull; Released {{ $loan['released_at'] ? \Illuminate\Support\Carbon::parse($loan['released_at'])->format('M d, Y') : 'n/a' }}
                    &bull; Status: {{ ucwords(str_replace('_', ' ', $loan['status'])) }}
                </div>
            </div>

            @if(count($loan['payments']))
                <table class="sched">
                    <thead>
                        <tr>
                            <th class="l" style="width:16%;">Date</th>
                            <th class="l" style="width:20%;">Reference / DV No.</th>
                            <th class="l" style="width:18%;">Method</th>
                            <th style="width:23%;">Amount Paid</th>
                            <th style="width:23%;">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="l" colspan="4" style="font-style:italic;color:#6b7280;">Balance brought forward</td>
                            <td>&#x20B1;{{ number_format($loan['opening_balance'], 2) }}</td>
                        </tr>
                        @foreach($loan['payments'] as $p)
                        <tr>
                            <td class="l">{{ $p['date'] ? \Illuminate\Support\Carbon::parse($p['date'])->format('M d, Y') : '-' }}</td>
                            <td class="l">{{ $p['reference'] ?: '-' }}</td>
                            <td class="l">{{ $p['method'] ? ucwords(str_replace('_', ' ', $p['method'])) : '-' }}</td>
                            <td>&#x20B1;{{ number_format($p['amount'], 2) }}</td>
                            <td>&#x20B1;{{ number_format($p['balance'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="l" colspan="3">TOTAL SHOWN ({{ count($loan['payments']) }} payment{{ count($loan['payments']) === 1 ? '' : 's' }})</td>
                            <td>&#x20B1;{{ number_format($loan['paid_in_range'], 2) }}</td>
                            <td>&#x20B1;{{ number_format($loan['remaining'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="l" colspan="3">LIFETIME PAID / OUTSTANDING</td>
                            <td>&#x20B1;{{ number_format($loan['total_paid'], 2) }}</td>
                            <td>&#x20B1;{{ number_format($loan['remaining'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @else
                <div class="empty">
                    No payments recorded for this loan
                    @if($s['filters']['from'] || $s['filters']['to']) within the selected period @endif.
                    Outstanding balance: &#x20B1;{{ number_format($loan['remaining'], 2) }}
                </div>
            @endif
        @empty
            <div class="empty" style="border-top:1px solid #e5e7eb;">No loans on record for this member.</div>
        @endforelse
    </div>

    <!-- Overall summary -->
    <div class="section">
        <div class="section-title">Summary</div>
        <div class="summary-grid">
            <div class="summary-cell">
                <div class="val" style="color: #1e40af;">&#x20B1;{{ number_format($s['summary']['total_borrowed'], 2) }}</div>
                <div class="lbl">Total Borrowed</div>
            </div>
            <div class="summary-cell">
                <div class="val" style="color: #059669;">&#x20B1;{{ number_format($s['summary']['total_paid'], 2) }}</div>
                <div class="lbl">Total Paid</div>
            </div>
            <div class="summary-cell">
                <div class="val" style="color: #dc2626;">&#x20B1;{{ number_format($s['summary']['total_outstanding'], 2) }}</div>
                <div class="lbl">Total Outstanding</div>
            </div>
            <div class="summary-cell">
                <div class="val" style="color: #111827;">{{ $s['summary']['active_loans'] }}</div>
                <div class="lbl">Active Loans</div>
            </div>
        </div>
        <div class="note">
            Total Borrowed is the sum of loan principals ({{ $s['summary']['loan_count'] }} loan{{ $s['summary']['loan_count'] === 1 ? '' : 's' }});
            total payable including interest is &#x20B1;{{ number_format($s['summary']['total_payable'], 2) }}.
            Payments shown in the selected period total &#x20B1;{{ number_format($s['summary']['total_paid_in_range'], 2) }}.
        </div>
    </div>

    <!-- FMIS payroll deductions (informational, never mixed into loan totals) -->
    @if(count($s['payroll_deductions']))
    <div class="section">
        <div class="section-title">Payroll Deduction History (FMIS)</div>
        <table class="sched">
            <thead>
                <tr>
                    <th class="l" style="width:20%;">Period</th>
                    <th class="l" style="width:22%;">DV Number</th>
                    <th class="l" style="width:18%;">DV Date</th>
                    <th class="l" style="width:20%;">Fund</th>
                    <th style="width:20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($s['payroll_deductions'] as $d)
                <tr>
                    <td class="l">{{ $d['period'] }}</td>
                    <td class="l">{{ $d['dv_number'] ?: '-' }}</td>
                    <td class="l">{{ $d['dv_date'] ? \Illuminate\Support\Carbon::parse($d['dv_date'])->format('M d, Y') : '-' }}</td>
                    <td class="l">{{ $d['fund'] ?: '-' }}</td>
                    <td>&#x20B1;{{ number_format($d['amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="l" colspan="4">TOTAL DEDUCTIONS</td>
                    <td>&#x20B1;{{ number_format($s['payroll_total'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
        <div class="note">
            Payroll deductions are recorded per employee, per payroll period, and are not tied to a
            specific loan. They are shown for reference only and are excluded from the per-loan
            balances and the summary above.
        </div>
    </div>
    @endif

    <div class="footer">
        Generated on {{ $s['generated_at'] }} &mdash; PMBF Financial Management System<br>
        This statement is system-generated and reflects payments recorded as of the date above.
    </div>
</body>
</html>
