<?php

namespace App\Models;

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Inquiry $inquiry): void {
            if (empty($inquiry->reference_no)) {
                $inquiry->reference_no = static::nextReferenceNo();
            }
        });
    }

    public static function nextReferenceNo(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('INQ-%d-%05d', $year, $count);
    }

    protected $fillable = [
        'reference_no',
        'submitted_by',
        'agency_id',
        'assigned_to',
        'title',
        'description',
        'source_url',
        'category',
        'status',
        'reviewed_by',
        'reviewed_at',
        'jurisdiction_accepted_at',
        'resolution_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => InquiryCategory::class,
            'status' => InquiryStatus::class,
            'reviewed_at' => 'datetime',
            'jurisdiction_accepted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(InquiryEvidence::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(InquiryActivityLog::class);
    }

    public function clarificationThreads(): HasMany
    {
        return $this->hasMany(ClarificationThread::class);
    }

    /**
     * True when assigned to an agency but that agency hasn't accepted
     * jurisdiction yet. Not a stored status — the design prototypes surface
     * this as a display-only overlay ("Awaiting Jurisdiction Review") on top
     * of the stored InquiryStatus::UnderInvestigation value.
     */
    public function isAwaitingJurisdiction(): bool
    {
        return $this->status === InquiryStatus::UnderInvestigation
            && $this->agency_id !== null
            && $this->jurisdiction_accepted_at === null;
    }

    /**
     * True when an open (unresolved) clarification thread exists for this
     * inquiry. Also not stored — mirrors the prototype's effStatus() overlay
     * ("Awaiting Clarification") computed from clarify-store.js thread state.
     */
    public function hasOpenClarification(): bool
    {
        return $this->clarificationThreads()->where('status', 'open')->exists();
    }
}
