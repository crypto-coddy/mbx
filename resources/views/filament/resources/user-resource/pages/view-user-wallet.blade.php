<x-filament-panels::page>
    @php
        $wallet = $this->record->wallet;
        $profile = $this->record->profile;
        $txCount = \App\Models\Transaction::where('user_id', $this->record->id)->count();
    @endphp

    <x-filament::section class="mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $this->record->name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->record->phone }}
                    @if ($this->record->email)
                        · {{ $this->record->email }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    User ID {{ $this->record->id }} · {{ $txCount }} transaction(s)
                </p>
            </div>
            <div class="flex gap-2">
                <x-filament::badge color="gray">KYC: {{ $this->record->kyc_status }}</x-filament::badge>
                <x-filament::badge color="success">{{ $this->record->status }}</x-filament::badge>
            </div>
        </div>
    </x-filament::section>

    @if ($wallet)
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <x-filament::section class="!p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total balance</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">₹{{ number_format((float) $wallet->balance, 2) }}</p>
            </x-filament::section>
            <x-filament::section class="!p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Recharged</p>
                <p class="mt-1 text-lg font-bold text-success-600">₹{{ number_format((float) $wallet->recharged_balance, 2) }}</p>
            </x-filament::section>
            <x-filament::section class="!p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Reward</p>
                <p class="mt-1 text-lg font-bold">₹{{ number_format((float) $wallet->reward_balance, 2) }}</p>
            </x-filament::section>
            <x-filament::section class="!p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Withdrawable</p>
                <p class="mt-1 text-lg font-bold">₹{{ number_format((float) $wallet->withdrawableBalance(), 2) }}</p>
            </x-filament::section>
            <x-filament::section class="!p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total deposited</p>
                <p class="mt-1 text-lg font-bold">₹{{ number_format((float) $wallet->total_deposited, 2) }}</p>
            </x-filament::section>
            <x-filament::section class="!p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total withdrawn</p>
                <p class="mt-1 text-lg font-bold">₹{{ number_format((float) $wallet->total_withdrawn, 2) }}</p>
            </x-filament::section>
        </div>
    @else
        <x-filament::section class="mb-6">
            <p class="text-sm text-gray-500">No wallet record yet. Use <strong>Recharge wallet</strong> to add funds.</p>
        </x-filament::section>
    @endif

    <x-filament::section heading="Payout details" class="mb-6" description="Saved in the mobile app (Profile tab).">
        @if ($profile && (filled($profile->upi_id) || filled($profile->bank_name)))
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">UPI ID</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $profile->upi_id ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Bank name</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $profile->bank_name ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Account holder</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $profile->account_holder_name ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Account number</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $profile->account_number ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">IFSC</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $profile->ifsc_code ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Account type</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $profile->account_type ? ucfirst($profile->account_type) : '—' }}</p>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-500">No payout details saved yet.</p>
        @endif
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
