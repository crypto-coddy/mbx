<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycManagementController extends ApiController
{
    public function __construct(protected AdminActivityLogger $activityLogger) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['kycDocuments', 'profile'])
            ->whereIn('kyc_status', ['pending', 'approved', 'rejected'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('kyc_status', $request->input('status'));
        }

        return $this->success($query->paginate($request->integer('per_page', 20)));
    }

    public function userDocuments(int $userId): JsonResponse
    {
        $documents = KycDocument::where('user_id', $userId)->with('reviewer:id,name')->get();

        return $this->success($documents);
    }

    public function viewDocument(int $documentId): JsonResponse
    {
        $document = KycDocument::findOrFail($documentId);
        $url = Storage::disk('public')->url($document->file_path);

        return $this->success(['url' => $url, 'document' => $document]);
    }

    public function approve(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $user->update([
            'kyc_status' => 'approved',
            'kyc_rejection_reason' => null,
        ]);

        KycDocument::where('user_id', $userId)->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

        $this->activityLogger->log(
            $request->user()->id,
            'kyc.approved',
            "Approved KYC for user #{$userId}",
            null,
            ['kyc_status' => 'approved'],
            $user,
            $request
        );

        return $this->success($user->fresh(), 'KYC approved.');
    }

    public function reject(Request $request, int $userId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        $user = User::findOrFail($userId);

        $user->update([
            'kyc_status' => 'rejected',
            'kyc_rejection_reason' => $data['reason'],
        ]);

        $this->activityLogger->log(
            $request->user()->id,
            'kyc.rejected',
            "Rejected KYC for user #{$userId}",
            null,
            ['reason' => $data['reason']],
            $user,
            $request
        );

        return $this->success($user->fresh(), 'KYC rejected.');
    }

    public function approveDocument(Request $request, int $documentId): JsonResponse
    {
        $document = KycDocument::findOrFail($documentId);
        $document->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return $this->success($document->fresh(), 'Document approved.');
    }

    public function rejectDocument(Request $request, int $documentId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        $document = KycDocument::findOrFail($documentId);

        $document->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return $this->success($document->fresh(), 'Document rejected.');
    }
}
