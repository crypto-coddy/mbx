<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends ApiController
{
    public function __construct(protected OtpService $otpService) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string']]);
        $user = $this->otpService->sendByPhone($data['phone']);

        if (! $user) {
            return $this->error('Phone number not found.', null, 404);
        }

        return $this->success(null, 'OTP sent successfully.');
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $this->otpService->verifyByPhone($data['phone'], $data['otp']);

        if (! $user) {
            return $this->error('Invalid or expired OTP.', ['otp' => ['Invalid or expired OTP.']], 422);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return $this->success([
            'verified' => true,
            'user' => $user->load(['profile', 'wallet']),
            'token' => $token,
        ], 'Phone verified successfully.');
    }
}
