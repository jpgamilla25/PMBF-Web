<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PMBF — Co-Maker Response</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f4f6f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 40px; max-width: 520px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; }
        .icon { font-size: 60px; margin-bottom: 20px; }
        h1 { font-size: 22px; font-weight: bold; margin-bottom: 12px; }
        p { color: #555; font-size: 15px; line-height: 1.6; }
        .details { background: #f8f9fa; border-radius: 8px; padding: 16px; margin: 20px 0; text-align: left; font-size: 14px; }
        .details table { width: 100%; }
        .details td { padding: 6px 4px; }
        .details td:first-child { color: #888; width: 45%; }
        .details td:last-child { font-weight: bold; }
        .footer { margin-top: 24px; font-size: 12px; color: #aaa; }
        .approved { color: #16a34a; }
        .declined { color: #dc2626; }
        .invalid { color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        @if($status === 'approved')
            <div class="icon">✅</div>
            <h1 class="approved">Consent Confirmed</h1>
        @elseif($status === 'declined')
            <div class="icon">❌</div>
            <h1 class="declined">Consent Declined</h1>
        @else
            <div class="icon">⚠️</div>
            <h1 class="invalid">Link Invalid</h1>
        @endif

        <p>{{ $message }}</p>

        @if(isset($loan) && $status !== 'invalid')
            <div class="details">
                <table>
                    <tr>
                        <td>Applicant</td>
                        <td>{{ $loan->user->full_name }}</td>
                    </tr>
                    <tr>
                        <td>Loan Type</td>
                        <td>{{ $loan->loan_type }}</td>
                    </tr>
                    <tr>
                        <td>Amount</td>
                        <td>₱{{ number_format($loan->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Term</td>
                        <td>{{ $loan->term_months }} months</td>
                    </tr>
                </table>
            </div>
        @endif

        <div class="footer">
            &copy; {{ date('Y') }} PMBF Financial Management System<br>
            You may close this window.
        </div>
    </div>
</body>
</html>
