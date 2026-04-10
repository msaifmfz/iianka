<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laragear\WebAuthn\WebAuthnData;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Override;

#[Fillable(['name', 'login_id', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements WebAuthnAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, WebAuthnAuthentication;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Normalize login IDs for case-insensitive authentication.
     */
    protected function setLoginIdAttribute(?string $value): void
    {
        $this->attributes['login_id'] = filled($value)
            ? Str::lower(trim($value))
            : null;
    }

    /**
     * Returns displayable data to be used to create WebAuthn Credentials.
     */
    public function webAuthnData(): WebAuthnData
    {
        $identifier = $this->email
            ?? $this->login_id
            ?? 'user-'.$this->getKey();

        return WebAuthnData::make(
            $identifier,
            $this->name ?? $this->login_id ?? $identifier,
        );
    }

    /**
     * @return BelongsToMany<ConstructionSchedule, $this>
     */
    public function constructionSchedules(): BelongsToMany
    {
        return $this->belongsToMany(ConstructionSchedule::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<BusinessSchedule, $this>
     */
    public function businessSchedules(): BelongsToMany
    {
        return $this->belongsToMany(BusinessSchedule::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<InternalNotice, $this>
     */
    public function internalNotices(): BelongsToMany
    {
        return $this->belongsToMany(InternalNotice::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<CleaningDutyRule, $this>
     */
    public function cleaningDutyRules(): BelongsToMany
    {
        return $this->belongsToMany(CleaningDutyRule::class)->withTimestamps();
    }
}
