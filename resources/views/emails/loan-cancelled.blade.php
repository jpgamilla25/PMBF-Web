<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 550px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; }
        .amount { font-size: 24px; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin: 15px 0; background: #fef2f2; color: #991b1b; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="header" style="color: #dc2626;">Loan Application Cancelled</h2>

        <p>
            @if($cancelledBy === 'applicant')
                <strong>{{ $loan->user->full_name }}</strong> ({{ $loan->user->employee_id }}) has cancelled their loan application.
            @else
                A loan application has been cancelled by the administrator.
            @endif
        </p>

        <div class="amount">₱{{ number_format($loan->amount, 2) }}</div>

        <table width="100%" cellpadding="6" style="font-size: 13px; color: #6b7280;">
            <tr><td>Applicant</td><td style="font-weight: bold; color: #111;">{{ $loan->user->full_name }} ({{ $loan->user->employee_id }})</td></tr>
            <tr><td>Ref. No.</td><td style="font-weight: bold; color: #111;">{{ $loan->reference_no }}</td></tr>
            <tr><td>Loan Type</td><td>{{ $loan->loan_type }}</td></tr>
            <tr><td>Amount</td><td>₱{{ number_format($loan->amount, 2) }}</td></tr>
            <tr><td>Term</td><td>{{ $loan->term_months }} months</td></tr>
            <tr><td>Status</td><td style="color: #dc2626; font-weight: bold;">Cancelled</td></tr>
        </table>

        <div class="footer">
            &copy; {{ date('Y') }} PMBF Financial Management System
        </div>
    </div>
</body>
</html>
