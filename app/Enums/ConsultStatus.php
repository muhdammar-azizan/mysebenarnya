<?php

namespace App\Enums;

enum ConsultStatus: string
{
    case Pending = 'pending';
    case Responded = 'responded';
    case Ended = 'ended';

    public function label(?string $locale = null): string
    {
        return __(match ($this) {
            self::Pending => 'Pending',
            self::Responded => 'Responded',
            self::Ended => 'Ended',
        }, [], $locale);
    }
}
