<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\OtpService;
use App\Services\UserProvisioningService;
use App\Support\ReferralCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends ApiController
{
    public function __construct(
        protected OtpService $otpService,
        protected UserProvisioningService $provisioning,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'referral_code' => ['nullable', 'string', 'exists:users,referral_code'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $referrer = null;
        if (! empty($data['referral_code'])) {
            $referrer = User::where('referral_code', $data['referral_code'])->first();
        }

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'referral_code' => ReferralCodeGenerator::generate(),
            'referred_by' => $referrer?->id,
            'status' => 'inactive',
        ]);

        $this->provisioning->provision($user, $referrer, grantReward: true);

        $this->otpService->send($user);

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return $this->success([
            'user' => $user->load(['profile', 'wallet', 'roles']),
            'token' => $token,
        ], 'Registration successful. Please verify your phone with OTP.', 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required_without_all:email,phone', 'nullable', 'string', 'max:255'],
            'phone' => ['required_without_all:email,login', 'nullable', 'string', 'max:20'],
            'email' => ['required_without_all:phone,login', 'nullable', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $identifier = trim($data['login'] ?? $data['email'] ?? $data['phone'] ?? '');

        if ($identifier === '') {
            return $this->error('Invalid credentials.', ['credentials' => ['Invalid phone/email or password.']], 401);
        }

        $user = User::findForLogin($identifier);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->error('Invalid credentials.', ['credentials' => ['Invalid phone/email or password.']], 401);
        }

        if (in_array($user->status, ['suspended', 'banned'], true)) {
            return $this->error('Account is not allowed to login.', ['status' => $user->status], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return $this->success([
            'user' => $user->load(['profile', 'wallet', 'roles']),
            'token' => $token,
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, 'Logged out.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'Logged out from all devices.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string']]);
        $user = $this->otpService->sendByPhone($data['phone']);

        if (! $user) {
            return $this->error('Phone number not found.', null, 404);
        }

        return $this->success(null, 'OTP sent to your phone.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->otpService->verifyByPhone($data['phone'], $data['otp']);

        if (! $user) {
            return $this->error('Invalid or expired OTP.', ['otp' => ['Invalid or expired OTP.']], 422);
        }

        $user->update(['password' => $data['password']]);

        return $this->success(null, 'Password reset successful.');
    }

    public function checkReferralCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $referrer = User::where('referral_code', $data['code'])->first(['id', 'name', 'referral_code']);

        if (! $referrer) {
            return $this->error('Invalid referral code.', null, 404);
        }

        return $this->success(['valid' => true, 'referrer' => $referrer]);
    }

    public function updateFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate(['fcm_token' => ['required', 'string']]);
        $request->user()->update(['fcm_token' => $data['fcm_token']]);

        return $this->success(null, 'FCM token updated.');
    }
}
