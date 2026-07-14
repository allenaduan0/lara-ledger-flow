<?php

namespace App\Modules\Transaction\Domain\Enums;

enum IdempotencyStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
