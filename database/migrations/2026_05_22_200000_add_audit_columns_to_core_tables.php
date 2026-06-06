<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, bool> table => needs timestamps */
    private array $tables = [
        'users' => false,
        'user_profiles' => false,
        'kyc_documents' => false,
        'assets' => false,
        'trades' => false,
        'wallets' => false,
        'transactions' => false,
        'withdrawal_requests' => false,
        'referral_commissions' => false,
        'trade_settings' => false,
        'support_tickets' => false,
        'support_messages' => false,
        'user_profile_asset_charts' => false,
        'price_history' => true,
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $needsTimestamps) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $needsTimestamps): void {
                if ($needsTimestamps) {
                    if (! Schema::hasColumn($table, 'created_at')) {
                        $blueprint->timestamps();
                    }
                }

                if (! Schema::hasColumn($table, 'created_by')) {
                    $blueprint->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'updated_by')) {
                    $blueprint->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if (Schema::hasColumn($table, 'updated_by')) {
                    $blueprint->dropConstrainedForeignId('updated_by');
                }
                if (Schema::hasColumn($table, 'created_by')) {
                    $blueprint->dropConstrainedForeignId('created_by');
                }
            });
        }
    }
};
