<?php

namespace App\Enums;

enum ClarificationStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(?string $locale = null): string
    {
        $english = match ($this) {
            self::Open => 'Awaiting MCMC Response',
            self::Answered => 'MCMC Responded',
            self::Closed => 'Resolved',
        };

        return __($english, [], $locale);
    }
}
