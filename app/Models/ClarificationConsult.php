<?php

namespace App\Models;

use App\Enums\ConsultStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ClarificationConsult extends Model
{
    use HasFactory;

    protected $fillable = [
        'clarification_thread_id',
        'requested_by',
        'consulted_agency_id',
        'question',
        'status',
        'unread',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConsultStatus::class,
            'unread' => 'boolean',
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

    /**
     * The consulted agency answers via a normal thread message tagged with
     * this consult's agency, not a dedicated response field — mirrors
     * clarify-store.js's consultReply().
     */
    public function reply(User $user, string $text): void
    {
        if ($this->status === ConsultStatus::Ended || ! $this->thread->isActive()) {
            throw new RuntimeException('This consultation has ended.');
        }

        $this->thread->messages()->create([
            'user_id' => $user->id,
            'consult_agency_id' => $this->consulted_agency_id,
            'message' => $text,
        ]);

        $this->update(['status' => ConsultStatus::Responded, 'unread' => false]);
        $this->thread->update(['unread_by_mcmc' => true]);
    }

    public function end(User $mcmcUser): void
    {
        if ($this->status === ConsultStatus::Ended) {
            return;
        }

        $this->update(['status' => ConsultStatus::Ended, 'unread' => false]);

        $this->thread->messages()->create([
            'user_id' => $mcmcUser->id,
            'consult_agency_id' => $this->consulted_agency_id,
            'is_system' => true,
            'message' => 'Consultation with '.$this->consultedAgency->name.' ended by '.$mcmcUser->name.' (MCMC).',
        ]);
    }

    public function markRead(): void
    {
        if ($this->unread) {
            $this->update(['unread' => false]);
        }
    }
}
