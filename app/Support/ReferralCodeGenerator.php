<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class ReferralCodeGenerator
{
    public static function generate(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::withTrashed()->where('referral_code', $code)->exists());

        return $code;
    }
}
