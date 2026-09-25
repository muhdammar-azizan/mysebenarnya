<?php

namespace App\Models;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationStatus;
use App\Enums\ClarificationTopic;
use App\Enums\ConsultStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class ClarificationThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_id',
        'opened_by',
        'subject',
        'topic',
        'priority',
        'status',
        'unread_by_agency',
        'unread_by_mcmc',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'topic' => ClarificationTopic::class,
            'priority' => ClarificationPriority::class,
            'status' => ClarificationStatus::class,
            'unread_by_agency' => 'boolean',
            'unread_by_mcmc' => 'boolean',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ClarificationMessage::class)->orderBy('created_at');
    }

    public function consults(): HasMany
    {
        return $this->hasMany(ClarificationConsult::class);
    }

    public function activeConsults(): HasMany
    {
        return $this->consults()->where('status', '!=', ConsultStatus::Ended);
    }

    public function isActive(): bool
    {
        return $this->status !== ClarificationStatus::Closed;
    }

    /**
     * Open a new clarification request on an inquiry. Only one active
     * (non-closed) thread is allowed per inquiry at a time, mirroring
     * clarify-store.js's create() guard.
     */
    public static function open(Inquiry $inquiry, User $openedBy, ClarificationTopic $topic, ClarificationPriority $priority, string $text): self
    {
        if ($inquiry->clarificationThreads()->where('status', '!=', ClarificationStatus::Closed)->exists()) {
            throw new RuntimeException('An active clarification request already exists for this inquiry.');
        }

        $thread = static::create([
            'inquiry_id' => $inquiry->id,
            'opened_by' => $openedBy->id,
            'subject' => $topic->value,
            'topic' => $topic,
            'priority' => $priority,
            'status' => ClarificationStatus::Open,
            'unread_by_agency' => false,
            'unread_by_mcmc' => true,
        ]);

        $thread->messages()->create([
            'user_id' => $openedBy->id,
            'message' => $text,
        ]);

        return $thread;
    }

    public function reply(User $user, string $text): void
    {
        if (! $this->isActive()) {
            throw new RuntimeException('This clarification request is already closed.');
        }

        $this->messages()->create([
            'user_id' => $user->id,
            'message' => $text,
        ]);

        if ($user->isMcmcStaff()) {
            $this->update(['status' => ClarificationStatus::Answered, 'unread_by_agency' => true, 'unread_by_mcmc' => false]);
        } else {
            $this->update(['status' => ClarificationStatus::Open, 'unread_by_mcmc' => true, 'unread_by_agency' => false]);
        }
    }

    public function close(User $user, string $note, string $side): void
    {
        if (! $this->isActive()) {
            return;
        }

        $this->messages()->create([
            'user_id' => $user->id,
            'is_system' => true,
            'message' => $note,
        ]);

        foreach ($this->activeConsults as $consult) {
            $consult->update(['status' => ConsultStatus::Ended, 'unread' => false]);
        }

        $this->update([
            'status' => ClarificationStatus::Closed,
            'closed_by' => $user->id,
            'unread_by_agency' => $side === 'mcmc',
            'unread_by_mcmc' => $side === 'agency',
        ]);
    }

    public function inviteConsult(User $mcmcUser, Agency $agency, string $question): ClarificationConsult
    {
        if (! $this->isActive()) {
            throw new RuntimeException('Cannot consult on a closed request.');
        }

        if ($agency->id === $this->inquiry->agency_id) {
            throw new RuntimeException('This agency already owns the case.');
        }

        if ($this->activeConsults()->where('consulted_agency_id', $agency->id)->exists()) {
            throw new RuntimeException($agency->name.' is already consulting on this request.');
        }

        $consult = $this->consults()->create([
            'requested_by' => $mcmcUser->id,
            'consulted_agency_id' => $agency->id,
            'question' => $question,
            'status' => ConsultStatus::Pending,
            'unread' => true,
        ]);

        $this->messages()->create([
            'user_id' => $mcmcUser->id,
            'consult_agency_id' => $agency->id,
            'is_system' => true,
            'message' => $mcmcUser->name.' (MCMC) invited '.$agency->name.' to consult on this request.',
        ]);

        return $consult;
    }

    public function markRead(string $side): void
    {
        if ($side === 'agency' && $this->unread_by_agency) {
            $this->update(['unread_by_agency' => false]);
        } elseif ($side === 'mcmc' && $this->unread_by_mcmc) {
            $this->update(['unread_by_mcmc' => false]);
        }
    }
}
