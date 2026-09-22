<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 550px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; }
        .ticket { font-size: 22px; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin: 15px 0; background: #f0fdf4; color: #166534; letter-spacing: 1px; }
        .quote { background: #f9fafb; border-left: 3px solid #d1d5db; padding: 12px 15px; margin: 15px 0; color: #374151; font-size: 14px; white-space: pre-wrap; }
        .notes { background: #f0fdf4; border-left: 3px solid #22c55e; padding: 12px 15px; margin: 15px 0; color: #14532d; font-size: 14px; white-space: pre-wrap; }
        .footer { font-size: 12px; color: #888; margin-top: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="header" style="color: #16a34a;">Your ticket has been resolved</h2>

        <p>Hello{{ $ticket->user ? ' ' . $ticket->user->first_name : '' }},</p>

        <p>The PMBF office has resolved the concern you raised.</p>

        <div class="ticket">{{ $ticket->ticket_number }}</div>

        <table width="100%" cellpadding="6" style="font-size: 13px; color: #6b7280;">
            <tr><td>Subject</td><td style="font-weight: bold; color: #111;">{{ $ticket->subject }}</td></tr>
            <tr><td>Filed on</td><td>{{ $ticket->created_at->format('M d, Y g:i A') }}</td></tr>
            <tr><td>Resolved on</td><td>{{ $ticket->resolved_at?->format('M d, Y g:i A') }}</td></tr>
            <tr><td>Status</td><td style="color: #16a34a; font-weight: bold;">Resolved</td></tr>
        </table>

        <p style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">What you told us:</p>
        <div class="quote">{{ $ticket->message }}</div>

        @if($ticket->resolution_notes)
            <p style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">How it was resolved:</p>
            <div class="notes">{{ $ticket->resolution_notes }}</div>
        @endif

        <p style="font-size: 13px; color: #6b7280;">
            If this is still not sorted out, please file a new ticket through the PMBF Assistant and quote
            {{ $ticket->ticket_number }}.
        </p>

        <div class="footer">
            This is an automated message from the PMBF system. Please do not reply to this email.
        </div>
    </div>
</body>
</html>
