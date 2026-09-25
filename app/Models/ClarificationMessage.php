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
        'message',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ClarificationThread::class, 'clarification_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
