<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 550px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .header { color: #d97706; text-align: center; margin-bottom: 20px; }
        .amount { font-size: 24px; font-weight: bold; color: #1e40af; text-align: center; padding: 15px; background: #f0f5ff; border-radius: 8px; margin: 15px 0; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
        .notice { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="header">⚠️ Co-Maker Notification</h2>

        <p>Hello <strong>{{ $loan->coMaker->full_name }}</strong>,</p>

        <p>
            You have been named as a <strong>co-maker</strong> for a loan application
            submitted by <strong>{{ $loan->user->full_name }}</strong> ({{ $loan->user->employee_id }}).
        </p>

        <div class="amount">₱{{ number_format($loan->amount, 2) }}</div>

        <table width="100%" cellpadding="8" style="font-size: 14px;">
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Ref. No.</td>
                <td style="font-weight: bold;">{{ $loan->reference_no }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Applicant</td>
                <td style="font-weight: bold;">{{ $loan->user->full_name }} ({{ $loan->user->employee_id }})</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Employment Type</td>
                <td>{{ $loan->user->employment_type }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Loan Type</td>
                <td style="font-weight: bold;">{{ $loan->loan_type }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Amount</td>
                <td style="font-weight: bold;">₱{{ number_format($loan->amount, 2) }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Term</td>
                <td>{{ $loan->term_months }} months</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Monthly Amortization</td>
                <td>₱{{ number_format($loan->monthly_amortization, 2) }}</td>
            </tr>
            <tr>
                <td style="color: #6b7280;">Purpose</td>
                <td>{{ $loan->purpose }}</td>
            </tr>
        </table>

        <div class="notice">
            <strong>⚠️ What this means:</strong><br>
            As a co-maker, you are guaranteeing this loan. If the borrower fails to make payments,
            you may be held responsible for the outstanding balance.
            <br><br>
            If you have any concerns, please contact the PMBF office or the loan applicant directly.
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} PMBF Financial Management System
        </div>
    </div>
</body>
</html>
