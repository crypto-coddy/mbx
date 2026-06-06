<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('document_type', [
                'aadhaar_front', 'aadhaar_back', 'pan_card',
                'bank_passbook', 'bank_statement', 'selfie', 'other',
            ]);
            $table->string('file_path');
            $table->string('file_url')->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'document_type']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
