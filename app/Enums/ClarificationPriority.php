<?php

namespace App\Enums;

enum ClarificationPriority: string
{
    case Normal = 'Normal';
    case Urgent = 'Urgent';

    public function label(?string $locale = null): string
    {
        return __($this->value, [], $locale);
    }
}
