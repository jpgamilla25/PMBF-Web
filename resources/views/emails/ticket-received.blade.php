<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 550px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; }
        .ticket { font-size: 22px; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin: 15px 0; background: #eff6ff; color: #1e40af; letter-spacing: 1px; }
        .quote { background: #f9fafb; border-left: 3px solid #d1d5db; padding: 12px 15px; margin: 15px 0; color: #374151; font-size: 14px; white-space: pre-wrap; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="header" style="color: #2563eb;">We received your ticket</h2>

        <p>Hello{{ $ticket->user ? ' ' . $ticket->user->first_name : '' }},</p>

        <p>Your concern has been logged with the PMBF office. Please keep this reference number — quote it in any follow-up.</p>

        <div class="ticket">{{ $ticket->ticket_number }}</div>

        <table width="100%" cellpadding="6" style="font-size: 13px; color: #6b7280;">
            <tr><td>Employee ID</td><td style="font-weight: bold; color: #111;">{{ $ticket->employee_id }}</td></tr>
            <tr><td>Subject</td><td style="font-weight: bold; color: #111;">{{ $ticket->subject }}</td></tr>
            <tr><td>Filed on</td><td>{{ $ticket->created_at->format('M d, Y g:i A') }}</td></tr>
            <tr><td>Status</td><td style="color: #2563eb; font-weight: bold;">Open</td></tr>
        </table>

        <p style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">What you told us:</p>
        <div class="quote">{{ $ticket->message }}</div>

        <p style="font-size: 13px; color: #6b7280;">
            You will get another email the moment this is resolved. No action is needed from you in the meantime.
        </p>

        <div class="footer">
            This is an automated message from the PMBF system. Please do not reply to this email.
        </div>
    </div>
</body>
</html>
