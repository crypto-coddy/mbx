<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Api\ApiController;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->visibleInWalletHistory()
            ->latest();

        if (! empty($data['type']) && $data['type'] !== 'all') {
            $query->where('type', $data['type']);
        }

        return $this->success($query->paginate($data['per_page'] ?? 20));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::where('user_id', $request->user()->id)->findOrFail($id);

        return $this->success($transaction);
    }
}
