<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClarificationThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_id',
        'opened_by',
        'subject',
        'status',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ClarificationMessage::class);
    }

    public function consults(): HasMany
    {
        return $this->hasMany(ClarificationConsult::class);
    }
}
