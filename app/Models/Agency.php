<?php

namespace App\Models;

use App\Enums\InquiryCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'specialization',
        'description',
        'contact_email',
        'contact_phone',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'specialization' => InquiryCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    /**
     * Derive a short unique code from an agency name, e.g. "Ministry of
     * Health Malaysia" -> "MOHM", disambiguating on collision.
     */
    public static function generateCodeFrom(string $name): string
    {
        $words = preg_split('/[\s\-]+/', preg_replace('/[^A-Za-z0-9\s\-]/', '', $name)) ?: [];
        $words = array_values(array_filter($words, fn ($w) => ! in_array(strtolower($w), ['of', 'the', 'and', 'for'], true)));

        $base = strtoupper(implode('', array_map(fn ($w) => $w[0], array_slice($words, 0, 5)))) ?: 'AGY';

        $code = $base;
        $suffix = 1;

        while (static::where('code', $code)->exists()) {
            $suffix++;
            $code = $base.$suffix;
        }

        return $code;
    }
}
