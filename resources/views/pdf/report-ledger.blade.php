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
        th.num, td.num { text-align: right; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9px; }
        tr:nth-child(even) td { background: #f9fafb; }
        tfoot td { font-weight: bold; border-top: 2px solid #1e40af; background: #eff6ff; }
        .footer { text-align: center; margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF &mdash; Loan Ledger</h1>
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
            <div class="val">{{ $summary['member_count'] }}</div>
            <div class="lbl">Members</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_previous'], 2) }}</div>
            <div class="lbl">Previous Loan Bal.</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_balance'], 2) }}</div>
            <div class="lbl">Total Balance</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_paid'], 2) }}</div>
            <div class="lbl">Total Collected</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="num" style="width:36px;">No.</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Division</th>
                <th>Type</th>
                <th class="num">Previous Loan Bal.</th>
                <th class="num">Current Balance</th>
                <th class="num">Loans</th>
            </tr>
        </thead>
        <tbody>
            @foreach($members as $i => $m)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ $m['employee_id'] }}</td>
                <td>{{ $m['full_name'] }}</td>
                <td>{{ $m['division'] ?? '-' }}</td>
                <td>{{ $m['employment_type'] }}</td>
                <td class="num">&#x20B1;{{ number_format($m['previous_loan_balance'], 2) }}</td>
                <td class="num">&#x20B1;{{ number_format($m['balance'], 2) }}</td>
                <td class="num">{{ $m['loans']->count() }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">TOTAL</td>
                <td class="num">&#x20B1;{{ number_format($summary['total_previous'], 2) }}</td>
                <td class="num">&#x20B1;{{ number_format($summary['total_balance'], 2) }}</td>
                <td class="num">{{ $summary['loan_count'] }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generated on {{ now()->format('M d, Y h:i A') }} &mdash; PMBF Financial Management System
    </div>
</body>
</html>
