<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .code { font-size: 32px; font-weight: bold; color: #2563eb; letter-spacing: 8px; text-align: center; padding: 20px; background: #f0f5ff; border-radius: 8px; margin: 20px 0; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color: #1e40af; text-align: center;">PMBF Financial Management System</h2>

        @if($type === 'registration')
            <p>You are registering for a PMBF account. Please use the OTP code below to complete your registration:</p>
        @elseif($type === 'login')
            <p>Use the OTP code below to verify your login:</p>
        @elseif($type === 'loan_application')
            <p>You are submitting a loan application. Please use the OTP code below to confirm:</p>
        @endif

        <div class="code">{{ $code }}</div>

        <p style="text-align: center; color: #666;">This code will expire in 10 minutes.</p>
        <p style="text-align: center; color: #666;">If you did not request this, please ignore this email.</p>

        <div class="footer">
            &copy; {{ date('Y') }} PMBF Financial Management System. All rights reserved.
        </div>
    </div>
</body>
</html>
