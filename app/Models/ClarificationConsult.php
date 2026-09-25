<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClarificationConsult extends Model
{
    use HasFactory;

    protected $fillable = [
        'clarification_thread_id',
        'requested_by',
        'consulted_agency_id',
        'question',
        'response',
        'status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ClarificationThread::class, 'clarification_thread_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function consultedAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'consulted_agency_id');
    }
}
