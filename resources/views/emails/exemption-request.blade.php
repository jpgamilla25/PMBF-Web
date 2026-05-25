<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .header { color: #1e40af; text-align: center; margin-bottom: 20px; }
        .detail-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .detail-table td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .detail-table td:first-child { font-weight: bold; color: #374151; width: 40%; }
        .detail-table td:last-child { color: #111827; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: bold; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .reason-box { background: #f9fafb; border-left: 4px solid #2563eb; padding: 12px 16px; margin: 16px 0; color: #374151; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="header">PMBF Financial Management System</h2>
        <h3 style="text-align: center; color: #374151;">New Loan Exemption Request</h3>

        <p>A new loan exemption request has been submitted and requires your review.</p>

        <table class="detail-table">
            <tr>
                <td>Employee Name</td>
                <td>{{ $employee->full_name }}</td>
            </tr>
            <tr>
                <td>Employee ID</td>
                <td>{{ $employee->employee_id }}</td>
            </tr>
            <tr>
                <td>Exemption Type</td>
                <td>
                    @if($exemption->type === 'below_minimum_pay')
                        Below Minimum Pay
                    @elseif($exemption->type === 'exceed_max_amount')
                        Exceed Maximum Loan Amount
                    @elseif($exemption->type === 'extend_term')
                        Extend Loan Term
                    @endif
                </td>
            </tr>
            <tr>
                <td>Loan Type</td>
                <td>{{ $exemption->loan_type }}</td>
            </tr>
            <tr>
                <td>Current Value</td>
                <td>&#8369;{{ number_format($exemption->current_value, 2) }}</td>
            </tr>
            <tr>
                <td>Required Value</td>
                <td>&#8369;{{ number_format($exemption->required_value, 2) }}</td>
            </tr>
            @if($exemption->requested_value)
            <tr>
                <td>Requested Value</td>
                <td>&#8369;{{ number_format($exemption->requested_value, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>Status</td>
                <td><span class="badge badge-pending">Pending</span></td>
            </tr>
        </table>

        <p style="font-weight: bold; color: #374151;">Reason:</p>
        <div class="reason-box">
            {{ $exemption->reason }}
        </div>

        <p style="text-align: center; color: #666;">Please log in to the PMBF admin panel to review this request.</p>

        <div class="footer">
            &copy; {{ date('Y') }} PMBF Financial Management System. All rights reserved.
        </div>
    </div>
</body>
</html>
