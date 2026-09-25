<?php

namespace App\Enums;

enum ClarificationStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Awaiting MCMC Response',
            self::Answered => 'MCMC Responded',
            self::Closed => 'Resolved',
        };
    }
}
