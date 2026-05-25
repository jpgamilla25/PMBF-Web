<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 3px solid #059669; padding-bottom: 12px; }
        .header h1 { color: #059669; font-size: 16px; margin: 0 0 3px; }
        .header p { color: #6b7280; margin: 2px 0; font-size: 9px; }
        .filters { background: #f3f4f6; padding: 6px 10px; margin-bottom: 12px; font-size: 9px; color: #6b7280; }
        .summary-grid { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #e5e7eb; }
        .summary-cell { display: table-cell; text-align: center; padding: 8px; border-right: 1px solid #e5e7eb; }
        .summary-cell:last-child { border-right: none; }
        .summary-cell .val { font-size: 14px; font-weight: bold; color: #059669; }
        .summary-cell .lbl { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        .by-method { display: table; width: 100%; margin-bottom: 14px; }
        .by-method-cell { display: table-cell; padding: 6px 10px; border: 1px solid #e5e7eb; margin-right: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #059669; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .footer { text-align: center; margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF &mdash; Payments Report</h1>
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
            <div class="lbl">Total Payments</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_amount'], 2) }}</div>
            <div class="lbl">Total Amount</div>
        </div>
        @foreach($summary['by_method'] as $method => $info)
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($info['amount'], 2) }}</div>
            <div class="lbl">{{ ucwords(str_replace('_', ' ', $method)) }} ({{ $info['count'] }})</div>
        </div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>OR Number</th>
                <th>Date</th>
                <th>Borrower</th>
                <th>Emp. ID</th>
                <th>Emp. Type</th>
                <th>Loan #</th>
                <th>Loan Type</th>
                <th class="text-right">Amount</th>
                <th>Method</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p['or_number'] ?? '-' }}</td>
                <td>{{ $p['payment_date'] }}</td>
                <td>{{ $p['borrower'] }}</td>
                <td>{{ $p['employee_id'] }}</td>
                <td>{{ $p['employment_type'] }}</td>
                <td>{{ $p['loan_id'] }}</td>
                <td>{{ $p['loan_type'] }}</td>
                <td class="text-right">&#x20B1;{{ number_format($p['amount'], 2) }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $p['payment_method'])) }}</td>
                <td>{{ $p['recorder'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        PMBF Financial Management System &mdash; This is a system-generated report.
    </div>
</body>
</html>
