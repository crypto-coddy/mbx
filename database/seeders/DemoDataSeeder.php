<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Trade;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\TradeService;
use App\Services\UserProvisioningService;
use App\Services\WalletService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    /** Demo login password for all seeded customers. */
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $force = (bool) config('mbx.demo_seed_force', false);

        if (! Role::where('name', 'user')->exists()) {
            $this->call(RoleSeeder::class);
        }

        $existingDemo = User::where('phone', '9100000001')->first();
        if (
            ! $force
            && $existingDemo
            && Transaction::where('user_id', $existingDemo->id)->where('type', 'wallet_recharge')->exists()
        ) {
            $this->command?->warn('Demo users already seeded. Skipping duplicate activity. Use: php artisan mbx:seed-demo --force');

            return;
        }

        $assets = Asset::where('is_active', true)->get()->keyBy('symbol');
        if ($assets->isEmpty()) {
            $this->command->error('No assets found. Run: php artisan db:seed');

            return;
        }

        $admin = User::role('super_admin')->first()
            ?? User::where('email', env('SUPER_ADMIN_EMAIL', 'admin@mbxzone.com'))->first();

        $walletService = app(WalletService::class);
        $tradeService = app(TradeService::class);
        $provisioning = app(UserProvisioningService::class);

        $xau = $assets->get('XAU') ?? $assets->first();
        $xag = $assets->get('XAG') ?? $assets->first();
        $usdt = $assets->get('USDT') ?? $assets->first();

        $created = [];
        $firstDemoUserId = null;

        $this->command->info('Seeding 10 demo users with wallet activity and trades...');

        foreach ($this->demoUserPlans() as $index => $plan) {
            $num = $index + 1;
            $phone = '91000000'.str_pad((string) $num, 2, '0', STR_PAD_LEFT);

            $referredBy = $plan['referred_by'] ?? ($plan['use_first_referrer'] ?? false ? $firstDemoUserId : null);

            $user = User::query()->updateOrCreate(
                ['phone' => $phone],
                [
                    'name' => $plan['name'],
                    'email' => "demo{$num}@mbxzone.test",
                    'password' => self::DEMO_PASSWORD,
                    'referral_code' => 'DEMO'.str_pad((string) $num, 4, '0', STR_PAD_LEFT),
                    'referred_by' => $referredBy,
                    'status' => 'active',
                    'kyc_status' => 'approved',
                    'phone_verified' => true,
                ],
            );

            // Always reset demo password (plain text — User model hashes via cast).
            $user->password = self::DEMO_PASSWORD;
            $user->save();

            $provisioning->provision(
                $user,
                $referredBy ? User::find($referredBy) : null,
                grantReward: (bool) ($plan['signup_reward'] ?? false),
                markPhoneVerified: true,
            );

            if ($index === 0) {
                $firstDemoUserId = $user->id;
            }

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge(['country' => 'India'], $plan['profile'] ?? []),
            );

            foreach ($plan['recharges'] ?? [] as $recharge) {
                $walletService->adminRecharge(
                    $user,
                    (string) $recharge['amount'],
                    $recharge['note'] ?? 'Wallet recharge by admin (demo)',
                    $admin?->id,
                );
            }

            $dayOffset = $plan['days_ago'] ?? (10 - $index);

            foreach ($plan['trades'] ?? [] as $tradePlan) {
                $asset = match ($tradePlan['asset'] ?? 'XAU') {
                    'XAG' => $xag,
                    'USDT' => $usdt,
                    default => $xau,
                };

                $buy = $tradeService->buy($user, $asset->id, (string) $tradePlan['amount']);
                $trade = $buy['trade'];
                $this->backdateTrade($trade, $dayOffset);

                if (($tradePlan['close'] ?? null) === 'profit') {
                    $exitPrice = bcmul((string) $trade->price_at_trade, '1.04', 8);
                    $this->setAssetPrice($asset, $exitPrice);
                    $closed = $tradeService->sell($user, $trade->id);
                    $this->backdateTrade($closed['trade']->fresh(), max(1, $dayOffset - 1));
                } elseif (($tradePlan['close'] ?? null) === 'loss') {
                    $exitPrice = bcmul((string) $trade->price_at_trade, '0.96', 8);
                    $this->setAssetPrice($asset, $exitPrice);
                    $closed = $tradeService->sell($user, $trade->id);
                    $this->backdateTrade($closed['trade']->fresh(), max(1, $dayOffset - 1));
                }
                // open: leave position open
            }

            if ($plan['withdrawal'] ?? null) {
                $amount = (string) $plan['withdrawal']['amount'];
                $walletService->lockForWithdrawal($user, $amount);
                $withdrawal = WithdrawalRequest::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'currency' => 'INR',
                    'bank_details' => [
                        'upi_id' => $user->profile?->upi_id ?? 'demo@upi',
                    ],
                    'status' => $plan['withdrawal']['status'] ?? 'pending',
                ]);
                $walletService->recordWithdrawalEvent(
                    $user,
                    $withdrawal,
                    'pending',
                    'Withdrawal request submitted (demo)',
                );
            }

            $created[] = $user->fresh(['profile']);
            $this->command->line("  ✓ {$user->name} ({$phone}) — ₹{$user->wallet?->balance} balance");
        }

        $this->command->newLine();
        $this->command->info('Demo users ready. Login on mobile with phone + password "'.self::DEMO_PASSWORD.'":');
        foreach ($created as $u) {
            $open = Trade::where('user_id', $u->id)->where('status', 'open')->count();
            $closed = Trade::where('user_id', $u->id)->where('status', 'closed')->count();
            $this->command->line("  • {$u->phone} — open: {$open}, closed: {$closed}");
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function demoUserPlans(): array
    {
        return [
            [
                'name' => 'Demo Priya Sharma',
                'days_ago' => 14,
                'profile' => [
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'upi_id' => 'priya@demo-upi',
                    'bank_name' => 'HDFC Bank',
                    'account_holder_name' => 'Priya Sharma',
                    'ifsc_code' => 'HDFC0001234',
                ],
                'recharges' => [
                    ['amount' => 5000, 'note' => 'First wallet recharge — demo'],
                ],
                'trades' => [
                    ['asset' => 'XAU', 'amount' => 800, 'close' => 'profit'],
                    ['asset' => 'XAG', 'amount' => 300, 'close' => 'open'],
                ],
            ],
            [
                'name' => 'Demo Rahul Verma',
                'days_ago' => 12,
                'signup_reward' => true,
                'profile' => ['city' => 'Delhi', 'upi_id' => 'rahul@demo-upi'],
                'recharges' => [
                    ['amount' => 2500, 'note' => 'Admin recharge after KYC'],
                ],
                'trades' => [
                    ['asset' => 'XAU', 'amount' => 400, 'close' => 'loss'],
                    ['asset' => 'USDT', 'amount' => 150, 'close' => 'profit'],
                ],
            ],
            [
                'name' => 'Demo Ananya Patel',
                'days_ago' => 11,
                'profile' => ['city' => 'Ahmedabad', 'upi_id' => 'ananya@demo-upi'],
                'recharges' => [
                    ['amount' => 8000, 'note' => 'VIP customer recharge'],
                    ['amount' => 2000, 'note' => 'Top-up before gold trade'],
                ],
                'trades' => [
                    ['asset' => 'XAU', 'amount' => 1200, 'close' => 'profit'],
                    ['asset' => 'XAU', 'amount' => 600, 'close' => 'profit'],
                    ['asset' => 'XAG', 'amount' => 500, 'close' => 'profit'],
                ],
            ],
            [
                'name' => 'Demo Vikram Singh',
                'days_ago' => 10,
                'profile' => ['city' => 'Jaipur', 'upi_id' => 'vikram@demo-upi'],
                'recharges' => [['amount' => 3500]],
                'trades' => [
                    ['asset' => 'XAG', 'amount' => 450, 'close' => 'open'],
                    ['asset' => 'USDT', 'amount' => 200, 'close' => 'open'],
                ],
            ],
            [
                'name' => 'Demo Meera Iyer',
                'days_ago' => 9,
                'signup_reward' => true,
                'profile' => ['city' => 'Bengaluru', 'upi_id' => 'meera@demo-upi'],
                'recharges' => [['amount' => 4200]],
                'trades' => [
                    ['asset' => 'XAU', 'amount' => 900, 'close' => 'profit'],
                ],
            ],
            [
                'name' => 'Demo Arjun Nair',
                'days_ago' => 8,
                'profile' => ['city' => 'Kochi', 'bank_name' => 'SBI', 'ifsc_code' => 'SBIN0000456'],
                'recharges' => [['amount' => 6000]],
                'trades' => [
                    ['asset' => 'XAU', 'amount' => 1500, 'close' => 'open'],
                    ['asset' => 'XAG', 'amount' => 700, 'close' => 'loss'],
                ],
            ],
            [
                'name' => 'Demo Kavya Reddy',
                'days_ago' => 7,
                'profile' => ['city' => 'Hyderabad', 'upi_id' => 'kavya@demo-upi'],
                'recharges' => [['amount' => 3000]],
                'trades' => [
                    ['asset' => 'USDT', 'amount' => 250, 'close' => 'profit'],
                    ['asset' => 'XAU', 'amount' => 350, 'close' => 'open'],
                ],
            ],
            [
                'name' => 'Demo Rohan Gupta',
                'days_ago' => 6,
                'profile' => ['city' => 'Kolkata', 'upi_id' => 'rohan@demo-upi'],
                'recharges' => [
                    ['amount' => 5500],
                    ['amount' => 1500, 'note' => 'Bonus recharge'],
                ],
                'trades' => [
                    ['asset' => 'XAU', 'amount' => 1000, 'close' => 'profit'],
                    ['asset' => 'XAG', 'amount' => 400, 'close' => 'profit'],
                    ['asset' => 'XAU', 'amount' => 500, 'close' => 'open'],
                ],
            ],
            [
                'name' => 'Demo Sneha Joshi',
                'days_ago' => 5,
                'use_first_referrer' => true,
                'signup_reward' => true,
                'profile' => ['city' => 'Pune', 'upi_id' => 'sneha@demo-upi'],
                'recharges' => [['amount' => 2800]],
                'trades' => [
                    ['asset' => 'XAG', 'amount' => 320, 'close' => 'profit'],
                ],
            ],
            [
                'name' => 'Demo Karan Malhotra',
                'days_ago' => 3,
                'profile' => [
                    'city' => 'Chandigarh',
                    'upi_id' => 'karan@demo-upi',
                    'account_holder_name' => 'Karan Malhotra',
                ],
                'recharges' => [['amount' => 7500]],
                'trades' => [
                    ['asset' => 'XAU', 'amount' => 2000, 'close' => 'profit'],
                    ['asset' => 'USDT', 'amount' => 300, 'close' => 'loss'],
                ],
                'withdrawal' => ['amount' => 500, 'status' => 'pending'],
            ],
        ];
    }

    private function setAssetPrice(Asset $asset, string $price): void
    {
        $asset->update([
            'live_price' => $price,
            'admin_price' => $price,
            'admin_override_active' => true,
            'price_updated_at' => now(),
        ]);
    }

    private function backdateTrade(Trade $trade, int $daysAgo): void
    {
        $at = now()->subDays($daysAgo)->subHours(random_int(1, 8));

        $trade->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
            'closed_at' => $trade->status === 'closed' ? $at->copy()->addHours(2) : null,
        ])->saveQuietly();

        Transaction::query()
            ->where('referenceable_type', Trade::class)
            ->where('referenceable_id', $trade->id)
            ->update(['created_at' => $at, 'updated_at' => $at]);
    }
}
