<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\Configuration;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * Generate a new OTP code and send it via email.
     */
    public function generate(string $email, string $type = 'registration'): OtpCode
    {
        // Invalidate previous unused OTPs for this email and type
        OtpCode::where('email', $email)
            ->where('type', $type)
            ->where('used', false)
            ->update(['used' => true]);

        $length = (int) Configuration::getValue('otp_length', 6);
        $expiryMinutes = (int) Configuration::getValue('otp_expiry_minutes', 10);

        $code = str_pad(
            (string) random_int(0, (int) pow(10, $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT
        );

        $otp = OtpCode::create([
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        Mail::to($email)->send(new OtpMail($code, $type));

        return $otp;
    }

    /**
     * Verify an OTP code.
     */
    public function verify(string $email, string $code, string $type = 'registration'): bool
    {
        $otp = OtpCode::where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp) {
            return false;
        }

        $otp->update(['used' => true]);

        return true;
    }

    /**
     * Delete all expired OTP codes.
     */
    public function cleanup(): void
    {
        OtpCode::where('expires_at', '<', now())
            ->orWhere('used', true)
            ->delete();
    }
}
