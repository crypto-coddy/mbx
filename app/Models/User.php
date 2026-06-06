<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\PublicUserIdGenerator;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'public_user_id',
        'name',
        'email',
        'phone',
        'password',
        'referral_code',
        'referred_by',
        'status',
        'kyc_status',
        'kyc_rejection_reason',
        'otp',
        'otp_expires_at',
        'phone_verified',
        'email_verified_flag',
        'fcm_token',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'otp_expires_at' => 'datetime',
            'phone_verified' => 'boolean',
            'email_verified_flag' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (blank($user->public_user_id)) {
                $user->public_user_id = PublicUserIdGenerator::generate();
            }
        });
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function profileMarketCharts(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            UserProfileAssetChart::class,
            UserProfile::class,
            'user_id',
            'user_profile_id',
            'id',
            'id'
        );
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function referralCommissionsEarned(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'beneficiary_user_id');
    }

    public function referralCommissionsGenerated(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'source_user_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function pushNotifications(): HasMany
    {
        return $this->hasMany(PushNotificationLog::class);
    }

    public function adminActivityLogs(): HasMany
    {
        return $this->hasMany(AdminActivityLog::class, 'admin_id');
    }

    public function isKycApproved(): bool
    {
        return $this->kyc_status === 'approved';
    }

    public function isAccountActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return list<string> */
    public function depositApprovalBlockers(): array
    {
        $blockers = [];

        if (! $this->isAccountActive()) {
            $blockers[] = 'Customer account must be active (current status: '.$this->status.').';
        }

        if (! $this->isKycApproved()) {
            $blockers[] = 'Customer KYC must be approved (current status: '.$this->kyc_status.').';
        }

        return $blockers;
    }

    public function canApproveDeposit(): bool
    {
        return $this->depositApprovalBlockers() === [];
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super_admin']);
    }

    /** Resolve a user from a phone number or email address (mobile / API login). */
    public static function findForLogin(string $identifier): ?self
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return static::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($identifier)])
                ->first();
        }

        return static::query()->where('phone', $identifier)->first();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->can('access_admin_panel');
    }
}
