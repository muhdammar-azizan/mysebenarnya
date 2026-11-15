<?php

namespace App\Models;

use App\Enums\AgencyStaffRole;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'agency_id',
        'agency_role',
        'phone',
        'profile_photo_path',
        'must_change_password',
        'notification_preferences',
        'locale',
    ];

    /**
     * Notification preference keys that default to enabled when a staff
     * member has never saved their Notification Preferences tab.
     */
    public const NOTIFICATION_PREFERENCE_KEYS = ['newInquiry', 'agencyRejects', 'agencyResolves', 'newAgency', 'slaReminder'];

    public const PUBLIC_NOTIFICATION_PREFERENCE_KEYS = ['statusChanged'];

    public const AGENCY_NOTIFICATION_PREFERENCE_KEYS = ['inquiryAssigned', 'consultRequested', 'slaReminder'];

    /**
     * The notification preference keys that apply to this user's role.
     *
     * @return list<string>
     */
    public function notificationPreferenceKeys(): array
    {
        return match (true) {
            $this->isMcmcStaff() => self::NOTIFICATION_PREFERENCE_KEYS,
            $this->isAgencyStaff() => self::AGENCY_NOTIFICATION_PREFERENCE_KEYS,
            default => self::PUBLIC_NOTIFICATION_PREFERENCE_KEYS,
        };
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'agency_role' => AgencyStaffRole::class,
            'must_change_password' => 'boolean',
            'last_active_at' => 'datetime',
            'notification_preferences' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Whether this user wants in-app notifications for the given preference
     * key. Unset keys default to true so existing staff keep receiving
     * notifications until they explicitly opt out.
     */
    public function wantsNotification(string $key): bool
    {
        return (bool) ($this->notification_preferences[$key] ?? true);
    }

    /**
     * Two-factor authentication is offered to MCMC and agency staff only.
     */
    public function canUseTwoFactor(): bool
    {
        return $this->isMcmcStaff() || $this->isAgencyStaff();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Consume a recovery code. Each code works once.
     */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals((string) $stored, $code)) {
                unset($codes[$index]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function inquiryDraft(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(InquiryDraft::class);
    }

    public function submittedInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'submitted_by');
    }

    public function assignedInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'assigned_to');
    }

    public function isPublic(): bool
    {
        return $this->role === UserRole::Public;
    }

    public function isMcmcStaff(): bool
    {
        return $this->role === UserRole::McmcStaff;
    }

    public function isAgencyStaff(): bool
    {
        return $this->role === UserRole::AgencyStaff;
    }

    public function isAgencyAdmin(): bool
    {
        return $this->isAgencyStaff() && $this->agency_role === AgencyStaffRole::Admin;
    }

    public function isAgencyReviewer(): bool
    {
        return $this->isAgencyStaff() && $this->agency_role === AgencyStaffRole::Reviewer;
    }

    /**
     * Build a unique, lowercase username (letters, digits and dots) from a
     * base string, adding a numeric suffix when the name is already taken.
     */
    public static function generateUsername(string $base): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9.]+/i', '.', trim($base)));
        $base = trim(preg_replace('/\.+/', '.', $base), '.') ?: 'staff';

        $username = $base;
        $i = 1;
        while (static::where('username', $username)->exists()) {
            $username = $base.(++$i);
        }

        return $username;
    }
}
