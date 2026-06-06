<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'wallet', 'roles']);

        return $this->success($user);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'date'],
            'address' => ['sometimes', 'string'],
            'city' => ['sometimes', 'string', 'max:100'],
            'state' => ['sometimes', 'string', 'max:100'],
            'pincode' => ['sometimes', 'string', 'max:20'],
            'country' => ['sometimes', 'string', 'max:100'],
        ]);

        $user = $request->user();

        if (isset($data['name'])) {
            $user->update(['name' => $data['name']]);
            unset($data['name']);
        }

        if ($data) {
            $user->profile()->updateOrCreate(['user_id' => $user->id], $data);
        }

        return $this->success($user->fresh()->load('profile'), 'Profile updated.');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:2048']]);
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);

        if ($profile->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars/'.$user->id, 'public');
        $url = Storage::disk('public')->url($path);

        $profile->update(['avatar_path' => $path, 'avatar_url' => $url]);

        return $this->success($profile->fresh(), 'Avatar uploaded.');
    }

    public function updateBankDetails(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'account_holder_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ifsc_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'account_type' => ['sometimes', 'nullable', 'in:savings,current'],
            'upi_id' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id]);
        $updates = [];

        if (array_key_exists('upi_id', $data)) {
            $updates['upi_id'] = filled($data['upi_id']) ? trim($data['upi_id']) : null;
        }

        if (array_key_exists('bank_name', $data)) {
            foreach (['bank_name', 'account_number', 'account_holder_name', 'ifsc_code'] as $field) {
                if (empty($data[$field])) {
                    return $this->error(
                        'All bank fields are required when saving a bank account.',
                        [$field => ['This field is required.']],
                        422
                    );
                }
            }
            $updates['bank_name'] = $data['bank_name'];
            $updates['account_number'] = $data['account_number'];
            $updates['account_holder_name'] = $data['account_holder_name'];
            $updates['ifsc_code'] = strtoupper($data['ifsc_code']);
            $updates['account_type'] = $data['account_type'] ?? 'savings';
        }

        if ($updates) {
            $profile->update($updates);
        }

        $profile->refresh();

        $hasBank = filled($profile->bank_name) && filled($profile->account_number)
            && filled($profile->account_holder_name) && filled($profile->ifsc_code);
        $hasUpi = filled($profile->upi_id);

        if (! $hasBank && ! $hasUpi) {
            return $this->error(
                'Add at least bank account details or a UPI ID for withdrawals.',
                null,
                422
            );
        }

        return $this->success($profile, 'Payout details saved.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return $this->error('Current password is incorrect.', ['current_password' => ['Incorrect password.']], 422);
        }

        $user->update(['password' => $data['password']]);

        return $this->success(null, 'Password changed.');
    }

    public function referralCode(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'referral_code' => $user->referral_code,
            'share_message' => "Join QuantX with my code: {$user->referral_code}",
            'referrals_count' => $user->referrals()->count(),
        ]);
    }
}
