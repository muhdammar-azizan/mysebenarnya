<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClarificationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'clarification_thread_id',
        'user_id',
        'consult_agency_id',
        'is_system',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ClarificationThread::class, 'clarification_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consultAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'consult_agency_id');
    }

    /**
     * True when this message was sent by staff from a third-party agency
     * consulted by MCMC on someone else's case, not the case's own agency.
     */
    public function isFromConsultedAgency(): bool
    {
        return $this->consult_agency_id !== null && ! $this->is_system;
    }
}
