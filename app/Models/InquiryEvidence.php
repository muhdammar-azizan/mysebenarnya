<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InquiryEvidence extends Model
{
    use HasFactory;

    protected $table = 'inquiry_evidence';

    protected $fillable = [
        'inquiry_id',
        'uploaded_by',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'description',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
