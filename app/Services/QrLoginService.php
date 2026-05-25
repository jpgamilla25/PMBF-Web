<?php

namespace App\Services;

use App\Models\QrLoginSession;
use App\Models\User;
use Illuminate\Support\Str;

class QrLoginService
{
    private const EXPIRY_MINUTES = 5;

    /**
     * Generate a new QR login session for the web to display.
     */
    public function createSession(): QrLoginSession
    {
        return QrLoginSession::create([
            'session_token' => Str::random(64),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);
    }

    /**
     * Check the status of a QR session (web polls this).
     * Returns the session with user if approved.
     */
    public function checkStatus(string $sessionToken): ?QrLoginSession
    {
        $session = QrLoginSession::where('session_token', $sessionToken)->first();

        if (!$session) {
            return null;
        }

        // Auto-expire
        if ($session->isExpired() && $session->status === 'pending') {
            $session->update(['status' => 'expired']);
        }

        return $session;
    }

    /**
     * Mobile app scans the QR and approves login.
     * Called from mobile app with auth token + session_token.
     */
    public function approveSession(string $sessionToken, User $user): bool
    {
        $session = QrLoginSession::where('session_token', $sessionToken)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return false;
        }

        $session->update([
            'user_id' => $user->id,
            'status' => 'approved',
        ]);

        return true;
    }

    /**
     * Cleanup expired sessions.
     */
    public function cleanup(): void
    {
        QrLoginSession::where('expires_at', '<', now())
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        QrLoginSession::where('created_at', '<', now()->subHours(1))->delete();
    }
}
