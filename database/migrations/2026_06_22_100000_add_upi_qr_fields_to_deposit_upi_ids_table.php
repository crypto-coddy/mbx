<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposit_upi_ids', function (Blueprint $table) {
            $table->string('payee_name')->nullable()->after('upi_id');
            $table->boolean('show_qr_code')->default(true)->after('payee_name');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_upi_ids', function (Blueprint $table) {
            $table->dropColumn(['payee_name', 'show_qr_code']);
        });
    }
};
