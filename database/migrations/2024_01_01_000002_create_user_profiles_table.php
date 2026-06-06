<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('avatar_path')->nullable();
            $table->string('avatar_url')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('bank_name')->nullable();
            $table->text('account_number')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->enum('account_type', ['savings', 'current'])->default('savings');
            $table->string('upi_id')->nullable();
            $table->string('aadhaar_number', 20)->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
