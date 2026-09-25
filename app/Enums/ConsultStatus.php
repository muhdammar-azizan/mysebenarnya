<?php

namespace App\Enums;

enum ConsultStatus: string
{
    case Pending = 'pending';
    case Responded = 'responded';
    case Ended = 'ended';
}
