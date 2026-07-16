<?php

namespace App\Models;

use App\Modules\AuthApi\Models\SocialAccount;
use App\Modules\Crm\Models\CrmLead;
use App\Modules\Crm\Models\CrmTask;
use App\Modules\NotificationTemplates\Traits\HasDeviceTokens;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasDeviceTokens, HasFactory, HasPushSubscriptions, HasRoles;
    use Notifiable, SoftDeletes;

    protected $guard_name = 'web';

    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'avatar',
        'phone',
        'bio',
        'timezone',
        'locale',
        'phone_verified_at',
        'phone_verification_code',
        'otp_two_factor_enabled',
        'otp_two_factor_channel',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'otp_two_factor_enabled' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function setNameAttribute(?string $value): void
    {
        $parts = explode(' ', trim((string) $value), 2);
        $this->attributes['first_name'] = $parts[0] ?? null;
        $this->attributes['last_name'] = $parts[1] ?? null;
    }

    public function initials(): string
    {
        $first = strtoupper(substr((string) $this->first_name, 0, 1));
        $last = strtoupper(substr((string) $this->last_name, 0, 1));

        return $first.($last ?: '');
    }

    public function hasVerifiedPhone(): bool
    {
        return ! is_null($this->phone_verified_at);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->otp_two_factor_enabled;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return (bool) $this->otp_two_factor_enabled;
    }

    public function hasOtpTwoFactorEnabled(): bool
    {
        return (bool) $this->otp_two_factor_enabled;
    }

    public function hasEmailTwoFactorEnabled(): bool
    {
        return (bool) $this->otp_two_factor_enabled && $this->otp_two_factor_channel === 'email';
    }

    public function hasSmsTwoFactorEnabled(): bool
    {
        return (bool) $this->otp_two_factor_enabled && $this->otp_two_factor_channel === 'sms';
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members', 'user_id', 'workspace_id')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function assignedCrmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'assigned_to');
    }

    public function assignedCrmTasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'assigned_to');
    }
}
