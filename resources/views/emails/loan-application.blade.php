<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 550px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .header { color: #1e40af; text-align: center; margin-bottom: 20px; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-label { color: #6b7280; font-size: 14px; }
        .detail-value { font-weight: bold; font-size: 14px; }
        .amount { font-size: 24px; font-weight: bold; color: #1e40af; text-align: center; padding: 15px; background: #f0f5ff; border-radius: 8px; margin: 15px 0; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .badge-pending { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="header">PMBF — New Loan Application</h2>

        <p>A new loan application requires your review:</p>

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
                <td><span class="badge badge-pending">{{ $loan->user->employment_type }}</span></td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Department</td>
                <td>{{ $loan->user->department }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Loan Type</td>
                <td style="font-weight: bold;">{{ $loan->loan_type }}</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Term</td>
                <td>{{ $loan->term_months }} months</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Interest Rate</td>
                @php
                    $rateMo = rtrim(rtrim(number_format((float) $loan->interest_rate, 3, '.', ''), '0'), '.');
                    $rateYr = rtrim(rtrim(number_format((float) $loan->interest_rate * 12, 2, '.', ''), '0'), '.');
                @endphp
                <td>{{ $rateMo }}% / month ({{ $rateYr }}% / year)</td>
            </tr>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="color: #6b7280;">Monthly Amortization</td>
                <td style="font-weight: bold;">₱{{ number_format($loan->monthly_amortization, 2) }}</td>
            </tr>
            <tr>
                <td style="color: #6b7280;">Purpose</td>
                <td>{{ $loan->purpose }}</td>
            </tr>
            @if($loan->coMaker)
            <tr>
                <td style="color: #6b7280;">Co-Maker</td>
                <td>{{ $loan->coMaker->full_name }} ({{ $loan->coMaker->employee_id }})</td>
            </tr>
            @endif
        </table>

        <p style="text-align: center; margin-top: 20px;">
            <span class="badge badge-pending">⏳ Pending {{ ucfirst(str_replace('_', ' ', $recipientRole)) }} Review</span>
        </p>

        <p style="text-align: center; color: #6b7280; font-size: 13px;">
            Please log in to the PMBF system to review and take action.
        </p>

        <div class="footer">
            &copy; {{ date('Y') }} PMBF Financial Management System
        </div>
    </div>
</body>
</html>
