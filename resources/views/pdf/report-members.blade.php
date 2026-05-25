<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #333; margin: 0; padding: 20px; }
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
        th { background: #7c3aed; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .footer { text-align: center; margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PMBF &mdash; Members Report</h1>
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
            <div class="lbl">Total Members</div>
        </div>
        @foreach($summary['by_type'] as $type => $count)
        <div class="summary-cell">
            <div class="val">{{ $count }}</div>
            <div class="lbl">{{ $type ?? 'Unknown' }}</div>
        </div>
        @endforeach
        <div class="summary-cell">
            <div class="val">{{ $summary['active_loans'] }}</div>
            <div class="lbl">With Active Loans</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Employment Type</th>
                <th>Department</th>
                <th>Role</th>
                <th class="text-center">Total Loans</th>
                <th class="text-center">Active Loans</th>
                <th>Joined</th>
            </tr>
        </thead>
        <tbody>
            @foreach($members as $i => $m)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $m['employee_id'] }}</td>
                <td>{{ $m['name'] }}</td>
                <td>{{ $m['email'] }}</td>
                <td>{{ $m['employment_type'] }}</td>
                <td>{{ $m['department'] }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $m['role'])) }}</td>
                <td class="text-center">{{ $m['loans_count'] }}</td>
                <td class="text-center">{{ $m['active_loans'] }}</td>
                <td>{{ $m['joined_at'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        PMBF Financial Management System &mdash; This is a system-generated report.
    </div>
</body>
</html>
