<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 550px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; }
        .amount { font-size: 24px; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .approved { background: #d1fae5; color: #065f46; }
        .disapproved { background: #fee2e2; color: #991b1b; }
        .released { background: #dbeafe; color: #1e40af; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
        .remarks { background: #f9fafb; border-left: 4px solid #d1d5db; padding: 12px; margin: 15px 0; font-style: italic; color: #4b5563; }
    </style>
</head>
<body>
    <div class="container">
        @if($action === 'approved')
            <h2 class="header" style="color: #059669;">✓ Loan Approved</h2>
            <div class="amount approved">₱{{ number_format($loan->amount, 2) }}</div>
            <p>Your <strong>{{ $loan->loan_type }}</strong> application has been approved at the <strong>{{ ucfirst(str_replace('_', ' ', $level)) }}</strong> level.</p>

            @if($loan->status === 'approved')
                <p style="color: #059669; font-weight: bold; text-align: center;">
                    🎉 All approvals complete! Your loan is ready for release.
                </p>
            @else
                <p style="color: #6b7280;">Your application will now proceed to the next approval level.</p>
            @endif

        @elseif($action === 'disapproved')
            <h2 class="header" style="color: #dc2626;">✗ Loan Disapproved</h2>
            <div class="amount disapproved">₱{{ number_format($loan->amount, 2) }}</div>
            <p>Unfortunately, your <strong>{{ $loan->loan_type }}</strong> application has been disapproved.</p>

        @elseif($action === 'released')
            <h2 class="header" style="color: #1e40af;">💰 Loan Released!</h2>
            <div class="amount released">₱{{ number_format($loan->amount, 2) }}</div>
            <p>Your <strong>{{ $loan->loan_type }}</strong> has been released! Monthly amortization of <strong>₱{{ number_format($loan->monthly_amortization, 2) }}</strong> for {{ $loan->term_months }} months.</p>

        @elseif($action === 'co_maker_approved')
            <h2 class="header" style="color: #059669;">✓ Co-Maker Agreed</h2>
            <div class="amount approved">₱{{ number_format($loan->amount, 2) }}</div>
            <p>Your co-maker <strong>{{ $loan->coMaker?->full_name }}</strong> has agreed to your <strong>{{ $loan->loan_type }}</strong> application. Your loan will now be reviewed by the approvers.</p>

        @elseif($action === 'co_maker_declined')
            <h2 class="header" style="color: #dc2626;">✗ Co-Maker Declined</h2>
            <div class="amount disapproved">₱{{ number_format($loan->amount, 2) }}</div>
            <p>Unfortunately, your co-maker <strong>{{ $loan->coMaker?->full_name }}</strong> has declined to co-sign your <strong>{{ $loan->loan_type }}</strong> application. Please select a different co-maker and reapply.</p>
        @endif

        @if($remarks)
            <div class="remarks">
                <strong>Remarks:</strong> {{ $remarks }}
            </div>
        @endif

        <table width="100%" cellpadding="6" style="font-size: 13px; color: #6b7280; margin-top: 15px;">
            <tr><td>Ref. No.</td><td style="font-weight: bold; color: #111;">{{ $loan->reference_no }}</td></tr>
            <tr><td>Type</td><td>{{ $loan->loan_type }}</td></tr>
            <tr><td>Amount</td><td>₱{{ number_format($loan->amount, 2) }}</td></tr>
            <tr><td>Term</td><td>{{ $loan->term_months }} months</td></tr>
        </table>

        <p style="text-align: center; color: #6b7280; font-size: 13px; margin-top: 20px;">
            Log in to the PMBF system to view full details.
        </p>

        <div class="footer">
            &copy; {{ date('Y') }} PMBF Financial Management System
        </div>
    </div>
</body>
</html>
