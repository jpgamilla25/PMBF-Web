<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TicketReceivedMail;
use App\Mail\TicketResolvedMail;
use App\Models\SupportTicket;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Support tickets raised from the PMBF Assistant.
 *
 * Filing is open to signed-out users (the widget sits on the login page), so
 * the employee id and email come off the form. Everything else — listing,
 * resolving, the resolution email — is admin-only.
 */
class SupportTicketController extends Controller
{
    use ApiResponse;

    /**
     * POST /chatbot/tickets — file a ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        // Link the account when the employee id matches one, which is what
        // puts a real name beside the ticket in the admin list.
        $member = User::where('employee_id', $data['employee_id'])->first();

        $ticket = DB::transaction(function () use ($data, $member, $request) {
            return SupportTicket::create([
                'ticket_number' => SupportTicket::nextTicketNumber(),
                'user_id' => $member?->id ?? $request->user()?->id,
                'employee_id' => $data['employee_id'],
                'email' => $data['email'],
                'subject' => $data['subject'],
                'message' => $data['message'],
            ]);
        });

        // A mail outage must not lose the ticket — it is already saved.
        try {
            Mail::to($ticket->email)->send(new TicketReceivedMail($ticket));
        } catch (\Throwable $e) {
            Log::warning("Ticket {$ticket->ticket_number} saved but acknowledgement mail failed: {$e->getMessage()}");
        }

        return $this->success([
            'ticket_number' => $ticket->ticket_number,
            'email' => $ticket->email,
        ], "Ticket {$ticket->ticket_number} has been filed.", 201);
    }

    /**
     * GET /admin/tickets — filterable list.
     */
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::with(['user:id,first_name,last_name,employee_id', 'resolver:id,first_name,last_name'])
            ->search($request->input('search'))
            ->when(
                in_array($request->input('status'), ['open', 'resolved'], true),
                fn ($q) => $q->where('status', $request->input('status'))
            )
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest('id')
            ->paginate(min((int) $request->input('per_page', 15), 100))
            ->through(fn (SupportTicket $t) => $this->present($t));

        return $this->success([
            'tickets' => $tickets,
            'counts' => [
                'open' => SupportTicket::open()->count(),
                'resolved' => SupportTicket::where('status', 'resolved')->count(),
            ],
        ]);
    }

    /**
     * POST /admin/tickets/resolve — resolve one ticket or a whole selection.
     *
     * Bulk and single share a path so the notes, the email and the audit
     * fields cannot drift apart between them.
     */
    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Already-resolved tickets are skipped rather than re-emailed.
        $tickets = SupportTicket::with('user')
            ->whereIn('id', $data['ids'])
            ->where('status', 'open')
            ->get();

        if ($tickets->isEmpty()) {
            return $this->error('Nothing to resolve — those tickets are already resolved.', 409);
        }

        $emailed = 0;
        $failed = [];

        foreach ($tickets as $ticket) {
            $ticket->update([
                'status' => 'resolved',
                'resolution_notes' => $data['notes'] ?? null,
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            try {
                Mail::to($ticket->email)->send(new TicketResolvedMail($ticket));
                $ticket->update(['resolution_emailed' => true]);
                $emailed++;
            } catch (\Throwable $e) {
                // The ticket stays resolved; the flag records that the member
                // was not actually told, so it can be chased.
                Log::warning("Ticket {$ticket->ticket_number} resolved but mail failed: {$e->getMessage()}");
                $failed[] = $ticket->ticket_number;
            }
        }

        $message = $tickets->count() === 1
            ? "Ticket {$tickets->first()->ticket_number} resolved."
            : "{$tickets->count()} tickets resolved.";

        if ($failed) {
            $message .= ' Email could not be sent for: ' . implode(', ', $failed) . '.';
        }

        return $this->success([
            'resolved' => $tickets->count(),
            'emailed' => $emailed,
            'email_failed' => $failed,
        ], $message);
    }

    /**
     * POST /admin/tickets/{ticket}/reopen — undo a resolve.
     */
    public function reopen(SupportTicket $ticket): JsonResponse
    {
        if ($ticket->status === 'open') {
            return $this->error('That ticket is already open.', 409);
        }

        $ticket->update([
            'status' => 'open',
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_emailed' => false,
        ]);

        return $this->success(['id' => $ticket->id], "Ticket {$ticket->ticket_number} reopened.");
    }

    /** Shape one ticket for the admin table. */
    private function present(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'employee_id' => $ticket->employee_id,
            'member_name' => $ticket->member_name,
            'is_member' => (bool) $ticket->user_id,
            'email' => $ticket->email,
            'subject' => $ticket->subject,
            'message' => $ticket->message,
            'status' => $ticket->status,
            'resolution_notes' => $ticket->resolution_notes,
            'resolution_emailed' => $ticket->resolution_emailed,
            'resolved_by' => $ticket->resolver
                ? trim("{$ticket->resolver->first_name} {$ticket->resolver->last_name}")
                : null,
            'resolved_at' => $ticket->resolved_at?->toDateTimeString(),
            'created_at' => $ticket->created_at?->toDateTimeString(),
        ];
    }
}
