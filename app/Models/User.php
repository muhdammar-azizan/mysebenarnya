<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'must_change_password' => 'boolean',
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
}
