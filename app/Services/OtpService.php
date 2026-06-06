<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(
        protected int $length = 6,
        protected int $expiryMinutes = 10,
    ) {}

    public function send(User $user): string
    {
        $otp = str_pad((string) random_int(0, 10 ** $this->length - 1), $this->length, '0', STR_PAD_LEFT);

        $user->update([
            'otp' => $otp,
            'otp_expires_at' => now()->addMinutes($this->expiryMinutes),
        ]);

        Log::info('OTP sent', ['phone' => $user->phone, 'otp' => app()->environment('local') ? $otp : '***']);

        return $otp;
    }

    public function sendByPhone(string $phone): ?User
    {
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return null;
        }

        $this->send($user);

        return $user;
    }

    public function verify(User $user, string $otp): bool
    {
        if (! $user->otp || ! $user->otp_expires_at) {
            return false;
        }

        if ($user->otp_expires_at->isPast()) {
            return false;
        }

        if (! hash_equals($user->otp, $otp)) {
            return false;
        }

        $user->update([
            'otp' => null,
            'otp_expires_at' => null,
            'phone_verified' => true,
            'status' => $user->status === 'inactive' ? 'active' : $user->status,
        ]);

        return true;
    }

    public function verifyByPhone(string $phone, string $otp): ?User
    {
        $user = User::where('phone', $phone)->first();

        if (! $user || ! $this->verify($user, $otp)) {
            return null;
        }

        return $user->fresh();
    }

    public function cleanExpired(): int
    {
        return User::whereNotNull('otp_expires_at')
            ->where('otp_expires_at', '<', now())
            ->update(['otp' => null, 'otp_expires_at' => null]);
    }
}
