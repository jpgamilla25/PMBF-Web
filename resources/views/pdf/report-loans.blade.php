<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 3px solid #1e40af; padding-bottom: 12px; }
        .header h1 { color: #1e40af; font-size: 16px; margin: 0 0 3px; }
        .header p { color: #6b7280; margin: 2px 0; font-size: 9px; }
        .filters { background: #f3f4f6; padding: 6px 10px; margin-bottom: 12px; font-size: 9px; color: #6b7280; }
        .summary-grid { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #e5e7eb; }
        .summary-cell { display: table-cell; text-align: center; padding: 8px; border-right: 1px solid #e5e7eb; }
        .summary-cell:last-child { border-right: none; }
        .summary-cell .val { font-size: 14px; font-weight: bold; color: #1e40af; }
        .summary-cell .lbl { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e40af; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .status { padding: 2px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .st-released { background: #d1fae5; color: #065f46; }
        .st-completed { background: #e0e7ff; color: #3730a3; }
        .st-pending { background: #fef3c7; color: #92400e; }
        .st-disapproved { background: #fee2e2; color: #991b1b; }
        .st-default { background: #f3f4f6; color: #374151; }
        .footer { text-align: center; margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF &mdash; Loans Report</h1>
        <p>PhilRice Mutual Benefit Fund &mdash; Financial Management System</p>
        <p>Generated: {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    @if(count($filters))
    <div class="filters">
        <strong>Filters:</strong>
        @foreach($filters as $key => $val)
            &nbsp;{{ ucwords(str_replace('_', ' ', $key)) }}: <strong>{{ $val }}</strong>
        @endforeach
    </div>
    @endif

    <div class="summary-grid">
        <div class="summary-cell">
            <div class="val">{{ $summary['total_count'] }}</div>
            <div class="lbl">Total Loans</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_amount'], 2) }}</div>
            <div class="lbl">Total Amount</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_paid'], 2) }}</div>
            <div class="lbl">Total Collected</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_released'], 2) }}</div>
            <div class="lbl">Active Released</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_completed'], 2) }}</div>
            <div class="lbl">Completed</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Applicant</th>
                <th>ID</th>
                <th>Type</th>
                <th>Emp. Type</th>
                <th class="text-right">Amount</th>
                <th>Rate</th>
                <th>Term</th>
                <th class="text-right">Monthly</th>
                <th class="text-right">Total Payable</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Balance</th>
                <th>Status</th>
                <th>Applied</th>
            </tr>
        </thead>
        <tbody>
            @foreach($loans as $l)
            @php
                $statusClass = match($l['status']) {
                    'released'  => 'st-released',
                    'completed' => 'st-completed',
                    'disapproved' => 'st-disapproved',
                    default => in_array($l['status'], ['pending', 'receiver_approved', 'committee_approved', 'chairperson_approved', 'admin_pending', 'co_maker_pending']) ? 'st-pending' : 'st-default',
                };
            @endphp
            <tr>
                <td>{{ $l['id'] }}</td>
                <td>{{ $l['applicant'] }}</td>
                <td>{{ $l['employee_id'] }}</td>
                <td>{{ $l['loan_type'] }}</td>
                <td>{{ $l['employment_type'] }}</td>
                <td class="text-right">&#x20B1;{{ number_format($l['amount'], 2) }}</td>
                <td>{{ $l['interest_rate'] }}%</td>
                <td>{{ $l['term_months'] }}mo</td>
                <td class="text-right">&#x20B1;{{ number_format($l['monthly_amortization'], 2) }}</td>
                <td class="text-right">&#x20B1;{{ number_format($l['total_payable'], 2) }}</td>
                <td class="text-right">&#x20B1;{{ number_format($l['total_paid'], 2) }}</td>
                <td class="text-right">&#x20B1;{{ number_format($l['remaining_balance'], 2) }}</td>
                <td><span class="status {{ $statusClass }}">{{ str_replace('_', ' ', $l['status']) }}</span></td>
                <td>{{ $l['applied_at'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        PMBF Financial Management System &mdash; This is a system-generated report.
    </div>
</body>
</html>
