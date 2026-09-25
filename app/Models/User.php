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
        'password',
        'role',
        'agency_id',
        'agency_role',
        'phone',
        'profile_photo_path',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
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
}
