<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 560px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .header { color: #1e40af; text-align: center; margin-bottom: 20px; }
        .amount { font-size: 26px; font-weight: bold; color: #1e40af; text-align: center; padding: 15px; background: #eff6ff; border-radius: 8px; margin: 15px 0; }
        .notice { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 15px; margin: 15px 0; font-size: 14px; }
        .btn { display: inline-block; padding: 14px 32px; border-radius: 6px; font-weight: bold; font-size: 15px; text-decoration: none; margin: 8px; }
        .btn-approve { background: #16a34a; color: #fff; }
        .btn-decline { background: #dc2626; color: #fff; }
        .actions { text-align: center; margin: 25px 0; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
        td { padding: 8px 4px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="header">Co-Maker Consent Request</h2>

        <p>Hello <strong>{{ $loan->coMaker->full_name }}</strong>,</p>

        <p>
            <strong>{{ $loan->user->full_name }}</strong> ({{ $loan->user->employee_id }}) has applied for a loan
            and has selected you as their <strong>co-maker</strong>. Your consent is required before the application
            can proceed to the approvers.
        </p>

        <div class="amount">₱{{ number_format($loan->amount, 2) }}</div>

        <table width="100%" cellpadding="0" cellspacing="0">
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280; width: 45%;">Ref. No.</td>
                <td style="font-weight: bold;">{{ $loan->reference_no }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280; width: 45%;">Applicant</td>
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
                <td style="color: #6b7280;">Loan Amount</td>
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
        </div>

        <p style="text-align:center; font-size: 15px; font-weight: bold;">Do you agree to be the co-maker for this loan?</p>

        <div class="actions">
            <a href="{{ url('/co-maker/respond/' . $loan->co_maker_token . '/approve') }}" class="btn btn-approve">
                ✅ Yes, I Agree
            </a>
            <a href="{{ url('/co-maker/respond/' . $loan->co_maker_token . '/decline') }}" class="btn btn-decline">
                ❌ No, I Decline
            </a>
        </div>

        <p style="font-size:12px; color:#888; text-align:center;">
            These links are unique to you. Do not share this email. Links expire once used.
        </p>

        <div class="footer">
            &copy; {{ date('Y') }} PMBF Financial Management System
        </div>
    </div>
</body>
</html>
