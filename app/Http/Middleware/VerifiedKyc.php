<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifiedKyc
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->kyc_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'KYC approval required to access this resource.',
                'errors' => ['kyc_status' => $user?->kyc_status ?? 'not_submitted'],
            ], 403);
        }

        return $next($request);
    }
}
