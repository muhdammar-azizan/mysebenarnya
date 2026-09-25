<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    use HasFactory;

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
        'resolution_notes',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
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
}
