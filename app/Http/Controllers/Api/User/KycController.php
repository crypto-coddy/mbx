<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\ApiController;
use App\Models\KycDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycController extends ApiController
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $documents = $user->kycDocuments()->latest()->get();

        return $this->success([
            'kyc_status' => $user->kyc_status,
            'kyc_rejection_reason' => $user->kyc_rejection_reason,
            'documents' => $documents,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', 'in:aadhaar_front,aadhaar_back,pan_card,bank_passbook,bank_statement,selfie,other'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $user = $request->user();
        $file = $request->file('file');
        $path = $file->store('kyc/'.$user->id, 'public');

        $document = KycDocument::create([
            'user_id' => $user->id,
            'document_type' => $data['document_type'],
            'file_path' => $path,
            'file_url' => Storage::disk('public')->url($path),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        if ($user->kyc_status === 'not_submitted') {
            $user->update(['kyc_status' => 'pending']);
        }

        return $this->success($document, 'Document uploaded.', 201);
    }

    public function deleteDocument(Request $request, int $id): JsonResponse
    {
        $document = KycDocument::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return $this->success(null, 'Document deleted.');
    }
}
