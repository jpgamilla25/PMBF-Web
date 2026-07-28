<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 16px; border-bottom: 3px solid #7c3aed; padding-bottom: 12px; }
        .header h1 { color: #7c3aed; font-size: 16px; margin: 0 0 3px; }
        .header p { color: #6b7280; margin: 2px 0; font-size: 9px; }
        .filters { background: #f3f4f6; padding: 6px 10px; margin-bottom: 12px; font-size: 9px; color: #6b7280; }
        .summary-grid { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #e5e7eb; }
        .summary-cell { display: table-cell; text-align: center; padding: 8px; border-right: 1px solid #e5e7eb; }
        .summary-cell:last-child { border-right: none; }
        .summary-cell .val { font-size: 14px; font-weight: bold; color: #7c3aed; }
        .summary-cell .lbl { font-size: 8px; color: #6b7280; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #7c3aed; color: #fff; padding: 4px 5px; text-align: right; font-size: 8px; }
        th:first-child, th:nth-child(2), th:nth-child(3), th:nth-child(4) { text-align: left; }
        td { padding: 3px 5px; border-bottom: 1px solid #f3f4f6; font-size: 8px; text-align: right; }
        td:first-child, td:nth-child(2), td:nth-child(3), td:nth-child(4) { text-align: left; }
        tr:nth-child(even) td { background: #f9fafb; }
        .footer { text-align: center; margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
        .total-row td { font-weight: bold; background: #f5f3ff; border-top: 2px solid #7c3aed; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF &mdash; Premium Contributions Report {{ $year }}</h1>
        <p>PhilRice Mutual Benefit Fund &mdash; Contract of Service Members</p>
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
            <div class="lbl">COS Members</div>
        </div>
        <div class="summary-cell">
            <div class="val">&#x20B1;{{ number_format($summary['total_amount'], 2) }}</div>
            <div class="lbl">Total Premium Contributions {{ $year }}</div>
        </div>
    </div>

    @php
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    @endphp

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Department</th>
                @foreach($months as $m)
                <th>{{ $m }}</th>
                @endforeach
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($data as $i => $row)
            @php $grandTotal += $row['total']; @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row['user']->employee_id }}</td>
                <td>{{ $row['user']->last_name }}, {{ $row['user']->first_name }}</td>
                <td>{{ $row['user']->department }}</td>
                @for($m = 1; $m <= 12; $m++)
                <td>{{ $row['monthly']->has($m) ? number_format($row['monthly'][$m]->amount, 2) : '-' }}</td>
                @endfor
                <td>&#x20B1;{{ number_format($row['total'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4">TOTAL</td>
                @for($m = 1; $m <= 12; $m++)
                <td>&#x20B1;{{ number_format($data->sum(fn($r) => optional($r['monthly']->get($m))->amount ?? 0), 2) }}</td>
                @endfor
                <td>&#x20B1;{{ number_format($grandTotal, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        PMBF Financial Management System &mdash; This is a system-generated report.
    </div>
</body>
</html>
